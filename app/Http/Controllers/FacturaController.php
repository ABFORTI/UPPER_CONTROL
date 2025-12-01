<?php
// app/Http/Controllers/FacturaController.php
namespace App\Http\Controllers;

use App\Models\Orden;
use App\Models\Factura;
use Illuminate\Http\Request;
use Inertia\Inertia;
use function auth; // para que el analizador reconozca el helper
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Services\Notifier;
use App\Jobs\GenerateFacturaPdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FacturaController extends Controller
{
  public function index(Request $request)
  {
    $u = $request->user();
    
    // Cliente solo puede ver sus propias facturas
    $isCliente = $u->hasRole('cliente');
    
    // Gerente: acceso solo lectura a consulta
    if (!$u->hasAnyRole(['admin','facturacion','gerente']) && !$isCliente) {
      abort(403);
    }

    $estatus = $request->get('estatus'); // opcional
    $centro  = $request->integer('centro') ?: null;
    $centroCosto = $request->integer('centro_costo') ?: null;
    $year = $request->integer('year') ?: null;
    $week = $request->integer('week') ?: null;

  // 1) Facturas existentes
  $qFact = Factura::query()->with([
    'orden.servicio','orden.centro','orden.area','orden.solicitud','orden.solicitud.centroCosto','orden.solicitud.marca',
    'ordenes.centro','ordenes.area','ordenes.solicitud.centroCosto','ordenes.solicitud.marca'
  ]);
  
  // Si es cliente, solo mostrar SUS facturas
  if ($isCliente) {
    $qFact->whereHas('orden.solicitud', fn($qq) => $qq->where('id_cliente', $u->id));
  }

  
  // Restricción por centro si no es admin NI facturacion
  // Facturación puede ver todas las facturas (como admin)
  elseif (!$u->hasAnyRole(['admin','facturacion'])) {
    $ids = $this->allowedCentroIds($u);
    if (!empty($ids)) {
      $qFact->whereHas('orden', fn($qq) => $qq->whereIn('id_centrotrabajo', $ids));
    } else {
      $qFact->whereRaw('1=0'); // sin centros asignados => nada
    }
  }
  // Filtro por centro explícito (si se pasa y está permitido)
  if ($centro) {
    if ($u->hasAnyRole(['admin','facturacion'])) {
      $qFact->whereHas('orden', fn($qq) => $qq->where('id_centrotrabajo', $centro));
    } else {
      $ids = $this->allowedCentroIds($u);
      if (in_array((int)$centro, array_map('intval',$ids), true)) {
        $qFact->whereHas('orden', fn($qq) => $qq->where('id_centrotrabajo', $centro));
      }
    }
  }
  // Filtro por Centro de Costos
  if ($centroCosto) {
    $qFact->where(function($q) use ($centroCosto){
      // Factura con orden directa
      $q->whereHas('orden.solicitud', function($qq) use ($centroCosto){ $qq->where('id_centrocosto', $centroCosto); })
        // o factura batch con alguna OT cuyo centro de costos coincida
        ->orWhereHas('ordenes.solicitud', function($qq) use ($centroCosto){ $qq->where('id_centrocosto', $centroCosto); });
    });
  }
  // Si el filtro es un estatus de factura, aplicarlo
  if ($estatus && in_array($estatus, ['pendiente','facturado','por_pagar','pagado'], true)) {
    $qFact->where('estatus', $estatus);
  }
  // Filtros de año y semana
  if ($year && $week) {
    $qFact->whereRaw('YEAR(created_at) = ? AND WEEK(created_at, 1) = ?', [$year, $week]);
  } elseif ($year) {
    $qFact->whereYear('created_at', $year);
  }
  $facturas = $qFact->latest('id')->limit(100)->get()->map(function($f){
    $ots = $f->ordenes?->pluck('id')->all() ?: ( $f->id_orden ? [$f->id_orden] : [] );
    $otsLabel = empty($ots) ? null : ('#'.implode(', #', $ots));
    $multi = is_array($ots) && count($ots) > 1;
    // Centro: ahora siempre un solo centro por factura (por validación en batch)
    $centroName = $f->orden?->centro?->nombre;
    if (!$centroName && $f->relationLoaded('ordenes') && $f->ordenes && $f->ordenes->count()>0) {
      $centroName = $f->ordenes->first()?->centro?->nombre;
    }
    return [
      'id' => (string) $f->id,
      'orden_id' => $f->id_orden,
      'ots' => $ots,
      'ots_label' => $otsLabel,
      'multi' => $multi,
      'servicio' => $multi ? 'Varios' : ($f->orden?->servicio?->nombre),
      'centro'   => $centroName ?: '—',
      'descripcion_general' => $multi ? null : ($f->orden?->descripcion_general),
      'producto' => $multi ? 'Varios' : ($f->orden?->descripcion_general),
      'area' => $multi ? 'Varios' : ($f->orden?->area?->nombre),
      'centro_costo' => $multi ? 'Varios' : ($f->orden?->solicitud?->centroCosto?->nombre),
      'marca' => $multi ? 'Varios' : ($f->orden?->solicitud?->marca?->nombre),
      'total'    => $f->total,
      'estatus'  => $f->estatus,
      'folio'    => $f->folio_externo,
      'created_at' => $f->created_at?->toDateTimeString(),
      'url' => route('facturas.show', $f->id),
    ];
  });

  // 2) OTs sin factura (incluye autorizada_cliente para generar) - SOLO para facturacion/admin
  $items = collect();

  // Cliente NO debe ver la lista de OTs pendientes de facturar (no puede crearlas)
  if (!$isCliente && (!$estatus || $estatus === 'autorizada_cliente' || $estatus === 'sin_factura')) {
    // Excluir OTs que ya tengan factura (directa o pivot)
    $ordenesConFacturaDirecta = Factura::query()
      ->when(!$u->hasRole('admin') && !$u->hasRole('facturacion'), function($q) use ($u) {
        $ids = $this->allowedCentroIds($u);
        $q->whereHas('orden', fn($qq) => $qq->whereIn('id_centrotrabajo', $ids));
      })
      ->pluck('id_orden');
    $ordenesConFacturaPivot = \Illuminate\Support\Facades\DB::table('factura_orden')->pluck('id_orden');
    $ordenesConFactura = $ordenesConFacturaDirecta->merge($ordenesConFacturaPivot)->unique()->values();

    $qOts = Orden::query()
      // cuando el filtro es 'autorizada_cliente' restringimos; si es 'sin_factura' mostramos cualquier estatus permitido
      ->when($estatus === 'autorizada_cliente', function($qq){ $qq->where('estatus','autorizada_cliente'); })
      ->when(!$u->hasRole('admin') && !$u->hasRole('facturacion'), function($qq) use ($u) {
        $ids = $this->allowedCentroIds($u);
        $qq->whereIn('id_centrotrabajo', $ids);
      })
      ->when($centro !== null, function($qq) use ($u, $centro) {
        if ($u->hasAnyRole(['admin','facturacion'])) { $qq->where('id_centrotrabajo', $centro); }
        else {
          $ids = $this->allowedCentroIds($u);
          if (in_array((int)$centro, array_map('intval',$ids), true)) { $qq->where('id_centrotrabajo', $centro); }
        }
      })
      ->whereNotIn('id', $ordenesConFactura->all())
      ->when($year && $week, function($qq) use ($year, $week) {
        $qq->whereRaw('YEAR(created_at) = ? AND WEEK(created_at, 1) = ?', [$year, $week]);
      })
      ->when($year && !$week, function($qq) use ($year) {
        $qq->whereYear('created_at', $year);
      })
      ->with(['servicio','centro','area','solicitud.centroCosto','solicitud.marca'])
      ->latest('id')
      ->limit(100);

    // Filtro por centro de costos para OTs pendientes
    if ($centroCosto) {
      $qOts->whereHas('solicitud', function($qq) use ($centroCosto){ $qq->where('id_centrocosto', $centroCosto); });
    }

    $ots = $qOts->get()->map(function($o){
      $total = $o->total ?? (($o->subtotal ?? 0) + ($o->iva ?? 0));
      return [
        'id' => 'OT-'.$o->id, // clave única en tabla
        'orden_id' => $o->id,
        'servicio' => $o->servicio?->nombre,
        'centro'   => $o->centro?->nombre,
        'descripcion_general' => $o->descripcion_general,
        'producto' => $o->descripcion_general,
        'area' => $o->area?->nombre,
        'centro_costo' => $o->solicitud?->centroCosto?->nombre,
        'marca' => $o->solicitud?->marca?->nombre,
        'total'    => $total,
        'estatus'  => 'autorizada_cliente',
        'folio'    => null,
        'created_at' => $o->created_at?->toDateTimeString(),
        'url' => route('facturas.createFromOrden', $o), // acción: generar factura
      ];
    });

    if (!$estatus) {
      // Mezclar ambos conjuntos si no hay filtro aplicado
      $items = $facturas->concat($ots);
    } else {
      // Solo OTs si filtro es 'autorizada_cliente' o 'sin_factura'
      $items = $ots;
    }
  } else {
    // Filtro de factura -> solo facturas
    $items = $facturas;
  }

  // Centros para selector
  $centrosLista = $u->hasAnyRole(['admin','facturacion'])
    ? \App\Models\CentroTrabajo::select('id','nombre')->orderBy('nombre')->get()
    : \App\Models\CentroTrabajo::whereIn('id', $this->allowedCentroIds($u))->select('id','nombre')->orderBy('nombre')->get();

  // Centros de costos para selector
  $centrosCostoLista = $u->hasAnyRole(['admin','facturacion'])
    ? \App\Models\CentroCosto::select('id','nombre','id_centrotrabajo')->orderBy('nombre')->get()
    : \App\Models\CentroCosto::whereIn('id_centrotrabajo', $this->allowedCentroIds($u))->select('id','nombre','id_centrotrabajo')->orderBy('nombre')->get();

  return Inertia::render('Facturas/Index', [
    'items' => $items,
    'filtros' => [ 'estatus' => $estatus, 'centro' => $centro, 'centro_costo' => $centroCosto, 'year' => $year, 'week' => $week ],
    'urls' => [ 'base' => route('facturas.index') ],
    'estatuses' => ['autorizada_cliente','sin_factura','facturado','por_pagar','pagado'],
    'centros' => $centrosLista,
    'centrosCosto' => $centrosCostoLista,
  ]);
  }

  /** Formulario para facturar múltiples OTs (subida de XML y resumen) */
  public function createBatch(Request $req)
  {
    $u = $req->user();
    if (!$u->hasAnyRole(['admin','facturacion'])) abort(403);

    // ids puede venir como array o string separado por comas
    $idsParam = $req->input('ids');
    $ids = [];
    if (is_array($idsParam)) { $ids = $idsParam; }
    elseif (is_string($idsParam)) { $ids = array_filter(array_map('intval', explode(',', $idsParam))); }
    $ids = array_values(array_unique(array_filter($ids, fn($v)=>$v>0)));
    if (empty($ids)) abort(422,'Selecciona al menos una OT.');

    $ordenes = Orden::whereIn('id', $ids)
      ->with(['servicio','centro','solicitud.cliente'])
      ->get();
    if ($ordenes->isEmpty()) abort(404,'No se encontraron OTs.');

    // Validaciones de estatus/centro
    foreach ($ordenes as $o) {
      if ($o->estatus !== 'autorizada_cliente') {
        abort(422, "La OT #{$o->id} no está autorizada por el cliente.");
      }
    }

    // Validar que todas las OTs pertenezcan al mismo Centro de Trabajo
    $centrosUnicos = $ordenes->pluck('id_centrotrabajo')->filter()->unique()->values();
    if ($centrosUnicos->count() > 1) {
      abort(422, 'No se puede generar una sola factura con OTs de diferentes Centros de Trabajo. Selecciona OTs del mismo centro.');
    }

    // Construir resumen (totales por OT y total general)
    $rows = $ordenes->map(function($o){
      $total = $o->total ?? (($o->subtotal ?? 0) + ($o->iva ?? 0));
      return [
        'id' => $o->id,
        'centro' => $o->centro?->nombre,
        'servicio' => $o->servicio?->nombre,
        'descripcion_general' => $o->descripcion_general,
        'total' => (float)$total,
      ];
    })->values();
    $suma = (float)$rows->sum('total');

    return Inertia::render('Facturas/CreateBatch', [
      'ordenes' => $rows,
      'suma_total' => $suma,
      'ids' => $ids,
      'urls' => [ 'store' => route('facturas.batch') ],
    ]);
  }
  
public function pdf(\App\Models\Factura $factura)
{
    $this->authorize('view', $factura);

    $refresh = request()->boolean('refresh');
    $existing = $factura->pdf_path && Storage::exists($factura->pdf_path);
    $stale = false;
    if ($existing) {
      try {
        $pdfAbs  = Storage::path($factura->pdf_path);
        $pdfTime = @filemtime($pdfAbs) ?: 0;
        $viewAbs = resource_path('views/pdf/factura.blade.php');
        $viewTime = @filemtime($viewAbs) ?: 0;
        $xmlTime = 0;
        if ($factura->xml_path && Storage::exists($factura->xml_path)) {
          $xmlAbs = Storage::path($factura->xml_path);
          $xmlTime = @filemtime($xmlAbs) ?: 0;
        }
        $stale = ($viewTime > $pdfTime) || ($xmlTime > $pdfTime);
      } catch (\Throwable $e) { $stale = false; }
    }
    if ($existing && !$refresh && !$stale) {
      $abs = Storage::path($factura->pdf_path);
      return response()->file($abs, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="Factura_' . $factura->id . '.pdf"'
      ]);
    }

  // Fallback on-demand con XML si existe
  $factura->load(['orden.servicio','orden.centro','orden.items']);
  $xmlData = null;
  if ($factura->xml_path && Storage::exists($factura->xml_path)) {
    try {
      $xmlString = Storage::get($factura->xml_path);
      $xml = simplexml_load_string($xmlString);
      if ($xml) {
        $ns = $xml->getDocNamespaces(true);
        $cfdi = $xml;
        if (isset($ns['cfdi'])) { $cfdi = $xml->children($ns['cfdi']); }

                $a = $xml->attributes();
        $serie = (string)($a['Serie'] ?? $a['serie'] ?? '');
        $folio = (string)($a['Folio'] ?? $a['folio'] ?? '');
        $fecha = (string)($a['Fecha'] ?? $a['fecha'] ?? '');
                $version = (string)($a['Version'] ?? $a['version'] ?? '');
                $noCert = (string)($a['NoCertificado'] ?? '');
                $cert = (string)($a['Certificado'] ?? '');
                $sello = (string)($a['Sello'] ?? '');
        $subTotal = (string)($a['SubTotal'] ?? $a['subTotal'] ?? $a['subtotal'] ?? '');
        $descuento = (string)($a['Descuento'] ?? $a['descuento'] ?? '');
        $total = (string)($a['Total'] ?? $a['total'] ?? '');
        $formaPago = (string)($a['FormaPago'] ?? $a['formaPago'] ?? '');
        $metodoPago = (string)($a['MetodoPago'] ?? $a['metodoPago'] ?? '');
        $moneda = (string)($a['Moneda'] ?? $a['moneda'] ?? '');
        $tipoComprobante = (string)($a['TipoDeComprobante'] ?? $a['tipoDeComprobante'] ?? '');
        $lugarExpedicion = (string)($a['LugarExpedicion'] ?? $a['lugarExpedicion'] ?? '');

                $emisor = null; $receptor = null; $conceptos = []; $impuestos = [ 'trasladados' => [], 'retenciones' => [], 'total_trasladados' => null, 'total_retenciones' => null ];
        // Emisor
        if (isset($cfdi->Emisor)) {
          $ea = $cfdi->Emisor->attributes();
          $emisor = [
            'rfc' => (string)($ea['Rfc'] ?? $ea['RFC'] ?? $ea['rfc'] ?? ''),
            'nombre' => (string)($ea['Nombre'] ?? $ea['nombre'] ?? ''),
            'regimen' => (string)($ea['RegimenFiscal'] ?? $ea['regimenFiscal'] ?? ''),
          ];
        }
        // Receptor
        if (isset($cfdi->Receptor)) {
          $ra = $cfdi->Receptor->attributes();
          $receptor = [
            'rfc' => (string)($ra['Rfc'] ?? $ra['RFC'] ?? $ra['rfc'] ?? ''),
            'nombre' => (string)($ra['Nombre'] ?? $ra['nombre'] ?? ''),
            'uso' => (string)($ra['UsoCFDI'] ?? $ra['usoCFDI'] ?? ''),
            'domicilio' => (string)($ra['DomicilioFiscalReceptor'] ?? ''),
            'regimen' => (string)($ra['RegimenFiscalReceptor'] ?? ''),
          ];
        }
        // Conceptos
        if (isset($cfdi->Conceptos) && isset($cfdi->Conceptos->Concepto)) {
          foreach ($cfdi->Conceptos->Concepto as $c) {
            $ca = $c->attributes();
            $traslados = [];
            if (isset($c->Impuestos) && isset($c->Impuestos->Traslados) && isset($c->Impuestos->Traslados->Traslado)) {
              foreach ($c->Impuestos->Traslados->Traslado as $t) {
                $ta = $t->attributes();
                $traslados[] = [
                  'impuesto' => (string)($ta['Impuesto'] ?? ''),
                  'tasa' => (string)($ta['TasaOCuota'] ?? ''),
                  'importe' => (string)($ta['Importe'] ?? ''),
                ];
              }
            }
            $conceptos[] = [
              'clave' => (string)($ca['ClaveProdServ'] ?? ''),
              'cantidad' => (string)($ca['Cantidad'] ?? ''),
              'clave_unidad' => (string)($ca['ClaveUnidad'] ?? ''),
              'unidad' => (string)($ca['Unidad'] ?? ''),
              'descripcion' => (string)($ca['Descripcion'] ?? ''),
              'valor_unitario' => (string)($ca['ValorUnitario'] ?? ''),
              'importe' => (string)($ca['Importe'] ?? ''),
              'traslados' => $traslados,
            ];
          }
        }
        // Impuestos globales
        if (isset($cfdi->Impuestos)) {
          $ia = $cfdi->Impuestos->attributes();
          $impuestos['total_trasladados'] = (string)($ia['TotalImpuestosTrasladados'] ?? '');
          $impuestos['total_retenciones'] = (string)($ia['TotalImpuestosRetenidos'] ?? '');
          if (isset($cfdi->Impuestos->Traslados) && isset($cfdi->Impuestos->Traslados->Traslado)) {
            foreach ($cfdi->Impuestos->Traslados->Traslado as $t) {
              $ta = $t->attributes();
              $impuestos['trasladados'][] = [
                'impuesto' => (string)($ta['Impuesto'] ?? ''),
                'tasa' => (string)($ta['TasaOCuota'] ?? ''),
                'importe' => (string)($ta['Importe'] ?? ''),
                'base' => (string)($ta['Base'] ?? ''),
                'tipo_factor' => (string)($ta['TipoFactor'] ?? ''),
              ];
            }
          }
          if (isset($cfdi->Impuestos->Retenciones) && isset($cfdi->Impuestos->Retenciones->Retencion)) {
            foreach ($cfdi->Impuestos->Retenciones->Retencion as $r) {
              $ra = $r->attributes();
              $impuestos['retenciones'][] = [
                'impuesto' => (string)($ra['Impuesto'] ?? ''),
                'importe' => (string)($ra['Importe'] ?? ''),
                'base' => (string)($ra['Base'] ?? ''),
                'tasa' => (string)($ra['TasaOCuota'] ?? ''),
                'tipo_factor' => (string)($ra['TipoFactor'] ?? ''),
              ];
            }
          }
        }
        // CfdiRelacionados
        $relacionados = null;
        if (isset($cfdi->CfdiRelacionados)) {
          $relacionados = [
            'tipo' => (string)($cfdi->CfdiRelacionados->attributes()['TipoRelacion'] ?? ''),
            'uuids' => [],
          ];
          if (isset($cfdi->CfdiRelacionados->CfdiRelacionado)) {
            foreach ($cfdi->CfdiRelacionados->CfdiRelacionado as $rel) {
              $relacionados['uuids'][] = (string)($rel->attributes()['UUID'] ?? '');
            }
          }
        }
        // Timbre
        $uuid = null; $fechaTimbrado = null; $noCertSAT = null;
                $uuid = null; $fechaTimbrado = null; $noCertSAT = null; $selloSAT = null;
        $tfdNode = null;
        if (isset($ns['tfd'])) {
          $complemento = $cfdi->Complemento ?? null;
          if ($complemento) {
            foreach ($complemento->children($ns['tfd']) as $child) {
              if ($child->getName() === 'TimbreFiscalDigital') { $tfdNode = $child; break; }
            }
          }
        }
        if (!$tfdNode) {
          // Fallback por xpath genérico
          $nodes = $xml->xpath('//*[local-name()="TimbreFiscalDigital"]');
          if (!empty($nodes)) { $tfdNode = $nodes[0]; }
        }
        if ($tfdNode) {
          $ta = $tfdNode->attributes();
          $uuid = (string)($ta['UUID'] ?? $ta['Uuid'] ?? '');
          $fechaTimbrado = (string)($ta['FechaTimbrado'] ?? '');
           $noCertSAT = (string)($ta['NoCertificadoSAT'] ?? '');
           $selloSAT = (string)($ta['SelloSAT'] ?? '');
        }

        $xmlData = [
          'version' => $version,
          'serie' => $serie,
          'folio' => $folio,
          'fecha' => $fecha,
          'subtotal' => $subTotal,
          'descuento' => $descuento,
          'total' => $total,
          'forma_pago' => $formaPago,
          'metodo_pago' => $metodoPago,
          'moneda' => $moneda,
          'tipo' => $tipoComprobante,
          'lugar_expedicion' => $lugarExpedicion,
          'no_certificado' => $noCert,
          'certificado' => $cert,
          'sello' => $sello,
          'emisor' => $emisor,
          'receptor' => $receptor,
          'conceptos' => $conceptos,
          'impuestos' => $impuestos,
          'relacionados' => $relacionados,
          'uuid' => $uuid,
          'fecha_timbrado' => $fechaTimbrado,
          'no_cert_sat' => $noCertSAT,
          'sello_sat' => $selloSAT,
        ];
        // QR SAT (si hay datos suficientes)
        $rfcEm = $emisor['rfc'] ?? '';
        $rfcRe = $receptor['rfc'] ?? '';
        $ttSat = $this->formatTotalSat($total);
        $ttDefault = $this->formatTotalDefault($total);
        if ($uuid && $rfcEm && $rfcRe && $ttSat) {
          $uuidUp = strtoupper(trim($uuid));
          $rfcEmUp = strtoupper(trim($rfcEm));
          $rfcReUp = strtoupper(trim($rfcRe));
          // 'fe' = últimos 8 del Sello CFDI (sin recortar '='). Fallback: SelloSAT
          $feCFD = $sello ? substr((string)$sello, -8) : null;
          $feSAT = $selloSAT ? substr((string)$selloSAT, -8) : null;
          $fe = $feCFD ?: $feSAT;
          $baseConsulta = 'https://verificacfdi.facturaelectronica.sat.gob.mx/Consulta/qr';
          $baseDefault  = 'https://verificacfdi.facturaelectronica.sat.gob.mx/Default.aspx';
          $queryConsulta = sprintf('id=%s&re=%s&rr=%s&tt=%s%s', $uuidUp, $rfcEmUp, $rfcReUp, $ttSat, $fe ? ('&fe=' . rawurlencode($fe)) : '');
          $queryDefault  = sprintf('id=%s&re=%s&rr=%s&tt=%s%s', $uuidUp, $rfcEmUp, $rfcReUp, $ttDefault, ($fe !== null && $fe !== '') ? ('&fe=' . $fe) : '');
          $urlConsulta = $baseConsulta . '?' . $queryConsulta;
          $urlDefault  = $baseDefault  . '?' . $queryDefault;
          // Exponer y usar Default.aspx como objetivo del QR pues es el que reportaste funcional
          $xmlData['sat_qr_url'] = $urlConsulta; // referencia
          $xmlData['sat_qr_url_alt'] = $urlDefault; // referencia
          $xmlData['sat_qr_url_consulta'] = $urlConsulta;
          $xmlData['sat_qr_url_default']  = $urlDefault;
          $xmlData['sat_qr_url_idonly'] = $baseConsulta . '?id=' . $uuidUp;
          $qrTarget = $urlDefault;
          $xmlData['sat_qr_target'] = $qrTarget;
          $xmlData['sat_qr_params'] = ['id'=>$uuidUp,'re'=>$rfcEmUp,'rr'=>$rfcReUp,'tt'=>$ttDefault,'fe'=>$fe,'fe_cfd'=>$feCFD,'fe_sat'=>$feSAT,'target'=>'default'];
          try {
            $clazz = '\\SimpleSoftwareIO\\QrCode\\Facades\\QrCode';
            if (class_exists($clazz)) {
              $png = $clazz::format('png')->errorCorrection('H')->size(256)->margin(1)->generate($qrTarget);
              $xmlData['sat_qr_png'] = 'data:image/png;base64,'.base64_encode($png);
            } else { $xmlData['sat_qr_png'] = null; }
          } catch (\Throwable $e) { $xmlData['sat_qr_png'] = null; }
          try {
            $clazz = '\\SimpleSoftwareIO\\QrCode\\Facades\\QrCode';
            if (class_exists($clazz)) {
              $svg = $clazz::format('svg')->errorCorrection('H')->size(256)->margin(1)->generate($qrTarget);
              $xmlData['sat_qr_svg_datauri'] = 'data:image/svg+xml;base64,'.base64_encode($svg);
            } else { $xmlData['sat_qr_svg_datauri'] = null; }
          } catch (\Throwable $e) { $xmlData['sat_qr_svg_datauri'] = null; }
        }
        // Árbol completo
        $xmlTree = $this->xmlElementToArray($xml);
      }
    } catch (\Throwable $e) { /* noop */ }
  }
  $pdf = PDF::loadView('pdf.factura', ['factura'=>$factura, 'xml'=>$xmlData])
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])
            ->setPaper('letter');
  // Cachear en disco y devolver
  $path = $factura->pdf_path ?: 'facturas/pdf/Factura_'.$factura->id.'.pdf';
  Storage::put($path, $pdf->output());
  if (!$factura->pdf_path) {
    $factura->update(['pdf_path' => $path, 'pdf_generated_at' => now()]);
  } else {
    $factura->update(['pdf_generated_at' => now()]);
  }
  $abs = Storage::path($path);
  return response()->file($abs, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' => 'inline; filename="Factura_' . $factura->id . '.pdf"'
  ]);
}

/**
 * Formatea el total para parámetro tt del SAT: 17 de longitud con 6 decimales.
 */
private function formatTotalSat($total): ?string
{
  if ($total === null || $total === '') return null;
  $s = str_replace(',', '.', trim((string)$total));
  // Mantener solo dígitos y punto
  $s = preg_replace('/[^0-9.]/', '', $s);
  if ($s === '' || $s === '.') return null;
  $parts = explode('.', $s, 2);
  $int = ltrim($parts[0] ?? '0', '0');
  if ($int === '') $int = '0';
  $frac = isset($parts[1]) ? preg_replace('/\D/', '', $parts[1]) : '';
  // Redondeo a 6 decimales basado en string
  if (strlen($frac) > 6) {
    $frac6 = substr($frac, 0, 6);
    $next = (int)($frac[6] ?? '0');
    if ($next >= 5) {
      $carry = 1;
      for ($i = 5; $i >= 0; $i--) {
        $d = ((int)$frac6[$i]) + $carry;
        if ($d >= 10) { $frac6[$i] = '0'; $carry = 1; }
        else { $frac6[$i] = (string)$d; $carry = 0; break; }
      }
      if ($carry === 1) {
        // carry a la parte entera
        $intLen = strlen($int);
        for ($j = $intLen - 1; $j >= 0; $j--) {
          $d = ((int)$int[$j]) + 1;
          if ($d >= 10) { $int[$j] = '0'; }
          else { $int[$j] = (string)$d; $carry = 0; break; }
        }
        if ($carry === 1) { $int = '1' . $int; }
      }
    }
    $frac = $frac6;
  }
  // Completar/podar a 6
  if (strlen($frac) < 6) { $frac = str_pad($frac, 6, '0'); }
  elseif (strlen($frac) > 6) { $frac = substr($frac, 0, 6); }
  $formatted = $int . '.' . $frac;
  return strlen($formatted) < 17 ? str_pad($formatted, 17, '0', STR_PAD_LEFT) : $formatted;
}

/**
 * Formatea el total para Default.aspx del SAT: usar el valor "tal cual" del XML
 * (sin padding, con los decimales originales), sanitizando separadores.
 */
private function formatTotalDefault($total): ?string
{
  if ($total === null || $total === '') return null;
  $s = trim((string)$total);
  // Normalizar separadores decimales, remover todo lo que no sea dígito o punto
  $s = str_replace(',', '.', $s);
  $s = preg_replace('/[^0-9.]/', '', $s);
  if ($s === '' || $s === '.') return null;
  // Si hay más de un punto, conservar solo un punto decimal
  $parts = explode('.', $s);
  if (count($parts) > 2) {
    $s = $parts[0] . '.' . implode('', array_slice($parts, 1));
  }
  return $s;
}

private function xmlElementToArray(\SimpleXMLElement $element): array
{
  $node = [];
  // Tag name
  $node['tag'] = $element->getName();
  // Attributes (todos los namespaces)
  $attrs = [];
  foreach ($element->attributes() as $k => $v) { $attrs[(string)$k] = (string)$v; }
  foreach ($element->getNamespaces(true) as $prefix => $ns) {
    foreach ($element->attributes($ns) as $k => $v) {
      $attrs[($prefix ? $prefix.':' : '').(string)$k] = (string)$v;
    }
  }
  if (!empty($attrs)) { $node['attrs'] = $attrs; }

  // Children (independientes de namespace)
  $children = [];
  $childNodes = $element->xpath('child::*') ?: [];
  foreach ($childNodes as $child) {
    $children[] = $this->xmlElementToArray($child);
  }
  if (!empty($children)) {
    $node['children'] = $children;
  } else {
    // Texto si no hay hijos
    $text = trim((string)$element);
    if ($text !== '') { $node['text'] = $text; }
  }
  return $node;
}
  
  /**
   * Parsear CFDI del XML asociado a la factura para mostrar en UI o PDF.
   */
  private function parseCfdi(?Factura $factura): ?array
  {
    if (!$factura || !$factura->xml_path || !Storage::exists($factura->xml_path)) return null;
    try {
      $xmlString = Storage::get($factura->xml_path);
      $xml = simplexml_load_string($xmlString);
      if (!$xml) return null;

      $ns = $xml->getDocNamespaces(true);
      $cfdi = isset($ns['cfdi']) ? $xml->children($ns['cfdi']) : $xml;

      $a = $xml->attributes();
      $data = [];
      $data['version'] = (string)($a['Version'] ?? $a['version'] ?? '');
      $data['serie']   = (string)($a['Serie'] ?? $a['serie'] ?? '');
      $data['folio']   = (string)($a['Folio'] ?? $a['folio'] ?? '');
      $data['fecha']   = (string)($a['Fecha'] ?? $a['fecha'] ?? '');
      $data['subtotal'] = (string)($a['SubTotal'] ?? $a['subTotal'] ?? $a['subtotal'] ?? '');
      $data['descuento'] = (string)($a['Descuento'] ?? $a['descuento'] ?? '');
      $data['total']   = (string)($a['Total'] ?? $a['total'] ?? '');
      $data['forma_pago']   = (string)($a['FormaPago'] ?? $a['formaPago'] ?? '');
      $data['metodo_pago']  = (string)($a['MetodoPago'] ?? $a['metodoPago'] ?? '');
      $data['moneda']  = (string)($a['Moneda'] ?? $a['moneda'] ?? '');
      $data['tipo']    = (string)($a['TipoDeComprobante'] ?? $a['tipoDeComprobante'] ?? '');
      $data['lugar_expedicion'] = (string)($a['LugarExpedicion'] ?? $a['lugarExpedicion'] ?? '');
      $data['no_certificado'] = (string)($a['NoCertificado'] ?? '');
      $data['certificado']    = (string)($a['Certificado'] ?? '');
      $data['sello']          = (string)($a['Sello'] ?? '');

      // Emisor
      if (isset($cfdi->Emisor)) {
        $ea = $cfdi->Emisor->attributes();
        $data['emisor'] = [
          'rfc' => (string)($ea['Rfc'] ?? $ea['RFC'] ?? $ea['rfc'] ?? ''),
          'nombre' => (string)($ea['Nombre'] ?? $ea['nombre'] ?? ''),
          'regimen' => (string)($ea['RegimenFiscal'] ?? $ea['regimenFiscal'] ?? ''),
        ];
      }
      // Receptor
      if (isset($cfdi->Receptor)) {
        $ra = $cfdi->Receptor->attributes();
        $data['receptor'] = [
          'rfc' => (string)($ra['Rfc'] ?? $ra['RFC'] ?? $ra['rfc'] ?? ''),
          'nombre' => (string)($ra['Nombre'] ?? $ra['nombre'] ?? ''),
          'uso' => (string)($ra['UsoCFDI'] ?? $ra['usoCFDI'] ?? ''),
          'domicilio' => (string)($ra['DomicilioFiscalReceptor'] ?? ''),
          'regimen' => (string)($ra['RegimenFiscalReceptor'] ?? ''),
        ];
      }
      // Conceptos
      $data['conceptos'] = [];
      if (isset($cfdi->Conceptos) && isset($cfdi->Conceptos->Concepto)) {
        foreach ($cfdi->Conceptos->Concepto as $c) {
          $ca = $c->attributes();
          $data['conceptos'][] = [
            'clave' => (string)($ca['ClaveProdServ'] ?? ''),
            'cantidad' => (string)($ca['Cantidad'] ?? ''),
            'clave_unidad' => (string)($ca['ClaveUnidad'] ?? ''),
            'unidad' => (string)($ca['Unidad'] ?? ''),
            'descripcion' => (string)($ca['Descripcion'] ?? ''),
            'valor_unitario' => (string)($ca['ValorUnitario'] ?? ''),
            'importe' => (string)($ca['Importe'] ?? ''),
          ];
        }
      }
      // Impuestos
      $data['impuestos'] = [ 'trasladados' => [], 'retenciones' => [], 'total_trasladados' => null, 'total_retenciones' => null ];
      if (isset($cfdi->Impuestos)) {
        $ia = $cfdi->Impuestos->attributes();
        $data['impuestos']['total_trasladados'] = (string)($ia['TotalImpuestosTrasladados'] ?? '');
        $data['impuestos']['total_retenciones'] = (string)($ia['TotalImpuestosRetenidos'] ?? '');
        if (isset($cfdi->Impuestos->Traslados) && isset($cfdi->Impuestos->Traslados->Traslado)) {
          foreach ($cfdi->Impuestos->Traslados->Traslado as $t) {
            $ta = $t->attributes();
            $data['impuestos']['trasladados'][] = [
              'impuesto' => (string)($ta['Impuesto'] ?? ''),
              'tasa' => (string)($ta['TasaOCuota'] ?? ''),
              'importe' => (string)($ta['Importe'] ?? ''),
              'base' => (string)($ta['Base'] ?? ''),
              'tipo_factor' => (string)($ta['TipoFactor'] ?? ''),
            ];
          }
        }
        if (isset($cfdi->Impuestos->Retenciones) && isset($cfdi->Impuestos->Retenciones->Retencion)) {
          foreach ($cfdi->Impuestos->Retenciones->Retencion as $r) {
            $ra = $r->attributes();
            $data['impuestos']['retenciones'][] = [
              'impuesto' => (string)($ra['Impuesto'] ?? ''),
              'importe' => (string)($ra['Importe'] ?? ''),
              'base' => (string)($ra['Base'] ?? ''),
              'tasa' => (string)($ra['TasaOCuota'] ?? ''),
              'tipo_factor' => (string)($ra['TipoFactor'] ?? ''),
            ];
          }
        }
      }
      // Relacionados
      if (isset($cfdi->CfdiRelacionados)) {
        $data['relacionados'] = [
          'tipo' => (string)($cfdi->CfdiRelacionados->attributes()['TipoRelacion'] ?? ''),
          'uuids' => [],
        ];
        if (isset($cfdi->CfdiRelacionados->CfdiRelacionado)) {
          foreach ($cfdi->CfdiRelacionados->CfdiRelacionado as $rel) {
            $data['relacionados']['uuids'][] = (string)($rel->attributes()['UUID'] ?? '');
          }
        }
      } else { $data['relacionados'] = null; }

      // Timbre
      $data['uuid'] = null; $data['fecha_timbrado'] = null; $data['no_cert_sat'] = null; $data['sello_sat'] = null;
      $tfdNode = null;
      if (isset($ns['tfd'])) {
        $complemento = $cfdi->Complemento ?? null;
        if ($complemento) {
          foreach ($complemento->children($ns['tfd']) as $child) {
            if ($child->getName() === 'TimbreFiscalDigital') { $tfdNode = $child; break; }
          }
        }
      }
      if (!$tfdNode) {
        $nodes = $xml->xpath('//*[local-name()="TimbreFiscalDigital"]');
        if (!empty($nodes)) { $tfdNode = $nodes[0]; }
      }
      if ($tfdNode) {
        $ta = $tfdNode->attributes();
        $data['uuid'] = (string)($ta['UUID'] ?? $ta['Uuid'] ?? '');
        $data['fecha_timbrado'] = (string)($ta['FechaTimbrado'] ?? '');
        $data['no_cert_sat'] = (string)($ta['NoCertificadoSAT'] ?? '');
        $data['sello_sat'] = (string)($ta['SelloSAT'] ?? '');
      }

      // QR para UI
      try {
        $rfcEm = $data['emisor']['rfc'] ?? '';
        $rfcRe = $data['receptor']['rfc'] ?? '';
        $ttSat = $this->formatTotalSat($data['total'] ?? null);
        $ttDefault = $this->formatTotalDefault($data['total'] ?? null);
        if (!empty($data['uuid']) && $rfcEm && $rfcRe && $ttSat) {
          $uuidUp = strtoupper(trim((string)$data['uuid']));
          $rfcEmUp = strtoupper(trim($rfcEm));
          $rfcReUp = strtoupper(trim($rfcRe));
          $feCFD = !empty($data['sello']) ? substr((string)$data['sello'], -8) : null;
          $feSAT = !empty($data['sello_sat']) ? substr((string)$data['sello_sat'], -8) : null;
          $fe = $feCFD ?: $feSAT;
          $baseConsulta = 'https://verificacfdi.facturaelectronica.sat.gob.mx/Consulta/qr';
          $baseDefault  = 'https://verificacfdi.facturaelectronica.sat.gob.mx/Default.aspx';
          $queryConsulta = sprintf('id=%s&re=%s&rr=%s&tt=%s%s', $uuidUp, $rfcEmUp, $rfcReUp, $ttSat, $fe ? ('&fe=' . rawurlencode($fe)) : '');
          $queryDefault  = sprintf('id=%s&re=%s&rr=%s&tt=%s%s', $uuidUp, $rfcEmUp, $rfcReUp, $ttDefault, ($fe !== null && $fe !== '') ? ('&fe=' . $fe) : '');
          $urlConsulta = $baseConsulta . '?' . $queryConsulta;
          $urlDefault  = $baseDefault  . '?' . $queryDefault;
          $data['sat_qr_url'] = $urlConsulta;
          $data['sat_qr_url_alt'] = $urlDefault;
          $data['sat_qr_url_consulta'] = $urlConsulta;
          $data['sat_qr_url_default']  = $urlDefault;
          $data['sat_qr_url_idonly'] = $baseConsulta . '?id=' . $uuidUp;
          // Objetivo del QR en UI: Default.aspx con tt del XML (como validaste manualmente)
          $qrTarget = $urlDefault;
          $data['sat_qr_target'] = $qrTarget;
          $data['sat_qr_params'] = ['id'=>$uuidUp,'re'=>$rfcEmUp,'rr'=>$rfcReUp,'tt'=>$ttDefault,'fe'=>$fe,'fe_cfd'=>$feCFD,'fe_sat'=>$feSAT,'target'=>'default'];
          try {
            $clazz = '\\SimpleSoftwareIO\\QrCode\\Facades\\QrCode';
            if (class_exists($clazz)) {
              $png = $clazz::format('png')->errorCorrection('H')->size(256)->margin(1)->generate($qrTarget);
              $data['sat_qr_png'] = 'data:image/png;base64,'.base64_encode($png);
            } else { $data['sat_qr_png'] = null; }
          } catch (\Throwable $e) { $data['sat_qr_png'] = null; }
          try {
            $clazz = '\\SimpleSoftwareIO\\QrCode\\Facades\\QrCode';
            if (class_exists($clazz)) {
              $svg = $clazz::format('svg')->errorCorrection('H')->size(256)->margin(1)->generate($qrTarget);
              $data['sat_qr_svg_datauri'] = 'data:image/svg+xml;base64,'.base64_encode($svg);
            } else { $data['sat_qr_svg_datauri'] = null; }
          } catch (\Throwable $e) { $data['sat_qr_svg_datauri'] = null; }
        }
      } catch (\Throwable $e) { /* noop */ }

      return $data;
    } catch (\Throwable $e) { return null; }
  }
  public function createFromOrden(\App\Models\Orden $orden) {
    $this->authFacturacion($orden);
    if ($orden->estatus !== 'autorizada_cliente') abort(422,'La OT no está autorizada por el cliente.');
  if ($f = \App\Models\Factura::where('id_orden',$orden->id)->first()) {
        return redirect()->route('facturas.show', $f->id);
    }

  // Cargar relaciones necesarias para mostrar resumen completo
  $orden->load(['servicio','centro','solicitud.cliente','items']);

  // Armar items (preferir cantidad_real, si no, cantidad_planeada)
  $items = $orden->items->map(function($it){
    $cant = $it->cantidad_real ?? $it->cantidad_planeada ?? 0;
    return [
      'id' => $it->id,
      'descripcion' => $it->descripcion,
      'tamano' => $it->tamano,
      'cantidad' => (float) $cant,
      'precio_unitario' => (float) ($it->precio_unitario ?? 0),
      'subtotal' => (float) ($it->subtotal ?? (($it->precio_unitario ?? 0) * ($cant ?? 0))),
    ];
  })->values();

  // Totales (fallback si no están en la OT)
  $subtotal = $orden->subtotal ?? $items->sum('subtotal');
  $iva      = $orden->iva ?? 0;
  $total    = $orden->total ?? ($subtotal + $iva);

  // Agregados para resumen
  $cantidadTotal = (float) $items->sum(fn($i)=> (float) ($i['cantidad'] ?? 0));
  $precioUnitarioCalc = $cantidadTotal > 0 ? (float) ($subtotal / $cantidadTotal) : 0.0;

  return \Inertia\Inertia::render('Facturas/CreateFromOrden', [
    'orden'          => [
      'id' => $orden->id,
      'empresa' => $orden->centro?->nombre, // alias solicitado
      'centro' => [ 'id' => $orden->centro?->id, 'nombre' => $orden->centro?->nombre ],
      'servicio' => [ 'id' => $orden->servicio?->id, 'nombre' => $orden->servicio?->nombre ],
      'cliente'  => $orden->solicitud?->cliente?->only(['id','name','email']),
      'descripcion_general' => $orden->descripcion_general,
      'items'    => $items,
      'totales'  => [
        'subtotal' => (float) $subtotal,
        'iva'      => (float) $iva,
        'total'    => (float) $total,
      ],
      'resumen'  => [
        'cantidad_total' => $cantidadTotal,
        'precio_unitario' => $precioUnitarioCalc,
      ],
    ],
    'total_sugerido' => $orden->total_planeado ?? $total,
    'urls'           => [
      'store' => route('facturas.storeFromOrden', $orden), // <- absoluta
    ],
  ]);
}

  public function storeFromOrden(Request $req, Orden $orden)
    {
        $this->authFacturacion($orden);
        $req->validate([
      'total' => ['required','numeric','min:0'],
      'folio' => ['nullable','string','max:100'],
      'folio_externo' => ['nullable','string','max:100'],
      'xml' => ['nullable','file','mimetypes:text/xml,application/xml','max:2048'],
    ]);

    // Si viene XML, guardarlo y extraer datos por si faltan
    $xmlPath = null; $xmlFolio = null; $xmlUUID = null; $xmlTotal = null;
    if ($req->hasFile('xml')) {
      $xmlPath = $req->file('xml')->store('facturas/xml', 'local');
      try {
        $xml = simplexml_load_string(file_get_contents(storage_path('app/'.$xmlPath)));
        if ($xml) {
          // Manejar namespaces opcionales
          $namespaces = $xml->getDocNamespaces();
          $cfdi = isset($namespaces['cfdi']) ? $xml->children($namespaces['cfdi']) : $xml;
          // Folio / Total
          $attrs = $xml->attributes();
          if ($attrs) {
            $xmlFolio = (string)($attrs['Folio'] ?? $attrs['folio'] ?? '');
            $xmlTotal = (string)($attrs['Total'] ?? $attrs['total'] ?? '');
          }
          // Timbre y UUID
          foreach ($xml->xpath('//@UUID') as $attr) { $xmlUUID = (string)$attr; break; }
          if (!$xmlUUID) {
            foreach ($xml->xpath('//@Uuid') as $attr) { $xmlUUID = (string)$attr; break; }
          }
          // RFC Emisor
          $rfcEmisor = null;
          $nodesEm = $xml->xpath('//*[local-name()="Emisor"]/@Rfc | //*[@*="Emisor"]/@Rfc');
          if (!empty($nodesEm)) { $rfcEmisor = (string)$nodesEm[0]; }
          // Renombrar archivo si tenemos UUID o RFC Emisor
          $safeUuid = $xmlUUID ? preg_replace('/[^A-Za-z0-9-]/','',$xmlUUID) : null;
          $safeRfc  = $rfcEmisor ? preg_replace('/[^A-Za-z0-9]/','',$rfcEmisor) : null;
          if ($safeUuid || $safeRfc) {
            $fileName = trim(($safeRfc ?: 'CFDI').'_'.($safeUuid ?: uniqid()).'.xml','_');
            $newPath = 'facturas/xml/'.$fileName;
            \Illuminate\Support\Facades\Storage::move($xmlPath, $newPath);
            $xmlPath = $newPath;
          }
        }
      } catch (\Throwable $e) { /* noop */ }
    }

    $folio = $req->string('folio')->toString() ?: ($xmlFolio ?: null);
    $folio_externo = $req->string('folio_externo')->toString() ?: ($xmlUUID ?: null);
    $total = $req->input('total');
    if ((!$total || $total <= 0) && is_numeric($xmlTotal)) {
      $total = (float)$xmlTotal;
    }

    $factura = Factura::create([
      'id_orden' => $orden->id,
      'total' => $total,
      'folio' => $folio,
      'folio_externo' => $folio_externo,
      'estatus' => 'facturado',
      'fecha_facturado' => now()->toDateString(),
      'xml_path' => $xmlPath,
    ]);
    
    // Registrar actividad
    $this->act('facturas')
        ->performedOn($factura)
        ->event('crear_factura')
        ->withProperties(['orden_id' => $orden->id, 'total' => $factura->total])
        ->log("Factura #{$factura->id} creada para OT #{$orden->id}");
    
    // Generar PDF y enviar notificación al cliente con el PDF adjunto
    GenerateFacturaPdf::dispatch($factura->id, true);
    
    return redirect()->route('facturas.show',$factura->id)->with('ok','Factura registrada');
  }

  public function show(\App\Models\Factura $factura) {
  $this->authorize('view', $factura);
  // Cliente puede ver, pero authFacturacion solo verifica que pertenezca al centro
  // Removido: $this->authFacturacion($factura->orden);
    $factura->load(
      'orden.servicio','orden.centro','orden.solicitud.cliente',
      'ordenes.servicio','ordenes.centro','ordenes.area','ordenes.solicitud.centroCosto','ordenes.solicitud.marca'
    );

  return \Inertia\Inertia::render('Facturas/Show', [
    'factura' => $factura->toArray(),
    'ordenes' => $factura->ordenes?->map(function($o){
      return [
        'id' => $o->id,
        'servicio' => $o->servicio?->nombre,
        'centro' => $o->centro?->nombre,
        'area' => $o->area?->nombre,
        'marca' => $o->solicitud?->marca?->nombre,
        'centro_costo' => $o->solicitud?->centroCosto?->nombre,
        'descripcion_general' => $o->descripcion_general,
        'total' => $o->total ?? (($o->subtotal ?? 0)+($o->iva ?? 0)),
        'url' => route('ordenes.show', $o),
      ];
    })->values(),
    'cfdi'    => $this->parseCfdi($factura),
    'urls'    => [
      'facturado' => route('facturas.facturado', $factura),
      'cobro'     => route('facturas.cobro',     $factura),
      'pagado'    => route('facturas.pagado',    $factura),
      'pdf'       => route('facturas.pdf', $factura),
      'xml'       => route('facturas.xml', $factura),
    ],
  ]);
}

  public function uploadXml(Request $req, Factura $factura)
  {
    $this->authorize('operar', $factura);
    $this->authFacturacion($factura->orden);
    $req->validate(['xml'=>['required','file','mimetypes:text/xml,application/xml','max:4096']]);
    // Borrar XML previo si existe
    if ($factura->xml_path && Storage::exists($factura->xml_path)) {
      Storage::delete($factura->xml_path);
    }
  $xmlPath = $req->file('xml')->store('facturas/xml','local');
    // Parseo mínimo para actualizar campos si vienen vacíos
    try {
      $xmlString = Storage::get($xmlPath);
      $xml = simplexml_load_string($xmlString);
      if ($xml) {
        $a = $xml->attributes();
        $folio = (string)($a['Folio'] ?? $a['folio'] ?? '');
        $total = (string)($a['Total'] ?? $a['total'] ?? '');
        $uuid = null; $nodes = $xml->xpath('//@UUID'); if (!empty($nodes)) { $uuid = (string)$nodes[0]; }
        // RFC Emisor
        $rfcEmisor = null; $nodesEm = $xml->xpath('//*[local-name()="Emisor"]/@Rfc | //*[@*="Emisor"]/@Rfc');
        if (!empty($nodesEm)) { $rfcEmisor = (string)$nodesEm[0]; }
        // Renombrar archivo si es posible
        $safeUuid = $uuid ? preg_replace('/[^A-Za-z0-9-]/','',$uuid) : null;
        $safeRfc  = $rfcEmisor ? preg_replace('/[^A-Za-z0-9]/','',$rfcEmisor) : null;
        if ($safeUuid || $safeRfc) {
          $fileName = trim(($safeRfc ?: 'CFDI').'_'.($safeUuid ?: uniqid()).'.xml','_');
          $newPath = 'facturas/xml/'.$fileName;
          if (\Illuminate\Support\Facades\Storage::exists($xmlPath)) {
            \Illuminate\Support\Facades\Storage::move($xmlPath, $newPath);
            $xmlPath = $newPath;
          }
        }
        $updates = ['xml_path'=>$xmlPath, 'pdf_path'=>null];
        if (!$factura->folio && $folio) $updates['folio'] = $folio;
        if (!$factura->folio_externo && $uuid) $updates['folio_externo'] = $uuid;
        if ((!$factura->total || $factura->total <= 0) && is_numeric($total)) $updates['total'] = (float)$total;
        $factura->update($updates);
      } else {
        $factura->update(['xml_path'=>$xmlPath, 'pdf_path'=>null]);
      }
    } catch (\Throwable $e) {
      $factura->update(['xml_path'=>$xmlPath, 'pdf_path'=>null]);
    }
    // Regenerar PDF al vuelo para cachear
    try {
      $this->pdf($factura);
    } catch (\Throwable $e) { /* noop */ }
    return back()->with('ok','XML actualizado');
  }

  /**
   * Facturación múltiple: crear una factura que agrupe varias OTs.
   * Requisitos: rol facturacion|admin, OTs en estatus 'autorizada_cliente'.
   * Entrada: orden_ids[] (int), folio_externo (string opcional), fecha (date opcional)
   */
  public function storeBatch(Request $req)
  {
    $u = $req->user();
    if (!$u->hasAnyRole(['admin','facturacion'])) abort(403);
    $data = $req->validate([
      'orden_ids' => ['required','array','min:1'],
      'orden_ids.*' => ['integer','exists:ordenes_trabajo,id'],
      'folio' => ['nullable','string','max:100'],
      'folio_externo' => ['nullable','string','max:100'],
      'fecha' => ['nullable','date'],
      'xml' => ['nullable','file','mimetypes:text/xml,application/xml','max:4096'],
    ]);

    // Cargar OTs y validar estatus y centros
    $ordenes = Orden::whereIn('id', $data['orden_ids'])->with(['solicitud','centro'])->get();
    if ($ordenes->count() === 0) abort(422,'Sin órdenes válidas');

    // Validar que NINGUNA de las OTs ya tenga factura (directa o vía pivot)
    $idsSel = $ordenes->pluck('id')->map(fn($v)=>(int)$v)->all();
    $usadasDirecto = Factura::query()->whereIn('id_orden', $idsSel)->pluck('id_orden')->map(fn($v)=>(int)$v)->all();
    $usadasPivot   = \Illuminate\Support\Facades\DB::table('factura_orden')->whereIn('id_orden', $idsSel)->pluck('id_orden')->map(fn($v)=>(int)$v)->all();
    $ocupadas = array_values(array_unique(array_merge($usadasDirecto, $usadasPivot)));
    if (!empty($ocupadas)) {
      $txt = '#'.implode(', #', $ocupadas);
      abort(422, 'Algunas OTs seleccionadas ya están asociadas a una factura: '.$txt);
    }
    foreach ($ordenes as $o) {
      // Estatus requerido
      if ($o->estatus !== 'autorizada_cliente') {
        abort(422, "La OT #{$o->id} no está autorizada por el cliente.");
      }
      // Centro permitido (facturación tiene bypass, admin tiene bypass)
      if (!$u->hasAnyRole(['admin','facturacion'])) {
        $ids = $this->allowedCentroIds($u);
        if (!in_array((int)$o->id_centrotrabajo, array_map('intval',$ids), true)) abort(403);
      }
    }

    // Validar que todas las OTs pertenezcan al mismo Centro de Trabajo
    $centrosUnicos = $ordenes->pluck('id_centrotrabajo')->filter()->unique()->values();
    if ($centrosUnicos->count() > 1) {
      abort(422, 'No se puede generar una sola factura con OTs de diferentes Centros de Trabajo. Selecciona OTs del mismo centro.');
    }

    // Calcular total (fallback a subtotal+iva si total está nulo)
    $total = $ordenes->sum(function($o){
      $t = $o->total; if ($t === null) $t = (float)($o->subtotal ?? 0) + (float)($o->iva ?? 0); return (float)$t;
    });

    // Manejo opcional de XML (si se envía): extraer folio/uuid/total y almacenar
    $xmlPath = null; $xmlFolio = null; $xmlUUID = null; $xmlTotal = null;
    if ($req->hasFile('xml')) {
      $xmlPath = $req->file('xml')->store('facturas/xml','local');
      try {
        $xmlString = \Illuminate\Support\Facades\Storage::get($xmlPath);
        $xml = simplexml_load_string($xmlString);
        if ($xml) {
          $a = $xml->attributes();
          $xmlFolio = (string)($a['Folio'] ?? $a['folio'] ?? '');
          $xmlTotal = (string)($a['Total'] ?? $a['total'] ?? '');
          $uuid = null; $nodes = $xml->xpath('//@UUID'); if (!empty($nodes)) { $uuid = (string)$nodes[0]; }
          if (!$uuid) { $nodes = $xml->xpath('//@Uuid'); if (!empty($nodes)) { $uuid = (string)$nodes[0]; } }
          $xmlUUID = $uuid ?: null;
          // Renombrar con RFC/UUID si es posible
          $rfcEm = null; $nodesEm = $xml->xpath('//*[local-name()="Emisor"]/@Rfc | //*[@*="Emisor"]/@Rfc');
          if (!empty($nodesEm)) { $rfcEm = (string)$nodesEm[0]; }
          $safeUuid = $xmlUUID ? preg_replace('/[^A-Za-z0-9-]/','',$xmlUUID) : null;
          $safeRfc  = $rfcEm ? preg_replace('/[^A-Za-z0-9]/','',$rfcEm) : null;
          if ($safeUuid || $safeRfc) {
            $fileName = trim(($safeRfc ?: 'CFDI').'_'.($safeUuid ?: uniqid()).'.xml','_');
            $newPath = 'facturas/xml/'.$fileName;
            \Illuminate\Support\Facades\Storage::move($xmlPath, $newPath);
            $xmlPath = $newPath;
          }
        }
      } catch (\Throwable $e) { /* noop */ }
    }

  // Usar una OT como "ancla" para cumplir con la FK id_orden en facturas (debe estar libre de factura)
  $anchor = $ordenes->first(function($o) use ($usadasDirecto){ return !in_array((int)$o->id, $usadasDirecto, true); }) ?: $ordenes->first();
    // Si el XML trae total numérico, usarlo como total
    if ($xmlTotal !== null && is_numeric($xmlTotal)) {
      $total = (float)$xmlTotal;
    }

    $factura = \Illuminate\Support\Facades\DB::transaction(function() use ($anchor, $total, $data, $xmlFolio, $xmlUUID, $xmlPath, $ordenes) {
      $f = Factura::create([
        'id_orden' => $anchor->id, // ancla para compatibilidad
        'total' => $total,
        'folio' => ($data['folio'] ?? null) ?: ($xmlFolio ?: null),
        'folio_externo' => ($data['folio_externo'] ?? null) ?: ($xmlUUID ?: null),
        'estatus' => 'facturado',
        'fecha_facturado' => ($data['fecha'] ?? now()->toDateString()),
        'xml_path' => $xmlPath,
      ]);
      // Asociar todas las OTs a la factura (incluida la ancla)
      $ids = $ordenes->pluck('id')->all();
      $f->ordenes()->syncWithoutDetaching($ids);
      // Cambiar estatus de OTs a 'facturada'
      Orden::whereIn('id', $ids)->update(['estatus' => 'facturada']);
      return $f;
    });

    // Notificaciones: enviar a TODOS los clientes del centro de trabajo
    // de las OTs facturadas (facturación por periodos por centro).
    try {
      $centroId = (int)($ordenes->first()?->id_centrotrabajo ?? 0);
      if ($centroId > 0) {
        // Obtener todos los clientes cuyo centro_trabajo_id coincide
        $clientes = \App\Models\User::role('cliente')
          ->where('centro_trabajo_id', $centroId)
          ->get();

        foreach ($clientes as $cliente) {
          Notifier::toUser(
            $cliente->id,
            'Factura generada',
            "Se generó la factura #{$factura->id} para órdenes de tu centro de trabajo.",
            route('facturas.show', $factura->id)
          );
        }
      }
    } catch (\Throwable $e) { /* noop */ }

    // Generar PDF asíncronamente
    try { GenerateFacturaPdf::dispatch($factura->id); } catch (\Throwable $e) { /* noop */ }

    return redirect()->route('facturas.show', $factura->id)->with('ok','Factura generada para las OTs seleccionadas');
  }

  public function marcarFacturado(Request $req, Factura $factura) {
    $this->authorize('operar', $factura);
    $this->authFacturacion($factura->orden);
    $req->validate(['folio_externo'=>['required','string','max:100']]);
    $factura->update([
      'estatus'=>'facturado',
      'folio_externo'=>$req->folio_externo,
      'fecha_facturado'=>now()->toDateString(),
    ]);
    // Notificar a todos los clientes del centro de trabajo de la factura
    try {
      $centroId = (int)($factura->orden?->id_centrotrabajo ?? 0);
      if ($centroId > 0) {
        $clientes = \App\Models\User::role('cliente')
          ->where('centro_trabajo_id', $centroId)
          ->get();
        foreach ($clientes as $cliente) {
          Notifier::toUser(
            $cliente->id,
            'Factura actualizada',
            "La factura #{$factura->id} está lista para pago. Favor de realizarlo en el tiempo establecido.",
            route('facturas.show',$factura->id)
          );
        }
      }
    } catch (\Throwable $e) { /* noop */ }
        $this->act('facturas')
            ->performedOn($factura)
            ->event('estatus')
            ->withProperties(['estatus' => $factura->estatus])
            ->log("Factura #{$factura->id} actualizada a {$factura->estatus}");
        GenerateFacturaPdf::dispatch($factura->id);
    return back()->with('ok','Marcada como facturada');
  }

  public function marcarCobro(Request $req, Factura $factura) {
  $this->authorize('operar', $factura);
  $this->authFacturacion($factura->orden);
  $factura->update(['estatus'=>'por_pagar', 'fecha_cobro'=>now()->toDateString()]);
  // Notificar a todos los clientes del centro de trabajo de la factura
  try {
    $centroId = (int)($factura->orden?->id_centrotrabajo ?? 0);
    if ($centroId > 0) {
      $clientes = \App\Models\User::role('cliente')
        ->where('centro_trabajo_id', $centroId)
        ->get();
      foreach ($clientes as $cliente) {
        Notifier::toUser(
          $cliente->id,
          'Factura actualizada',
          "La factura #{$factura->id} está lista para pagar. Favor de hacerlo en el tiempo establecido.",
          route('facturas.show',$factura->id)
        );
      }
    }
  } catch (\Throwable $e) { /* noop */ }
        $this->act('facturas')
            ->performedOn($factura)
            ->event('estatus')
            ->withProperties(['estatus' => $factura->estatus])
            ->log("Factura #{$factura->id} actualizada a {$factura->estatus}");
        GenerateFacturaPdf::dispatch($factura->id);
  return back()->with('ok','Cobro registrado (por pagar)');
  }

  public function marcarPagado(Request $req, Factura $factura) {
  $this->authorize('operar', $factura);
  $this->authFacturacion($factura->orden);
  $factura->update(['estatus'=>'pagado', 'fecha_pagado'=>now()->toDateString()]);
  // Notificar a todos los clientes del centro de trabajo de la factura
  try {
    $centroId = (int)($factura->orden?->id_centrotrabajo ?? 0);
    if ($centroId > 0) {
      $clientes = \App\Models\User::role('cliente')
        ->where('centro_trabajo_id', $centroId)
        ->get();
      foreach ($clientes as $cliente) {
        Notifier::toUser(
          $cliente->id,
          'Factura actualizada',
          "La factura #{$factura->id} ha sido marcada como pagada. ¡Gracias por completar el proceso!",
          route('facturas.show',$factura->id)
        );
      }
    }
  } catch (\Throwable $e) { /* noop */ }
        $this->act('facturas')
            ->performedOn($factura)
            ->event('estatus')
            ->withProperties(['estatus' => $factura->estatus])
            ->log("Factura #{$factura->id} actualizada a {$factura->estatus}");
        GenerateFacturaPdf::dispatch($factura->id);
  return back()->with('ok','Pago confirmado');
  }

  private function authFacturacion(\App\Models\Orden $orden): void {
    $u = request()->user();
    if ($u->hasRole('admin')) return;
    // Facturación tiene acceso a todas las órdenes
    if ($u->hasRole('facturacion')) return;
    // Para otros roles, verificar centros asignados
    if (!$u->hasRole('facturacion')) abort(403);
    $ids = $this->allowedCentroIds($u);
    if (!in_array((int)$orden->id_centrotrabajo, array_map('intval', $ids), true)) abort(403);
  }

  private function allowedCentroIds(\App\Models\User $u): array
  {
    if ($u->hasAnyRole(['admin','facturacion'])) return [];
    $ids = $u->centros()->pluck('centros_trabajo.id')->map(fn($v)=>(int)$v)->all();
    $primary = (int)($u->centro_trabajo_id ?? 0);
    if ($primary) $ids[] = $primary;
    return array_values(array_unique(array_filter($ids)));
  }
  private function act(string $log)
  {
      return app(\Spatie\Activitylog\ActivityLogger::class)->useLog($log);
  }
}

@php
  $f = $factura; $o = $f->orden; $x = $xml ?? null;
  // Buscar logo en public/ y embeberlo como base64 para asegurar que DomPDF lo muestre
  $logoSrc = null;
  foreach (['logo.png','img/logo.png','images/logo.png'] as $cand) {
    $p = public_path($cand);
    if (file_exists($p)) {
      $mime = function_exists('mime_content_type') ? (mime_content_type($p) ?: 'image/png') : 'image/png';
      $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(@file_get_contents($p));
      break;
    }
  }
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { size: Letter; margin: 14mm; }
    body { font-family: Helvetica, Arial, sans-serif; color:#000; }

  .bar     { background:#1A73E8; color:#fff; padding:8pt 10pt; font-weight:700; font-size:12pt; position:relative; padding-right:96pt; min-height:30pt; letter-spacing:-0.2pt; }
  .bar .logo { position:absolute; right:10pt; top:8pt; height:30pt; }
    .subgrid { display:grid; grid-template-columns: 40% 25% 35%; gap:0; margin:0 0 4pt; }

    .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:10pt; margin:0 0 6pt; }
    .card { border:0.25pt solid lightgrey; padding:6pt; }
  .card .title { background:#F5F7FA; font-weight:700; color:#2E3A59; padding:0 0 4pt; margin:0 0 4pt; font-size:9pt; letter-spacing:-0.15pt; }
  .label { font-weight:700; color:#2E3A59; font-size:9pt; letter-spacing:-0.15pt; }
  .text  { font-size:9pt; letter-spacing:-0.15pt; line-height:1.2; }

    .paybar { display:grid; grid-template-columns: 1fr 1fr 1fr; gap:0; background:#F5F7FA; border:0.25pt solid lightgrey; padding:6pt; margin:0 0 8pt; }

    table { width:100%; border-collapse:collapse; }
  th, td { border:0.25pt solid lightgrey; padding:6pt; font-size:8pt; letter-spacing:-0.1pt; }
    thead th { background:#F5F7FA; font-weight:700; text-align:center; }

    .concepts thead th:nth-child(1){ width:8%;  }
    .concepts thead th:nth-child(2){ width:18%; }
    .concepts thead th:nth-child(3){ width:44%; }
    .concepts thead th:nth-child(4){ width:15%; }
    .concepts thead th:nth-child(5){ width:15%; }

    .totals { display:grid; grid-template-columns: 60% 40%; margin:0 0 8pt; }
    .totbox { border:0.25pt solid lightgrey; }
    .totbox table { width:100%; border-collapse:collapse; }
    .totbox td { border:0.25pt solid lightgrey; padding:6pt; font-size:9pt; text-align:right; }
    .totbox tr:last-child td { font-weight:700; }

    .stamp { border:0.25pt solid lightgrey; padding:6pt; }
    .stamp .head { background:#F5F7FA; font-weight:700; color:#2E3A59; padding:4pt 6pt; margin:-6pt -6pt 6pt; }
    .muted { color:#6C7A96; font-size:8pt; }
  .break { word-break: break-all; overflow-wrap: anywhere; white-space: normal; }
  </style>
</head>
<body>
  <!-- Barra superior -->
  <div class="bar">
    <span>{{ $x['emisor']['nombre'] ?? 'Representación CFDI' }}</span>
    @if($logoSrc)
      <img class="logo" src="{{ $logoSrc }}" alt="Logo">
    @endif
  </div>

  <!-- Subheader: Serie/Folio | Tipo | Fecha -->
  @php
    $tipoLetra = $x['tipo'] ?? null;
    $mapTipo = ['I'=>'Ingreso','E'=>'Egreso','T'=>'Traslado','P'=>'Pago','N'=>'Nómina'];
    $tipoEtiqueta = $tipoLetra ? (strtoupper($tipoLetra).'-'.($mapTipo[strtoupper($tipoLetra)] ?? $tipoLetra)) : '—';
  @endphp
  <div class="subgrid text" style="margin-top:6pt;">
    <div>Serie: {{ $x['serie'] ?? '—' }} Folio: {{ $x['folio'] ?? '—' }}</div>
    <div>Tipo: {{ $tipoEtiqueta }}</div>
    <div>Fecha: {{ $x['fecha'] ?? ($f->created_at?->format('Y-m-d\\TH:i:s')) }}</div>
  </div>

  <!-- Tarjetas Emisor / Receptor -->
  <div class="grid-2">
    <div class="card">
      <div class="title">Emisor</div>
      <div class="text"><span class="label">RFC:</span> {{ $x['emisor']['rfc'] ?? '—' }}</div>
      <div class="text"><span class="label">Nombre:</span> {{ $x['emisor']['nombre'] ?? '—' }}</div>
      <div class="text"><span class="label">Régimen:</span> {{ $x['emisor']['regimen'] ?? '—' }}</div>
      <div class="text"><span class="label">Lugar de expedición:</span> {{ $x['lugar_expedicion'] ?? '—' }}</div>
    </div>
    <div class="card">
      <div class="title">Receptor</div>
      <div class="text"><span class="label">RFC:</span> {{ $x['receptor']['rfc'] ?? '—' }}</div>
      <div class="text"><span class="label">Nombre:</span> {{ $x['receptor']['nombre'] ?? ($o->solicitud->cliente->name ?? '—') }}</div>
      <div class="text"><span class="label">Uso CFDI:</span> {{ $x['receptor']['uso'] ?? '—' }}</div>
    </div>
  </div>

  <!-- Barra de Pago -->
  <div class="paybar text">
    <div><span class="label">Moneda:</span> {{ $x['moneda'] ?? '—' }}</div>
    <div><span class="label">Forma de pago:</span> {{ $x['forma_pago'] ?? '—' }}</div>
    <div><span class="label">Método de pago:</span> {{ $x['metodo_pago'] ?? '—' }}</div>
  </div>

  <!-- Tabla de conceptos -->
  <table class="concepts" style="margin:0 0 8pt;">
    <thead>
      <tr>
        <th>Cant.</th>
        <th>Clave</th>
        <th>Descripción</th>
        <th>V. Unitario</th>
        <th>Importe</th>
      </tr>
    </thead>
    <tbody>
      @if(!empty($x['conceptos']))
        @foreach($x['conceptos'] as $c)
          <tr>
            <td style="text-align:right;">{{ $c['cantidad'] ?? '' }}</td>
            <td>{{ $c['clave'] ?? '' }}</td>
            <td>{{ $c['descripcion'] ?? '' }}</td>
            <td style="text-align:right;">{{ isset($c['valor_unitario']) ? number_format((float)$c['valor_unitario'], 2) : '' }}</td>
            <td style="text-align:right;">{{ isset($c['importe']) ? number_format((float)$c['importe'], 2) : '' }}</td>
          </tr>
        @endforeach
      @else
        @foreach($o->items as $it)
          <tr>
            <td style="text-align:right;">{{ $it->cantidad_real }}</td>
            <td>—</td>
            <td>{{ $it->tamano ? ('Tamaño: '.$it->tamano) : ($it->descripcion ?? '') }}</td>
            <td style="text-align:right;">{{ number_format((float)($it->precio_unitario ?? 0),2) }}</td>
            <td style="text-align:right;">{{ number_format((float)($it->precio_unitario ?? 0) * (float)($it->cantidad_real ?? 0),2) }}</td>
          </tr>
        @endforeach
      @endif
    </tbody>
  </table>

  <!-- Totales a la derecha -->
  @php
    $subtotal = (isset($x['subtotal']) && is_numeric($x['subtotal'])) ? (float)$x['subtotal'] : (float)($o->subtotal ?? 0);
    $descuento = (isset($x['descuento']) && is_numeric($x['descuento'])) ? (float)$x['descuento'] : null;
    $iva = (isset($x['impuestos']['total_trasladados']) && is_numeric($x['impuestos']['total_trasladados'])) ? (float)$x['impuestos']['total_trasladados'] : (float)($o->iva ?? 0);
    $ret = (isset($x['impuestos']['total_retenciones']) && is_numeric($x['impuestos']['total_retenciones'])) ? (float)$x['impuestos']['total_retenciones'] : null;
    $total = (isset($x['total']) && is_numeric($x['total'])) ? (float)$x['total'] : (float)$f->total;
  @endphp
  <div class="totals">
    <div></div>
    <div class="totbox">
      <table>
        <tbody>
          <tr><td style="text-align:left;">Subtotal</td><td>${{ number_format($subtotal,2) }}</td></tr>
          @if(!is_null($descuento))
            <tr><td style="text-align:left;">Descuento</td><td>-${{ number_format($descuento,2) }}</td></tr>
          @endif
          <tr><td style="text-align:left;">IVA</td><td>${{ number_format($iva,2) }}</td></tr>
          @if(!is_null($ret))
            <tr><td style="text-align:left;">Retenciones</td><td>-${{ number_format($ret,2) }}</td></tr>
          @endif
          <tr><td style="text-align:left;">Total</td><td>${{ number_format($total,2) }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Timbre/UUID y certificados -->
  <div class="stamp" style="margin:0 0 6pt;">
    <div class="head">Timbre y certificación</div>
    <table style="width:100%; border-collapse:collapse; border:0;">
      <tr>
        <td style="vertical-align:top; width:70%; border:0;">
          <div class="text"><span class="label">UUID:</span> {{ $x['uuid'] ?? ($f->folio_externo ?? '—') }}</div>
          <div class="text"><span class="label">Fecha de timbrado:</span> {{ $x['fecha_timbrado'] ?? '—' }}</div>
          <div class="text"><span class="label">No. Certificado CFDI:</span> {{ $x['no_certificado'] ?? '—' }}</div>
          <div class="text"><span class="label">No. Certificado SAT:</span> {{ $x['no_cert_sat'] ?? '—' }}</div>
          @if(!empty($x['sello']))
            <div class="muted break"><span class="label">Sello CFDI:</span> {{ $x['sello'] }}</div>
          @endif
          @if(!empty($x['sello_sat']))
            <div class="muted break"><span class="label">Sello SAT:</span> {{ $x['sello_sat'] }}</div>
          @endif
        </td>
        <td style="vertical-align:top; width:15%; text-align:left; border:0; padding:0;">
          @if(!empty($x['sat_qr_png']))
            <img src="{{ $x['sat_qr_png'] }}" alt="QR SAT" style="width:100%; height:auto; display:block; margin:0;" />
          @elseif(!empty($x['sat_qr_svg_datauri']))
            <img src="{{ $x['sat_qr_svg_datauri'] }}" alt="QR SAT" style="width:100%; height:auto; display:block; margin:0;" />
          @endif
        </td>
      </tr>
    </table>
  </div>

  <!-- Nota legal -->
  <div class="muted">Este documento es una representación impresa de un CFDI.</div>
</body>
</html>

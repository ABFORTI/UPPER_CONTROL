@php
  use Carbon\Carbon;
  $o = $orden;
  Carbon::setLocale('es');
  $fechaLarga = $o->created_at ? $o->created_at->locale('es')->isoFormat('dddd, D [de] MMMM, YYYY') : '';
  // Totales
  $subtotal = 0.0;
  foreach ($o->items as $it) { $subtotal += (float)($it->subtotal ?? 0); }
  $ivaRate = 0.16; $iva = $subtotal * $ivaRate; $total = $subtotal + $iva;
  $pzas = $o->items->sum(function($it){ return (int)($it->cantidad_planeada ?? 0); });
  // Precio unitario único (si todos iguales)
  $uniquePus = collect($o->items)->pluck('precio_unitario')->filter(fn($v)=>$v!==null)->unique();
  $puLabel = $uniquePus->count() === 1 ? number_format($uniquePus->first(), 2) : '';
  $clienteName = optional(optional($o->solicitud)->cliente)->name;
  $areaNombre = optional($o->area)->nombre;
@endphp
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
<style>
  *{ font-family: DejaVu Sans, sans-serif; } body{ font-size:11.5px; color:#111; }
  .row{ display:flex; justify-content:space-between; align-items:center; }
  .col{ display:flex; flex-direction:column; }
  .mb4{ margin-bottom:4px; } .mb6{ margin-bottom:6px; } .mb8{ margin-bottom:8px; } .mb12{ margin-bottom:12px; } .mb16{ margin-bottom:16px; } .mb24{ margin-bottom:24px; }
  .title{ text-align:center; font-weight:700; font-size:16px; letter-spacing:.5px; }
  .muted{ color:#555; }
  .box{ border:1px solid #000; padding:6px 8px; font-size:11px; }
  .box-sm{ border:1px solid #000; padding:4px 6px; font-size:10px; }
  .label{ font-weight:700; font-size:11px; }
  table{ width:100%; border-collapse:collapse; } th,td{ border:1px solid #000; padding:6px; }
  th{ background:#efefef; text-align:center; font-weight:700; }
  .right{ text-align:right; } .center{ text-align:center; }
  .footer{ position:fixed; left:0; right:0; bottom:8px; text-align:center; font-size:10px; color:#666; }
  .nowrap{ white-space:nowrap; }
  .w-20{ width:20%; } .w-25{ width:25%; } .w-15{ width:15%; } .w-30{ width:30%; }
  .signature-line{ border-top:1px solid #000; height:28px; }
</style>
</head>
<body>

<!-- Encabezado -->
<div class="row mb8">
  <div>
    @php $logoPath = public_path('img/logo.png'); @endphp
    @if(file_exists($logoPath))
      <img src="{{ $logoPath }}" alt="Logo" style="height:48px">
    @else
      <div style="font-weight:700; font-size:14px;">UPPER LOGISTICS</div>
    @endif
  </div>
  <div style="text-align:right">
    <div class="box-sm" style="margin-bottom:6px;">FECHA:&nbsp; <span class="nowrap">{{ $fechaLarga }}</span></div>
    <div class="row" style="gap:6px; justify-content:flex-end;">
      <div class="box-sm">N. ORDEN:&nbsp; {{ $o->folio ?? ('OT-'.$o->id) }}</div>
      @php
        // Mostrar folio de la solicitud de donde provino la OT si existe; si no, fallback al folio OT o ID
        $folioSolicitud = optional($o->solicitud)->folio ?? null;
        $idLabel = $folioSolicitud ?: ($o->folio ?? ('OT-'.$o->id));
      @endphp
      <div class="box-sm">ID:&nbsp; {{ $idLabel }}</div>
    </div>
  </div>
  </div>

<div class="title mb8">ORDEN DE TRABAJO</div>

<!-- Línea info -->
<div class="row mb12">
  <div class="box" style="flex:1; margin-right:8px;">CENTRO: <strong>{{ optional($o->centro)->nombre ?? ($areaNombre ?? '—') }}</strong></div>
  <div class="box" style="width:200px;">CODIGO: <strong>{{ optional($o->servicio)->codigo ?? 'KPI 01' }}</strong></div>
  </div>

<!-- Tabla principal -->
<table class="mb12">
  <thead>
    <tr>
      <th class="w-15">MARCA</th>
      <th class="w-15">CLIENTE</th>
      <th class="w-15">CLAVE</th>
      <th class="w-30">DESCRIPCIÓN DEL PRODUCTO</th>
  <th class="w-25">CENTRO OPERATIVO</th>
    </tr>
  </thead>
  <tbody>
  @foreach($o->items as $it)
    <tr>
      <td class="center">&nbsp;</td>
      <td class="center">{{ $clienteName ?? '—' }}</td>
      <td class="center">{{ $it->tamano ? strtoupper($it->tamano) : '—' }}</td>
      <td>{{ $it->descripcion ?: ($o->descripcion_general ?: '—') }}</td>
  <td class="center">{{ optional($o->centro)->nombre ?? ($areaNombre ?? '—') }}</td>
    </tr>
  @endforeach
  @if($o->items->count() === 0)
    <tr>
      <td class="center" colspan="5">Sin items</td>
    </tr>
  @endif
  </tbody>
  </table>

<!-- Descripción del proceso y precio pza -->
<table class="mb12">
  <tr>
  <td style="width:75%;"><span class="label">DESCRIPCIÓN DEL PROCESO</span><br>{{ optional($o->servicio)->nombre ?? ($o->descripcion_general ?: '—') }}</td>
    <td style="width:25%;" class="right"><span class="label">PRECIO PZA $</span><br><span style="font-size:14px; font-weight:700;">{{ $puLabel }}</span></td>
  </tr>
  </table>

<!-- Totales compactos -->
<table class="mb16">
  <tr>
    <td class="label" style="width:60%;">TOTAL:</td>
    <td class="center" style="width:20%;"><strong>{{ number_format($pzas) }}</strong> PZS</td>
    <td class="right" style="width:20%;"><strong>$ {{ number_format($total,2) }}</strong></td>
  </tr>
  </table>

<!-- Control del proceso -->
@php
  $fechaInicio = $o->created_at ? Carbon::parse($o->created_at) : null;
  // Fecha de término: preferir la fecha persistida en la orden (fecha_completada).
  // Si no existe, intentar recuperar la fecha del avance que causó la completación.
  $fechaFin = null;
  if (!empty($o->fecha_completada)) {
    try { $fechaFin = Carbon::parse($o->fecha_completada); } catch (\Throwable $e) { $fechaFin = null; }
  }
  if (!$fechaFin) {
    try {
      // Cantidad total necesaria para completar (piezas planeadas)
      $needed = (int)($pzas ?? $o->items->sum(fn($it)=>(int)($it->cantidad_planeada ?? 0)));
      if ($needed > 0) {
        $cumulative = 0;
        $avances = \App\Models\Avance::where('id_orden', $o->id)->orderBy('created_at')->get();
        foreach ($avances as $av) {
          $cumulative += (int)($av->cantidad ?? 0);
          if ($cumulative >= $needed) {
            $fechaFin = Carbon::parse($av->created_at);
            break;
          }
        }
      }
    } catch (\Throwable $e) {
      $fechaFin = null;
    }
  }
@endphp
<div class="label mb6">CONTROL DEL PROCESO</div>
<table class="mb16">
  <tr>
    <td class="center" style="width:15%;">FECHA</td>
    <td class="center" style="width:35%;">{{ $fechaInicio ? $fechaInicio->format('n/j/Y') : '—' }}</td>
    <td class="center" style="width:15%;">FECHA</td>
    <td class="center" style="width:35%;">{{ $fechaFin ? $fechaFin->format('n/j/Y') : '—' }}</td>
  </tr>
  <tr>
    <td class="center">HORA INICIO</td>
    <td class="center">{{ $fechaInicio ? $fechaInicio->format('g:i a') : '—' }}</td>
    <td class="center">HORA DE TÉRMINO</td>
    <td class="center">{{ $fechaFin ? $fechaFin->format('g:i a') : '—' }}</td>
  </tr>
  </table>

<!-- Firmas -->
<table>
  <tr>
    <td style="width:33%; padding:10px;">
      <div class="signature-line"></div>
      <div class="center">NOMBRE/FIRMA</div>
  <div class="center" style="font-weight:700;">{{ optional($o->teamLeader)->name ?? '—' }}</div>
      <div class="center">{{ $fechaInicio ? $fechaInicio->format('n/j/Y') : '' }} &nbsp; {{ $fechaInicio ? $fechaInicio->format('g:i a') : '' }}</div>
    </td>
    <td style="width:33%; padding:10px;">
      <div class="signature-line"></div>
      <div class="center">SOLICITANTE</div>
      <div class="center" style="font-weight:700;">{{ $clienteName ?? '—' }}</div>
      <div class="center">{{ $fechaFin ? $fechaFin->format('n/j/Y') : '' }} &nbsp; {{ $fechaFin ? $fechaFin->format('g:i a') : '' }}</div>
    </td>
    <td style="width:33%; padding:10px;">
      <div class="signature-line"></div>
      <div class="center">AUTORIZA</div>
      <div class="center" style="font-weight:700;">{{ $o->aprobado_por?->name ?? '—' }}</div>
      <div class="center">{{ $fechaFin ? $fechaFin->format('n/j/Y') : '' }}</div>
    </td>
  </tr>
  </table>

<div class="footer">Upper Logistics — {{ now()->format('Y') }}</div>
</body>
</html>

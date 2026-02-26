@php
  use Carbon\Carbon;
  $o = $orden;
  Carbon::setLocale('es');
  $fechaLarga = $o->created_at ? $o->created_at->locale('es')->isoFormat('dddd, D [de] MMMM, YYYY') : '';
  
  // Determinar si es multi-servicio
  $esMultiServicio = $o->otServicios && $o->otServicios->count() > 1;
  
  // Datos generales
  $clienteName = optional(optional($o->solicitud)->cliente)->name;
  $marcaNombre = optional(optional($o->solicitud)->marca)->nombre;
  $areaNombre = optional($o->area)->nombre;
  $centroNombre = optional($o->centro)->nombre ?? $areaNombre ?? '—';
  
  // Calcular totales (desde otServicios si es multi, o desde items tradicionales)
  if ($esMultiServicio || ($o->otServicios && $o->otServicios->count() === 1)) {
    $subtotal = $o->otServicios->sum('subtotal');
  } else {
    $subtotal = $o->items->sum('subtotal');
  }
  $ivaRate = 0.16;
  $iva = $subtotal * $ivaRate;
  $total = $subtotal + $iva;
  
  // Servicios adicionales
  $serviciosAdicionales = $o->otServicios ? $o->otServicios->where('origen', 'ADICIONAL') : collect();
  
  // Fecha de inicio y término
  $fechaInicio = $o->created_at ? Carbon::parse($o->created_at) : null;
  $fechaFin = null;
  if (!empty($o->fecha_completada)) {
    try { $fechaFin = Carbon::parse($o->fecha_completada); } catch (\Throwable $e) { $fechaFin = null; }
  }
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Orden de Trabajo - {{ $o->folio ?? ('OT-'.$o->id) }}</title>
  <style>
    * { 
      margin: 0; 
      padding: 0; 
      box-sizing: border-box; 
      font-family: 'DejaVu Sans', Arial, sans-serif; 
    }
    body { 
      font-size: 10px; 
      color: #222; 
      line-height: 1.4;
      padding: 15px;
    }
    .page-break { 
      page-break-after: always; 
    }
    
    /* Header */
    .header {
      display: table;
      width: 100%;
      margin-bottom: 10px;
      border-bottom: 2px solid #1e40af;
      padding-bottom: 8px;
    }
    .header-left, .header-right {
      display: table-cell;
      vertical-align: top;
    }
    .header-left {
      width: 50%;
    }
    .header-right {
      width: 50%;
      text-align: right;
    }
    .logo {
      height: 45px;
      max-width: 200px;
    }
    .company-name {
      font-size: 16px;
      font-weight: bold;
      color: #1e40af;
    }
    .doc-title {
      font-size: 18px;
      font-weight: bold;
      color: #1e40af;
      text-align: center;
      margin: 8px 0;
      letter-spacing: 1px;
    }
    .badge {
      display: inline-block;
      background: #fbbf24;
      color: #78350f;
      padding: 3px 8px;
      border-radius: 3px;
      font-size: 9px;
      font-weight: bold;
      margin-left: 10px;
    }
    
    /* Info boxes */
    .info-grid {
      display: table;
      width: 100%;
      margin-bottom: 10px;
    }
    .info-row {
      display: table-row;
    }
    .info-cell {
      display: table-cell;
      padding: 5px 8px;
      border: 1px solid #d1d5db;
      background: #f9fafb;
      font-size: 9px;
    }
    .info-cell strong {
      color: #1e40af;
      font-weight: bold;
    }
    .info-label {
      font-weight: bold;
      color: #4b5563;
      font-size: 8px;
      text-transform: uppercase;
    }
    
    /* Tables */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 10px;
    }
    th {
      background: #e5e7eb;
      color: #1f2937;
      font-weight: bold;
      font-size: 9px;
      padding: 6px 5px;
      text-align: left;
      border: 1px solid #9ca3af;
    }
    td {
      padding: 5px;
      border: 1px solid #d1d5db;
      font-size: 9px;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-bold { font-weight: bold; }
    
    /* Section titles */
    .section-title {
      font-size: 11px;
      font-weight: bold;
      color: #1e40af;
      margin: 12px 0 6px 0;
      padding-bottom: 3px;
      border-bottom: 1px solid #93c5fd;
    }
    
    /* Service block */
    .service-block {
      margin-bottom: 12px;
      padding: 8px;
      border: 1px solid #d1d5db;
      background: #f9fafb;
      border-radius: 4px;
    }
    .service-header {
      background: #dbeafe;
      padding: 6px 8px;
      margin: -8px -8px 8px -8px;
      border-bottom: 1px solid #93c5fd;
      border-radius: 3px 3px 0 0;
    }
    .service-name {
      font-size: 11px;
      font-weight: bold;
      color: #1e40af;
    }
    .service-summary {
      display: table;
      width: 100%;
      margin-bottom: 8px;
    }
    .summary-item {
      display: table-cell;
      padding: 4px 6px;
      border: 1px solid #d1d5db;
      background: #ffffff;
      text-align: center;
      font-size: 8px;
    }
    .summary-item strong {
      display: block;
      font-size: 11px;
      color: #1e40af;
      margin-top: 2px;
    }
    
    /* Totals */
    .totals-box {
      float: right;
      width: 45%;
      margin-top: 8px;
      margin-bottom: 12px;
    }
    .totals-row {
      display: table;
      width: 100%;
      border-collapse: collapse;
    }
    .totals-label, .totals-value {
      display: table-cell;
      padding: 5px 8px;
      border: 1px solid #d1d5db;
      font-size: 10px;
    }
    .totals-label {
      background: #f3f4f6;
      font-weight: bold;
      width: 60%;
    }
    .totals-value {
      text-align: right;
      width: 40%;
    }
    .totals-final {
      background: #dbeafe;
      font-weight: bold;
      font-size: 11px;
      color: #1e40af;
    }
    
    /* Signatures */
    .signatures {
      margin-top: 20px;
      display: table;
      width: 100%;
    }
    .signature-cell {
      display: table-cell;
      width: 33.33%;
      padding: 8px;
      text-align: center;
      vertical-align: bottom;
    }
    .signature-line {
      border-top: 1px solid #000;
      margin-bottom: 5px;
      height: 40px;
    }
    .signature-label {
      font-size: 8px;
      font-weight: bold;
      text-transform: uppercase;
      color: #4b5563;
      margin-bottom: 2px;
    }
    .signature-name {
      font-size: 9px;
      font-weight: bold;
      color: #1f2937;
    }
    .signature-date {
      font-size: 8px;
      color: #6b7280;
    }
    
    /* Footer */
    .footer {
      position: fixed;
      bottom: 10px;
      left: 0;
      right: 0;
      text-align: center;
      font-size: 8px;
      color: #9ca3af;
    }
    
    /* Utilities */
    .mb-1 { margin-bottom: 5px; }
    .mb-2 { margin-bottom: 10px; }
    .mt-1 { margin-top: 5px; }
    .mt-2 { margin-top: 10px; }
    .no-items {
      text-align: center;
      padding: 15px;
      color: #6b7280;
      font-style: italic;
      background: #f9fafb;
    }
    .note {
      font-size: 8px;
      color: #6b7280;
      font-style: italic;
      margin-top: 5px;
    }
  </style>
</head>
<body>

{{-- ENCABEZADO --}}
<div class="header">
  <div class="header-left">
    @php $logoPath = public_path('img/logo.png'); @endphp
    @if(file_exists($logoPath))
      <img src="{{ $logoPath }}" alt="Logo" class="logo">
    @else
      <div class="company-name">UPPER LOGISTICS</div>
    @endif
  </div>
  <div class="header-right">
    <div style="font-size: 8px; color: #6b7280; margin-bottom: 3px;">{{ $fechaLarga }}</div>
    <div style="font-size: 10px; margin-bottom: 2px;">
      <strong>OT:</strong> {{ $o->folio ?? ('OT-'.$o->id) }}
    </div>
    <div style="font-size: 9px; color: #6b7280;">
      <strong>ID:</strong> {{ optional($o->solicitud)->folio ?? ($o->folio ?? $o->id) }}
    </div>
  </div>
</div>

<div class="doc-title">
  ORDEN DE TRABAJO
  @if($esMultiServicio)
    <span class="badge">MULTI-SERVICIO</span>
  @endif
</div>

{{-- DATOS GENERALES --}}
<div class="info-grid mb-2">
  <div class="info-row">
    <div class="info-cell" style="width: 25%;">
      <div class="info-label">Cliente</div>
      <strong>{{ $clienteName ?? '—' }}</strong>
    </div>
    <div class="info-cell" style="width: 25%;">
      <div class="info-label">Marca</div>
      <strong>{{ $marcaNombre ?? '—' }}</strong>
    </div>
    <div class="info-cell" style="width: 25%;">
      <div class="info-label">Centro Operativo</div>
      <strong>{{ $centroNombre }}</strong>
    </div>
    <div class="info-cell" style="width: 25%;">
      <div class="info-label">Código</div>
      <strong>{{ optional($o->servicio)->codigo ?? 'KPI 01' }}</strong>
    </div>
  </div>
  <div class="info-row">
    <div class="info-cell" style="width: 50%;">
      <div class="info-label">Fecha Creación</div>
      <strong>{{ $fechaInicio ? $fechaInicio->format('d/m/Y H:i') : '—' }}</strong>
    </div>
    <div class="info-cell" style="width: 50%;">
      <div class="info-label">Team Leader</div>
      <strong>{{ optional($o->teamLeader)->name ?? '—' }}</strong>
    </div>
  </div>
</div>

{{-- TABLA DE ITEMS --}}
<div class="section-title">Ítems de la Orden</div>
@if($o->items && $o->items->count() > 0)
  <table>
    <thead>
      <tr>
        <th style="width: 15%;">Clave</th>
        <th style="width: 45%;">Descripción</th>
        <th style="width: 15%;" class="text-center">Cantidad</th>
        <th style="width: 25%;">Observaciones</th>
      </tr>
    </thead>
    <tbody>
      @foreach($o->items as $item)
        <tr>
          <td class="text-center">{{ $item->tamano ? strtoupper($item->tamano) : ($item->sku ?? '—') }}</td>
          <td>{{ $item->descripcion ?: ($o->descripcion_general ?: '—') }}</td>
          <td class="text-center text-bold">{{ number_format($item->cantidad_planeada ?? 0) }}</td>
          <td style="font-size: 8px; color: #6b7280;">{{ $item->marca ?? '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@else
  <div class="no-items">Sin ítems registrados</div>
@endif

{{-- SERVICIOS DE LA OT --}}
@if($esMultiServicio || ($o->otServicios && $o->otServicios->count() === 1))
  <div class="section-title">Servicios de la OT</div>
  
  @foreach($o->otServicios->where('origen', 'SOLICITADO') as $otServicio)
    @php
      $servicio = $otServicio->servicio;
      $totalesServicio = $otServicio->calcularTotales();
      $solicitado = $totalesServicio['solicitado'] ?? $totalesServicio['planeado'];
      $extra = $totalesServicio['extra'] ?? 0;
      $totalCobrable = $totalesServicio['total_cobrable'] ?? $totalesServicio['total'];
      $completado = $totalesServicio['completado'];
      $faltantesReg = $totalesServicio['faltantes'];
      $pendiente = $totalesServicio['pendiente'];
      $extrasAuditables = $otServicio->items
        ->flatMap(fn($it) => $it->ajustes ?? collect())
        ->where('tipo', 'extra')
        ->sortBy('created_at');
    @endphp
    
    <div class="service-block">
      <div class="service-header">
        <div class="service-name">
          {{ $servicio->nombre ?? 'Servicio' }}
          <span style="font-size: 9px; font-weight: normal; color: #4b5563;">
            ({{ $servicio->codigo ?? '—' }})
          </span>
        </div>
        <div style="font-size: 8px; color: #4b5563; margin-top: 2px;">
          <strong>Cantidad Planeada:</strong> {{ number_format($otServicio->cantidad) }} 
          | <strong>Precio Base Normal:</strong> ${{ number_format($otServicio->precio_unitario, 2) }} MXN
          | <strong>Subtotal:</strong> ${{ number_format($otServicio->subtotal, 2) }} MXN
        </div>
      </div>
      
      {{-- Mini-resumen del servicio --}}
      <div class="service-summary mb-1">
        <div class="summary-item">
          <div class="info-label">Solicitado</div>
          <strong>{{ number_format($solicitado) }}</strong>
        </div>
        <div class="summary-item">
          <div class="info-label">Extra</div>
          <strong style="color: #b45309;">{{ number_format($extra) }}</strong>
        </div>
        <div class="summary-item">
          <div class="info-label">Faltantes</div>
          <strong style="color: #dc2626;">{{ number_format($faltantesReg) }}</strong>
        </div>
        <div class="summary-item">
          <div class="info-label">Total Cobrable</div>
          <strong style="color: #1d4ed8;">{{ number_format($totalCobrable) }}</strong>
        </div>
        <div class="summary-item">
          <div class="info-label">Completado</div>
          <strong style="color: #059669;">{{ number_format($completado) }}</strong>
        </div>
        <div class="summary-item">
          <div class="info-label">Pendiente</div>
          <strong style="color: #f59e0b;">{{ number_format($pendiente) }}</strong>
        </div>
      </div>

      @if($extrasAuditables->count() > 0)
        <div style="font-size: 9px; font-weight: bold; color: #4b5563; margin: 8px 0 4px;">
          Extras registrados (auditoría):
        </div>
        <table>
          <thead>
            <tr>
              <th style="width: 20%;">Detalle</th>
              <th style="width: 10%;" class="text-center">Cantidad</th>
              <th style="width: 40%;">Motivo</th>
              <th style="width: 15%;">Usuario</th>
              <th style="width: 15%;">Fecha/Hora</th>
            </tr>
          </thead>
          <tbody>
            @foreach($extrasAuditables as $ext)
              <tr>
                <td>{{ $ext->detalle->descripcion_item ?? 'Detalle' }}</td>
                <td class="text-center text-bold">{{ number_format($ext->cantidad) }}</td>
                <td>{{ $ext->motivo ?: '—' }}</td>
                <td>{{ optional($ext->user)->name ?? '—' }}</td>
                <td>{{ $ext->created_at ? $ext->created_at->format('d/m/Y h:i a') : '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
      
      {{-- Tabla de avances/segmentos --}}
      @if($otServicio->avances && $otServicio->avances->count() > 0)
        <div style="font-size: 9px; font-weight: bold; color: #4b5563; margin-bottom: 4px;">
          Segmentos / Avances Registrados:
        </div>
        <table>
          <thead>
            <tr>
              <th style="width: 15%;">Tipo Tarifa</th>
              <th style="width: 15%;" class="text-center">Cantidad</th>
              <th style="width: 15%;" class="text-right">P.U.</th>
              <th style="width: 15%;" class="text-right">Subtotal</th>
              <th style="width: 20%;">Usuario</th>
              <th style="width: 20%;">Fecha/Hora</th>
            </tr>
          </thead>
          <tbody>
            @foreach($otServicio->avances as $avance)
              @php
                $subtotalAvance = $avance->cantidad_registrada * ($avance->precio_unitario_aplicado ?? 0);
                $tarifa = $avance->tarifa ?? 'NORMAL';
                $tarifaColor = match(strtoupper($tarifa)) {
                  'EXTRA' => '#f59e0b',
                  'FIN_DE_SEMANA' => '#8b5cf6',
                  default => '#059669'
                };
              @endphp
              <tr>
                <td class="text-center">
                  <span style="color: {{ $tarifaColor }}; font-weight: bold;">
                    {{ strtoupper($tarifa) }}
                  </span>
                </td>
                <td class="text-center text-bold">{{ number_format($avance->cantidad_registrada) }}</td>
                <td class="text-right">${{ number_format($avance->precio_unitario_aplicado ?? 0, 2) }}</td>
                <td class="text-right text-bold">${{ number_format($subtotalAvance, 2) }}</td>
                <td>{{ optional($avance->createdBy)->name ?? '—' }}</td>
                <td>{{ $avance->created_at ? $avance->created_at->format('d/m/Y h:i a') : '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <div class="no-items" style="padding: 8px;">
          No se han registrado avances para este servicio
        </div>
      @endif
    </div>
  @endforeach
@endif

{{-- SERVICIOS ADICIONALES --}}
@if($serviciosAdicionales->count() > 0)
  <div class="section-title">Servicios Adicionales Agregados</div>
  <table>
    <thead>
      <tr>
        <th style="width: 30%;">Servicio</th>
        <th style="width: 15%;" class="text-center">Fecha/Hora</th>
        <th style="width: 20%;">Agregado por</th>
        <th style="width: 35%;">Justificación</th>
      </tr>
    </thead>
    <tbody>
      @foreach($serviciosAdicionales as $adicional)
        <tr>
          <td><strong>{{ optional($adicional->servicio)->nombre ?? 'Servicio Adicional' }}</strong></td>
          <td class="text-center">{{ $adicional->created_at ? $adicional->created_at->format('d/m/Y h:i a') : '—' }}</td>
          <td>{{ optional($adicional->addedBy)->name ?? '—' }}</td>
          <td style="font-size: 8px;">{{ $adicional->nota ?? 'Sin justificación' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@else
  <div style="margin-top: 8px; font-size: 9px; color: #6b7280; font-style: italic;">
    No se agregaron servicios adicionales a esta orden
  </div>
@endif

{{-- TOTALES GENERALES --}}
<div class="totals-box">
  <div class="totals-row">
    <div class="totals-label">Subtotal</div>
    <div class="totals-value">${{ number_format($subtotal, 2) }} MXN</div>
  </div>
  <div class="totals-row">
    <div class="totals-label">IVA (16%)</div>
    <div class="totals-value">${{ number_format($iva, 2) }} MXN</div>
  </div>
  <div class="totals-row">
    <div class="totals-label totals-final">Total</div>
    <div class="totals-value totals-final">${{ number_format($total, 2) }} MXN</div>
  </div>
</div>

<div style="clear: both;"></div>

@if($esMultiServicio)
  <div class="note">
    * Totales calculados con base en los avances registrados por tipo de tarifa (NORMAL, EXTRA, FIN_DE_SEMANA)
  </div>
@endif

{{-- CONTROL DEL PROCESO --}}
<div class="section-title" style="margin-top: 15px;">Control del Proceso</div>
<table class="mb-2">
  <tr>
    <td style="width: 25%; background: #f3f4f6; font-weight: bold;">Fecha Inicio</td>
    <td style="width: 25%;">{{ $fechaInicio ? $fechaInicio->format('d/m/Y') : '—' }}</td>
    <td style="width: 25%; background: #f3f4f6; font-weight: bold;">Fecha Término</td>
    <td style="width: 25%;">{{ $fechaFin ? $fechaFin->format('d/m/Y') : '—' }}</td>
  </tr>
  <tr>
    <td style="background: #f3f4f6; font-weight: bold;">Hora Inicio</td>
    <td>{{ $fechaInicio ? $fechaInicio->format('h:i a') : '—' }}</td>
    <td style="background: #f3f4f6; font-weight: bold;">Hora Término</td>
    <td>{{ $fechaFin ? $fechaFin->format('h:i a') : '—' }}</td>
  </tr>
</table>

{{-- FIRMAS --}}
<div class="signatures">
  <div class="signature-cell">
    <div class="signature-line"></div>
    <div class="signature-label">Nombre / Firma</div>
    <div class="signature-name">{{ optional($o->teamLeader)->name ?? '—' }}</div>
    <div class="signature-date">
      {{ $fechaInicio ? $fechaInicio->format('d/m/Y h:i a') : '' }}
    </div>
  </div>
  <div class="signature-cell">
    <div class="signature-line"></div>
    <div class="signature-label">Solicitante</div>
    <div class="signature-name">{{ $clienteName ?? '—' }}</div>
    <div class="signature-date">
      {{ $fechaFin ? $fechaFin->format('d/m/Y h:i a') : '' }}
    </div>
  </div>
  <div class="signature-cell">
    <div class="signature-line"></div>
    <div class="signature-label">Autoriza</div>
    @php
      $aprobador = $o->aprobaciones->where('resultado', 'aprobada')->first();
      $nombreAprobador = $aprobador ? optional($aprobador->usuario)->name : '—';
    @endphp
    <div class="signature-name">{{ $nombreAprobador }}</div>
    <div class="signature-date">
      {{ $fechaFin ? $fechaFin->format('d/m/Y') : '' }}
    </div>
  </div>
</div>

<div class="footer">
  Upper Logistics © {{ now()->format('Y') }} | Documento generado el {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>

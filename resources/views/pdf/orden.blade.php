@php
  $o = $orden;
@endphp
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
<style>
  *{ font-family: DejaVu Sans, sans-serif; } body{ font-size:12px; }
  .row{ display:flex; justify-content:space-between; align-items:center; }
  .mb8{ margin-bottom:8px; } .mb16{ margin-bottom:16px; } .mb24{ margin-bottom:24px; }
  .badge{ padding:3px 6px; border:1px solid #999; border-radius:3px; }
  table{ width:100%; border-collapse:collapse; } th,td{ border:1px solid #ddd; padding:6px; }
  th{ background:#f3f3f3; text-align:left; }
  .right{ text-align:right; } .small{ font-size:11px; color:#666; }
  .footer{ position:fixed; left:0; right:0; bottom:10px; text-align:center; font-size:10px; color:#888; }
</style>
</head><body>

<div class="row mb16">
  <img src="{{ public_path('img/logo.png') }}" alt="Logo" style="height:40px">
  <div style="text-align:right">
    <div><strong>OT #{{ $o->id }}</strong></div>
    <div class="small">{{ $o->created_at->format('Y-m-d H:i') }}</div>
  </div>
</div>

<div class="mb8"><strong>Servicio:</strong> {{ $o->servicio->nombre ?? '—' }}</div>
<div class="mb8">
  <strong>Centro:</strong> {{ $o->centro->nombre ?? '—' }} &nbsp; | &nbsp;
  <strong>Team Leader:</strong> {{ $o->teamLeader->name ?? 'No asignado' }}
</div>
<div class="mb16">
  <strong>Estatus:</strong> <span class="badge">{{ $o->estatus }}</span>
  &nbsp; <strong>Calidad:</strong> <span class="badge">{{ $o->calidad_resultado }}</span>
</div>

<table class="mb24">
  <thead>
    <tr>
      <th>Descripción / Tamaño</th>
      <th class="right">Planeado</th>
      <th class="right">Real</th>
      <th class="right">P.U.</th>
      <th class="right">Subtotal</th>
    </tr>
  </thead>
  <tbody>
  @foreach($o->items as $it)
    <tr>
      <td>{{ $it->tamano ? ('Tamaño: '.$it->tamano) : $it->descripcion }}</td>
      <td class="right">{{ $it->cantidad_planeada }}</td>
      <td class="right">{{ $it->cantidad_real }}</td>
      <td class="right">{{ number_format($it->precio_unitario,2) }}</td>
      <td class="right">{{ number_format($it->subtotal,2) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>

@php
  $subtotal = 0;
  foreach ($o->items as $it) { $subtotal += (float)$it->subtotal; }
  $ivaRate = 0.16; $iva = $subtotal * $ivaRate; $total = $subtotal + $iva;
@endphp

<div class="row">
  <div class="small">
    Generado por Upper Control. Documento sin validez fiscal.
  </div>
  <div style="text-align:right">
    <div><strong>Subtotal:</strong> $ {{ number_format($subtotal,2) }}</div>
    <div><strong>IVA (16%):</strong> $ {{ number_format($iva,2) }}</div>
    <div><strong>Total:</strong> $ {{ number_format($total,2) }}</div>
  </div>
</div>

<div class="footer">Upper Logistics — {{ now()->format('Y') }}</div>
</body>
</html>

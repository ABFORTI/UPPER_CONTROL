@php $f = $factura; $o = $f->orden; @endphp
<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
  *{ font-family: DejaVu Sans, sans-serif; } body{ font-size:12px; }
  .row{ display:flex; justify-content:space-between; align-items:center; }
  .mb8{ margin-bottom:8px; } .mb16{ margin-bottom:16px; } .badge{ padding:3px 6px; border:1px solid #999; border-radius:3px; }
  table{ width:100%; border-collapse:collapse; } th,td{ border:1px solid #ddd; padding:6px; } th{ background:#f3f3f3; text-align:left; }
  .right{ text-align:right; } .footer{ position:fixed; left:0; right:0; bottom:10px; text-align:center; font-size:10px; color:#888; }
</style>
</head><body>

<div class="row mb16">
  <img src="{{ public_path('img/logo.png') }}" alt="Logo" style="height:40px">
  <div style="text-align:right">
    <div><strong>Factura #{{ $f->id }}</strong></div>
    <div class="badge">{{ strtoupper($f->estatus) }}</div>
    <div class="small">{{ $f->created_at->format('Y-m-d H:i') }}</div>
  </div>
</div>

<div class="mb8"><strong>OT:</strong> #{{ $o->id }} — {{ $o->servicio->nombre ?? '—' }}</div>
<div class="mb8"><strong>Centro:</strong> {{ $o->centro->nombre ?? '—' }}</div>
<div class="mb16"><strong>Folio externo:</strong> {{ $f->folio ?? '—' }}</div>

<table class="mb16">
  <thead>
    <tr>
      <th>Concepto</th><th class="right">Cantidad</th><th class="right">P.U.</th><th class="right">Subtotal</th>
    </tr>
  </thead>
  <tbody>
    @foreach($o->items as $it)
      <tr>
        <td>{{ $it->tamano ? ('Tamaño: '.$it->tamano) : $it->descripcion }}</td>
        <td class="right">{{ $it->cantidad_real }}</td>
        <td class="right">{{ number_format($it->precio_unitario,2) }}</td>
        <td class="right">{{ number_format($it->precio_unitario * $it->cantidad_real,2) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="row">
  <div></div>
  <div style="text-align:right">
    <div><strong>Total:</strong> $ {{ number_format($f->total,2) }}</div>
  </div>
</div>

<div class="footer">Documento informativo. Para efectos fiscales, consulte el CFDI timbrado.</div>
</body></html>

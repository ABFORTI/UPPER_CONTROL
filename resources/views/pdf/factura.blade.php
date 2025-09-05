<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body{ font-family: DejaVu Sans, sans-serif; font-size:12px; }
    .title{ font-size:18px; font-weight:bold; margin-bottom:6px }
    .muted{ color:#555 }
    table{ width:100%; border-collapse: collapse; margin-top:10px }
    th,td{ border:1px solid #ddd; padding:6px; text-align:left }
    th{ background:#f5f5f5 }
    .right{ text-align:right }
  </style>
</head>
<body>
  <table style="border:none">
    <tr style="border:none">
      <td style="border:none">
        <div class="title">Factura</div>
        <div class="muted">#{{ $factura->id }}</div>
      </td>
      <td style="border:none; text-align:right">
        @if(!empty($empresa['logo']) && file_exists($empresa['logo']))
          <img src="{{ $empresa['logo'] }}" height="48">
        @endif
        <div>{{ $empresa['nombre'] }}</div>
      </td>
    </tr>
  </table>

  <p>
    <b>OT:</b> #{{ $factura->orden->id }} — {{ $factura->orden->servicio->nombre ?? '-' }}<br>
    <b>Centro:</b> {{ $factura->orden->centro->nombre ?? '-' }}<br>
    <b>Folio externo:</b> {{ $factura->folio ?? '—' }}<br>
    <b>Estatus:</b> {{ $factura->estatus }}
  </p>

  <table>
    <thead>
      <tr>
        <th>Concepto</th>
        <th class="right">Importe</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Servicios de {{ $factura->orden->servicio->nombre ?? '—' }} (OT #{{ $factura->orden->id }})</td>
        <td class="right">{{ number_format($factura->total,2) }}</td>
      </tr>
    </tbody>
  </table>

  <p class="right"><b>Total:</b> {{ number_format($factura->total,2) }}</p>
</body>
</html>

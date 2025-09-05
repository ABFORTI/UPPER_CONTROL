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
        <div class="title">Orden de Trabajo</div>
        <div class="muted">OT #{{ $orden->id }}</div>
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
    <b>Servicio:</b> {{ $orden->servicio->nombre ?? '-' }}<br>
    <b>Centro:</b> {{ $orden->centro->nombre ?? '-' }}<br>
    <b>Team Leader:</b> {{ $orden->teamLeader->name ?? 'No asignado' }}<br>
    <b>Estatus:</b> {{ $orden->estatus }} |
    <b>Calidad:</b> {{ $orden->calidad_resultado }}
  </p>

  <table>
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
      @foreach($orden->items as $it)
        <tr>
          <td>{{ $it->tamano ? 'Tamaño: '.$it->tamano : $it->descripcion }}</td>
          <td class="right">{{ $it->cantidad_planeada }}</td>
          <td class="right">{{ $it->cantidad_real }}</td>
          <td class="right">{{ number_format($it->precio_unitario,2) }}</td>
          <td class="right">{{ number_format($it->subtotal,2) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <p class="right"><b>Total planeado:</b> {{ number_format($orden->total_planeado,0) }}
  &nbsp; | &nbsp; <b>Total real:</b> {{ number_format($orden->total_real,2) }}</p>
</body>
</html>

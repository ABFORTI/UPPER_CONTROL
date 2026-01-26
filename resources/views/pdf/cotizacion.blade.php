@php
  use Carbon\Carbon;

  /** @var \App\Models\Cotizacion $c */
  $c = $cotizacion;

  Carbon::setLocale('es');
  $fecha = $c->created_at ? $c->created_at->locale('es')->isoFormat('D [de] MMMM, YYYY') : '';

  $logoSrc = null;
  foreach (['logo.png','img/logo.png','images/logo.png'] as $cand) {
    $p = public_path($cand);
    if (file_exists($p)) {
      $mime = function_exists('mime_content_type') ? (mime_content_type($p) ?: 'image/png') : 'image/png';
      $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(@file_get_contents($p));
      break;
    }
  }

  $moneda = strtoupper((string)($c->currency ?? 'MXN'));
  $notes = trim((string)($c->notas ?? ''));
  $notes2 = trim((string)($c->notes ?? ''));
  $notesAll = trim($notes . ($notes && $notes2 ? "\n" : '') . $notes2);
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { size: Letter; margin: 14mm; }
    body { font-family: DejaVu Sans, sans-serif; color:#111; font-size: 10.5pt; }

    .bar { background:#1E1C8F; color:#fff; padding:10pt 12pt; font-weight:700; font-size:13pt; position:relative; padding-right:110pt; min-height:34pt; }
    .bar .logo { position:absolute; right:12pt; top:8pt; height:34pt; }

    .meta { margin-top:8pt; display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8pt; font-size:9.5pt; }
    .card { border:0.4pt solid #d6d6d6; padding:8pt; }
    .label { color:#2E3A59; font-weight:700; }
    .muted { color:#6C7A96; }

    table { width:100%; border-collapse:collapse; }
    th, td { border:0.4pt solid #d6d6d6; padding:6pt; font-size:9pt; vertical-align:top; }
    thead th { background:#F5F7FA; font-weight:700; text-align:center; }

    .right { text-align:right; }
    .center { text-align:center; }

    .totals { margin-top:10pt; display:grid; grid-template-columns: 60% 40%; gap:10pt; }
    .totbox td { font-size:10pt; }
    .totbox tr:last-child td { font-weight:700; }

    .pre { white-space:pre-wrap; }
  </style>
</head>
<body>
  <div class="bar">
    <span>Cotización</span>
    @if($logoSrc)
      <img class="logo" src="{{ $logoSrc }}" alt="Logo">
    @endif
  </div>

  <div class="meta">
    <div class="card">
      <div><span class="label">Folio:</span> {{ $c->folio ?? ('COT-' . $c->id) }}</div>
      <div><span class="label">Fecha:</span> {{ $fecha ?: '—' }}</div>
      <div><span class="label">Estatus:</span> <span class="muted">{{ strtoupper((string)$c->estatus) }}</span></div>
    </div>
    <div class="card">
      <div><span class="label">Cliente:</span> {{ $c->cliente?->name ?? '—' }}</div>
      <div><span class="label">Email:</span> {{ $c->cliente?->email ?? '—' }}</div>
      <div><span class="label">Moneda:</span> {{ $moneda }}</div>
    </div>
    <div class="card">
      <div><span class="label">Centro:</span> {{ $c->centro?->nombre ?? '—' }}</div>
      <div><span class="label">Centro de costos:</span> {{ $c->centroCosto?->nombre ?? '—' }}</div>
      <div><span class="label">Marca/Área:</span> {{ $c->marca?->nombre ?? '—' }} / {{ $c->area?->nombre ?? '—' }}</div>
    </div>
  </div>

  <div style="margin-top:10pt;">
    <table>
      <thead>
        <tr>
          <th style="width:30%;">Ítem</th>
          <th style="width:22%;">Servicio</th>
          <th style="width:10%;">Tamaño</th>
          <th style="width:10%;">Cantidad</th>
          <th style="width:10%;">P. Unit</th>
          <th style="width:18%;">Total</th>
        </tr>
      </thead>
      <tbody>
        @php $hasAnyRow = false; @endphp
        @foreach(($c->items ?? []) as $it)
          @php
            $svs = $it->servicios ?? collect();
            $svCount = method_exists($svs, 'count') ? $svs->count() : count((array)$svs);
          @endphp

          @if($svCount > 0)
            @foreach($svs as $idx => $s)
              @php $hasAnyRow = true; @endphp
              <tr>
                <td>
                  @if($idx === 0)
                    <div><strong>{{ $it->descripcion ?? '—' }}</strong></div>
                    <div class="muted">Cant. ítem: {{ $it->cantidad ?? '—' }}</div>
                    @if(!empty($it->notas))
                      <div class="muted pre">{{ $it->notas }}</div>
                    @endif
                  @endif
                </td>
                <td>{{ $s->servicio?->nombre ?? ($s->id_servicio ?? '—') }}</td>
                <td class="center">{{ $s->tamano ?? '—' }}</td>
                <td class="center">{{ $s->qty ?? $s->cantidad ?? '—' }}</td>
                <td class="right">{{ isset($s->precio_unitario) ? number_format((float)$s->precio_unitario, 2) : '' }}</td>
                <td class="right">{{ isset($s->total) ? number_format((float)$s->total, 2) : '' }}</td>
              </tr>
            @endforeach
          @else
            @php $hasAnyRow = true; @endphp
            <tr>
              <td>
                <div><strong>{{ $it->descripcion ?? '—' }}</strong></div>
                <div class="muted">Cant. ítem: {{ $it->cantidad ?? '—' }}</div>
                @if(!empty($it->notas))
                  <div class="muted pre">{{ $it->notas }}</div>
                @endif
              </td>
              <td class="center" colspan="5">Sin servicios</td>
            </tr>
          @endif
        @endforeach

        @if(!$hasAnyRow)
          <tr>
            <td class="center" colspan="6">Sin ítems</td>
          </tr>
        @endif
      </tbody>
    </table>
  </div>

  <div class="totals">
    <div>
      @if($notesAll)
        <div class="card">
          <div class="label" style="margin-bottom:4pt;">Notas</div>
          <div class="pre">{{ $notesAll }}</div>
        </div>
      @endif
    </div>
    <div class="card totbox">
      <table>
        <tbody>
          <tr><td style="text-align:left;"><span class="label">Subtotal</span></td><td class="right">{{ number_format((float)($c->subtotal ?? 0), 2) }}</td></tr>
          <tr><td style="text-align:left;"><span class="label">IVA</span></td><td class="right">{{ number_format((float)($c->iva ?? 0), 2) }}</td></tr>
          <tr><td style="text-align:left;"><span class="label">Total</span></td><td class="right">{{ number_format((float)($c->total ?? 0), 2) }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="muted" style="margin-top:10pt; font-size:8.5pt;">
    Generado: {{ now()->format('Y-m-d H:i') }}
  </div>
</body>
</html>

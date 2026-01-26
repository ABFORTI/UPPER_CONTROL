@php
    /** @var \App\Models\Cotizacion $cotizacion */
    $folio = $cotizacion->folio ?? ('#' . $cotizacion->id);
    $total = number_format((float)($cotizacion->total ?? 0), 2);
    $itemsCount = (int)($itemsCount ?? 0);
@endphp

@component('mail::message')
# Nueva cotización {{ $folio }}

Hola {{ $notifiable->name ?? 'cliente' }},

Se te ha enviado una nueva cotización.

@component('mail::panel')
**Resumen**

- **Folio:** {{ $folio }}
- **Ítems:** {{ $itemsCount }}
- **Total:** ${{ $total }}
@if(!empty($expiresAtIso))
- **Vence:** {{ \Carbon\Carbon::parse($expiresAtIso)->format('d/m/Y H:i') }}
@endif
@endcomponent

@component('mail::button', ['url' => $reviewUrl, 'color' => 'primary'])
Revisar y autorizar
@endcomponent

Si el botón no funciona, abre este enlace:
{{ $reviewUrl }}

Gracias,
**Upper Control**
@endcomponent

@props(['url'])
@php
    $brandName = trim($slot) ?: config('app.name', 'Upper Logistics');
    $logoUrl = asset('img/logo.png');
@endphp
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block; width: 100%;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center">
                        <div class="brand-block">
                            <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="logo">
                            <div class="brand-text">
                                <div class="brand-name">{{ $brandName }}</div>
                                <div class="brand-tagline">Operaciones confiables · Upper Logistics</div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </a>
    </td>
</tr>


@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
@else
<span style="font-size: 26px; font-weight: 700; color: #ffffff; letter-spacing: 1px; text-shadow: 0 2px 8px rgba(0,0,0,0.2);">
    🔧 {!! $slot !!}
</span>
@endif
</a>
</td>
</tr>


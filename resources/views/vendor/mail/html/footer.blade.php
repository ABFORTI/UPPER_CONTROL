@php
	$company = config('app.name', 'Upper Logistics');
	$from = config('mail.from.address');
@endphp
<tr>
	<td>
		<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
			<tr>
				<td class="content-cell" align="center">
					{{ Illuminate\Mail\Markdown::parse($slot) }}
					<p class="footer-contact">
						{{ $company }} · {{ $from }} · Atención 24/7
					</p>
					<p class="footer-contact">
						Este correo es informativo. Si no corresponde contigo, ignóralo o contáctanos.
					</p>
				</td>
			</tr>
		</table>
	</td>
</tr>

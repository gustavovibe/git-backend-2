<html>
<head>
	<title>Booking Confirmation</title>
</head>
<body>
<table border="0" cellpadding="0" cellspacing="0" style="max-width: 600px;margin:0 auto;width: 100%;">
	<tbody>
		<tr>
			<td align="left" style="max-width: 203px;" valign="middle"><img alt="" border="0" class="w203px" src="https://vibeadventures.be/images//logo.png" style="display: block; max-width: 203px; width: 100%;" width="203" /></td>
			<td style="width:20px">&nbsp;</td>
			<td align="right"><a href="{{ rtrim(config('frontend.url'), '/') }}/my-trips/order?order_id={{$order->booking_id}}" target="_blank" style="background-color:#ff6c0e;font-size:14px;font-weight:bold;line-height:37px;width:169px;color:#ffffff;border-radius:10px;display:inline-block;font-family:Canaro, sans-serif;text-align:center;text-decoration:none;-webkit-text-size-adjust:none;box-sizing:border-box;" >View booking</a></td>
		</tr>
	</tbody>
</table>

<table border="0" cellpadding="0" cellspacing="0" style="max-width: 600px;margin:0 auto;width: 100%;">
	<tbody>
		<tr>
			<td align="left" bgcolor="#ffffff" style="padding: 30px;" valign="top">
			<div align="center" style="padding: 20px 0px;" valign="middle">
				<span style="font-family: Canaro, sans-serif; font-size: 28px; color: #000000;line-height: 34px;">
				<strong>New <span style="color: #82CF45;">confirmed</span> booking</strong>
				</span>
			</div>
			</td>
		</tr>
	</tbody>
</table>

<table border="0" cellpadding="0" cellspacing="0" style="max-width: 600px;margin:0 auto;width: 100%;">
	<tbody>
		<tr>
			<td align="center" valign="top">
			<table border="0" cellpadding="0" cellspacing="0" style="text-align: center;">
				<tbody>
					<tr>
						<td style="font-family: Arial, sans-serif; font-weight: bold; font-size: 12px; color: #4f4f4f; line-height: 20px; text-transform: uppercase;">Booking number</td>
					</tr>
					<tr>
						<td style="font-family: Arial, sans-serif; font-weight: bold; font-size: 16px; color: #000000; line-height: 20px; letter-spacing: 3px;">{{ str_pad($order->booking_id, 6, '0', STR_PAD_LEFT) }}</td>
					</tr>
				</tbody>
			</table>
			</td>
			<td align="center" valign="top">
			<table border="0" cellpadding="0" cellspacing="0" style="text-align: center;">
				<tbody>
					<tr>
						<td style="font-family: Arial, sans-serif; font-weight: bold; font-size: 12px; color: #4f4f4f; line-height: 20px; text-transform: uppercase;">Source</td>
					</tr>
					<tr>
						<td style="font-family: Arial, sans-serif; font-weight: bold; font-size: 16px; color: #000000; line-height: 20px; text-transform: uppercase;">{{ $order->source ?? 'web' }}</td>
					</tr>
				</tbody>
			</table>
			</td>
			<td align="center" valign="top">
			<table border="0" cellpadding="0" cellspacing="0" style="text-align: center;">
				<tbody>
					<tr>
						<td style="font-family: Arial, sans-serif; font-weight: bold; font-size: 12px; color: #4f4f4f; line-height: 20px; text-transform: uppercase;">Booking status</td>
					</tr>
					<tr>
						<td align="center">
						@if ($order->booking_status !='confirmed')
						<table border="0" cellpadding="0" cellspacing="0" style="background: rgb(255,108,14,0.25); border-radius: 20px; width: 100px;">
							<tbody>
								<tr>
									<td align="center" style="padding: 2px 4px 2px 4px;"><img alt="" height="18" src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/sync.png" style="display: block;" width="18" /></td>
									<td style="font-family: Arial, sans-serif; font-size: 12px; color: #FF6C0E; font-weight: bold; text-decoration: none;">Pending</td>
								</tr>
							</tbody>
						</table>	
						@else
						<table border="0" cellpadding="0" cellspacing="0" style="background: #82cf45; border-radius: 20px; width: 100px;">
							<tbody>
								<tr>
									<td align="center" style="padding: 2px 4px 2px 4px;"><img alt="" height="18" src="https://blog.vibeadventures.com/wp-content/uploads/2025/06/tik.png" style="display: block;" width="18" /></td>
									<td style="font-family: Arial, sans-serif; font-size: 12px; color: #ffffff; font-weight: bold; text-decoration: none;">Confirmed</td>
								</tr>
							</tbody>
						</table>
						@endif
						</td>
					</tr>
				</tbody>
			</table>
			</td>
		</tr>
	</tbody>
</table>

<table border="0" cellpadding="0" cellspacing="0" style="max-width: 600px;margin:0 auto;width: 100%;margin-top:25px">
	<tbody>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 25px; color: #000000;">Travelers</span></td>
		</tr>
	</tbody>
</table>
@if ($order->travelers)
<div border="0" cellpadding="0" cellspacing="0" style="border-radius: 20px; border-width: 1px; border-color: #82cf45;border-style: solid; border-collapse: separate;width:100%;max-width:600px;margin: 25px auto auto;">
<div style="padding: 20px 30px;">
<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">
	<tbody>
		@foreach ($order->travelers as $traveler)
		<tr>
			<td align="left" valign="middle"><img alt="" border="0" class="w24px" src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/i1682235450.png" style="max-width: 24px; width: 100%;" width="24" /> <span style="font-family: Canaro, sans-serif; font-size: 16px; color: #000000;">{{ $traveler->title }} <b>{{ $traveler->name.' '.$traveler->last }}</b></span></td>
			<td align="right" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 16px; color: #000000;">{{ \Carbon\Carbon::parse($traveler->birth)->format('j M Y') }}</span></td>
		</tr>
		<tr style="height:10px">
		@endforeach	
	</tbody>
</table>
</div>
</div>
@endif

<table border="0" cellpadding="0" cellspacing="0" style="max-width: 600px;margin:0 auto;width: 100%;margin-top:25px">
	<tbody>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 25px; color: #000000;">Contacts</span></td>
		</tr>
	</tbody>
</table>

<div border="0" cellpadding="0" cellspacing="0" style="border-radius: 20px; border-width: 1px; border-color: #82cf45;border-style: solid; border-collapse: separate;width:100%;max-width:600px;margin: 25px auto auto;">
<div style="padding: 20px 30px;">
<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">
	<tbody>
		<tr>
			<td align="left" valign="middle"><img alt="" border="0" class="w24px" src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/i728805621.png" style="max-width: 24px; width: 100%;" width="24" /> 
			<span style="font-family: Canaro, sans-serif; font-size: 16px; color: #000000;">{{$order->user->phone}}</span>
			</td>
		</tr>
		<tr style="height:10px">
		</tr>
		<tr>
			<td align="left" valign="middle"><img alt="" border="0" class="w24px" src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/i1097817612.png" style="max-width: 24px; width: 100%;" width="24" /> 
			<span style="font-family: Canaro, sans-serif; font-size: 16px; color: #000000; text-decoration: none;">{{$order->user->email}}</span>
			</td>
		</tr>
	</tbody>
</table>
</div>
</div>

@if ($order->tour)

<table border="0" cellpadding="0" cellspacing="0" style="max-width: 600px;margin:0 auto;width: 100%;margin-top:25px">
	<tbody>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 25px; color: #000000;">Adventure summary</span></td>
			<td align="right" valign="middle">
			@if ($order->booking_status == 'confirmed') 	
				<a href="https://vibeadventures.be/api/boooking-summary-pdf?tour_id={{ $order->tour_id }}" style="font-size:14px;font-weight:bold;line-height:31px;width:171px;border: 1px solid #ff6c0e;color:#ff6c0e;border-radius:10px;display:inline-block;font-family:Canaro, sans-serif;text-align:center;text-decoration:none;-webkit-text-size-adjust:none;box-sizing:border-box;" target="_blank">Download itinerary</a>
			@endif	
			</td>
		</tr>
	</tbody>
</table>
<table border="0" cellpadding="0" cellspacing="0" style="padding: 20px 30px; border-radius: 20px; border-width: 1px; border-color: #82cf45;border-style: solid; border-collapse: separate;width:100%;max-width:600px;margin: 25px auto auto;">
	<tbody>
		<tr>
			<td align="left" style="width:150px;padding-right: 20px;" valign="middle">
				 @if ($order->tour->main_thumbnail)
				<img src="{{ $order->tour->main_thumbnail }}" style="width:100%;" />
				@endif
			</td>
			<td align="left" valign="top">
			<table>
				<tbody>
					<tr>
						<td><span style="font-family: Canaro, sans-serif; font-weight: bold; font-size: 15px; color: #82cf45;">{{ $order->tour->tour_name }}</span></td>
					</tr>
				</tbody>
			</table>

			<table>
				<tbody>
					<tr>
						<td width="24"><img border="0" height="18" src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/i196401944.png" style="display: block;" width="18" /></td>
						<td width="30"><span style="font-family: Canaro, sans-serif; font-size: 12px; color: #82cf45;">{{ $order->tour->ratings_overall }}</span></td>
						<td><span style="font-family: 'Interstate Light Cond', sans-serif; font-size: 12px; color: #9ca3af;">{{ $order->tour->reviews_count }} reviews </span></td>
					</tr>
				</tbody>
			</table>

			<table>
				<tbody>
					<tr>
						<td width="28"><img src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/i-1337963125.png" style="height: 26px;width: 26px;" /></td>
						<td style="min-width: 50px;"><span style="font-family: 'Interstate Light Cond', sans-serif; font-size: 12px; color: #000000;">Starts in: </span></td>
						<td style="min-width: 76px;"><span style="color: #9ca3af;font-family: 'Interstate Light Cond', sans-serif; font-size: 12px;">{{ $order->start_city . ',' . $order->origin }}</span></td>
						<td width="28"><img src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/i-1337963125.png" style="height: 26px;width: 26px;" /></td>
						<td style="min-width: 50px;"><span style="font-family: 'Interstate Light Cond', sans-serif; font-size: 12px; color: #000000;">Ends in: </span></td>
						<td style="min-width: 76px;"><span style="color: #9ca3af;font-family: 'Interstate Light Cond', sans-serif; font-size: 12px;">{{ $order->end_city . ',' . $order->f_destination }}</span></td>
					</tr>
				</tbody>
			</table>

			<table>
				<tbody>
					<tr>
						<td width="30"><img src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/i-1067799321.png" style="margin-left:-3px;height: 30px;width: 30px;" /></td>
						<td style="min-width: 50px;"><span style="font-family: 'Interstate Light Cond', sans-serif; font-size: 12px; color: #000000;">Starts on:</span></td>
						<td style="min-width: 72px;"><span style="color: #9ca3af;font-family: 'Interstate Light Cond', sans-serif; font-size: 12px;">{{ \Carbon\Carbon::parse($order->start)->format('M d, Y') }}</span></td>
						<td width="32"><img src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/i64381891.png" style="margin-left:1px;height: 28px;width: 28px;" /></td>
						<td style="min-width: 50px;"><span style="font-family: 'Interstate Light Cond', sans-serif; font-size: 12px; color: #000000;">Ends on:</span></td>
						<td style="min-width: 72px;"><span style="color: #9ca3af;font-family: 'Interstate Light Cond', sans-serif; font-size: 12px;">{{ \Carbon\Carbon::parse($order->end)->format('M d, Y') }}</span></td>
					</tr>
				</tbody>
			</table>

			<table>
				<tbody>
					<tr>
						<td width="30"><img border="0" src="https://blog.vibeadventures.com/wp-content/uploads/2025/05/i751044998.png" style="margin-left:-1px;height: 28px;width: 28px;" /></td>
						<td style="min-width: 50px;"><span style="font-family: 'Interstate Light Cond', sans-serif; font-size: 12px; color: #000000;">Rooms:</span></td>
						<td><span style="color: #9ca3af;font-family: 'Interstate Light Cond', sans-serif; font-size: 12px;">
							 @foreach ($order->passengers as $acc)
                                        <p>
                                            <span style="color: #82CF45;">
                                                {{ $acc['passengers'] }}
                                            </span>
                                            × {{ $acc['name'] }}
                                        </p>
                                    @endforeach
						</span></td>
					</tr>
				</tbody>
			</table>
			</td>
		</tr>
	</tbody>
</table>
@endif
@if (isset($order->attempt->duffel_res['data']['slices']))

<table border="0" cellpadding="0" cellspacing="0" style="max-width: 600px;margin:0 auto;width: 100%;margin-top:25px">
	<tbody>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 25px; color: #000000;">Flights summary</span></td>
			@if ($order->booking_status == 'confirmed')
			<td align="right" valign="middle"><a href="https://vibeadventures.be/api/get-tickets?orderId={{ $order->duffel_id }}" style="font-size:14px;font-weight:bold;line-height:31px;width:171px;border: 1px solid #ff6c0e;color:#ff6c0e;border-radius:10px;display:inline-block;font-family:Canaro, sans-serif;text-align:center;text-decoration:none;-webkit-text-size-adjust:none;box-sizing:border-box;" target="_blank">Download e-tickets</a></td>
			@endif
		</tr>
	</tbody>
</table>
<div border="0" cellpadding="0" cellspacing="0" style="border-radius: 20px; border-width: 1px; border-color: #82cf45;border-style: solid; border-collapse: separate;width:100%;max-width:600px;margin: 25px auto auto;">
<div style="padding: 20px 30px;">
@foreach ($order->attempt->duffel_res['data']['slices'] as $or)	
<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">
	<tbody>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 18px; color: #4f4f4f;">
			@if(isset($or['segments'][0]['origin']['city']['name'])  )
				<b>{{ $or['segments'][0]['origin']['city']['name'] }}</b>
				@else 
				<b>{{ $or['segments'][0]['origin']['city_name'] }}</b>
				@endif
				 → 
				@if(isset($or['segments'][0]['destination']['city_name'])  )
				<b>{{ $or['segments'][0]['destination']['city_name'] }}</b>
				@else 
				<b>{{ $or['segments'][0]['destination']['city']['name'] }}</b>
				@endif
			</span></td>
			<td align="right" valign="middle"><span style="font-family: 'Segoe UI', sans-serif; font-weight: bold; font-size: 14px; color: #000000;">{{ \Carbon\CarbonInterval::make($or['duration'])->format('%hh %im') }}</span></td>
		</tr>
	</tbody>
</table>
<table border="0" cellpadding="0" cellspacing="0" style="width:100%;padding:10px;margin-top:20px">
	<tbody>
		<tr>
			<td align="left" valign="middle">
				<span style="font-family: Canaro, sans-serif; font-size: 18px; color: #000000;">{{ \Carbon\Carbon::parse($or['segments'][0]['departing_at'])->format('H:i') }}</span><br />
				<span style="font-family: Canaro, sans-serif; font-size: 12px; color: #4f4f4f;">{{ \Carbon\Carbon::parse($or['segments'][0]['departing_at'])->format('M d, Y') }}</span>
			</td>
			<td align="right" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 14px; color: #82cf45;">
				@if(isset($or['segments'][0]['origin']['city']['name'])  )
				<b>{{ $or['segments'][0]['origin']['city']['name'] }}@endif</b>
				({{$or['segments'][0]['origin']['iata_code']}})· </span><span style="font-family: Canaro, sans-serif; color: #4f4f4f; text-decoration: none;">{{$or['segments'][0]['origin']['name']}}</span></td>
		</tr>
	</tbody>
</table>
<table border="0" cellpadding="0" cellspacing="0" style="width:100%;margin-top:0px;margin-bottom:10px;padding-left:10px;padding-right: 10px;">
	<tbody>
		<tr>
			<td align="left" valign="middle">
			<span style="font-family: Canaro, sans-serif; font-size: 14px; color: #82cf45;">
				{{ \Carbon\CarbonInterval::make($or['duration'])->format('%hh %im') }}
			</span></td>
			<td align="right" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 14px;"><strong>{{$or['segments'][0]['operating_carrier']['name']}}</strong> {{$or['segments'][0]['operating_carrier_flight_number']}}</span></td>
		</tr>
	</tbody>
</table>
<table border="0" cellpadding="0" cellspacing="0" style="width:100%;padding:10px;margin-top:20px">
	<tbody>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 18px; color: #000000;">{{ \Carbon\Carbon::parse($or['segments'][0]['arriving_at'])->format('H:i') }}</span><br />
			<span style="font-family: Canaro, sans-serif; font-size: 12px; color: #4f4f4f;">{{ \Carbon\Carbon::parse($or['segments'][0]['arriving_at'])->format('M d, Y') }}</span></td>
			<td align="right" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 14px; color: #82cf45;">
				@if(isset($or['segments'][0]['destination']['city']['name'])  )
				<b>{{ $or['segments'][0]['destination']['city']['name'] }}@endif</b>
				({{$or['segments'][0]['destination']['iata_code']}})· </span><span style="font-family: Canaro, sans-serif; color: #4f4f4f; text-decoration: none;">{{$or['segments'][0]['destination']['name']}}</span></td>
		</tr>
	</tbody>
</table>

{{-- === HERE: After the very first slice, inject your extra table === --}}
    @if ($loop->first)
        <table border="0" cellpadding="0" cellspacing="0" style="width:100%;margin-top:20px;margin-bottom:20px;padding-left:10px;padding-right: 10px;">
            <tbody>
                <tr>
                    <td align="center" valign="middle">
                        <a href="#"
                           style="font-size:14px;font-weight:bold;line-height:31px;width:171px;
                                  border: 1px dotted #ff6c0e;color:#ff6c0e;border-radius:10px;
                                  display:inline-block;font-family:Canaro, sans-serif;
                                  text-align:center;text-decoration:none;
                                  -webkit-text-size-adjust:none;box-sizing:border-box;"
                           target="_blank">
                            {{ $order->tour_length }} days in destination
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
    @endif
@endforeach
<table border="0" cellpadding="0" cellspacing="0" style="width:100%;margin-top:10px;padding-left:10px;padding-right: 10px;display: none;">
	<tbody>
		<tr>
			<td align="center" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 14px; color: #000000; text-decoration: none;"><strong>5h 55m </strong>layover</span></td>
		</tr>
	</tbody>
</table>
</div>
</div>
@endif
@if ($order->booking_status !='pending')
<table border="0" cellpadding="0" cellspacing="0" style="max-width: 600px;margin:0 auto;width: 100%;margin-top:25px">
	<tbody>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 25px; color: #000000;">Payment</span></td>
			<td align="right" valign="middle"><a href="https://vibeadventures.be/api/preview/invoice?booking_id={{$order->booking_id}}&orderId={{$order->duffel_id}}&payment_id={{$order->payment_id}}" style="font-size:14px;font-weight:bold;line-height:31px;width:171px;border: 1px solid #ff6c0e;color:#ff6c0e;border-radius:10px;display:inline-block;font-family:Canaro, sans-serif;text-align:center;text-decoration:none;-webkit-text-size-adjust:none;box-sizing:border-box;" target="_blank">Download invoice</a></td>
		</tr>
	</tbody>
</table>
<div border="0" cellpadding="0" cellspacing="0" style="border-radius: 20px; border-width: 1px; border-color: #82cf45;border-style: solid; border-collapse: separate;width:100%;max-width:600px;margin: 25px auto auto;">
<div style="padding: 20px 30px;">
<table border="0" cellpadding="0" cellspacing="0" style="width:100%;">
	<tbody>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 22px; color: #000000;">Total</span></td>
			<td align="right" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 22px; color: #000000;">${{ number_format( ceil($order->paid), 2 ) }} USD</span></td>
		</tr>
		<tr style="height:5px">
		</tr>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 14px; color: #4f4f4f;">Incl. taxes and fees</span></td>
			<td align="right" valign="middle"><a href="#" style="font-size:14px;font-weight:bold;line-height: 18px;width: fit-content;border: 1px dotted #ff6c0e;color:#ff6c0e;border-radius:10px;display:inline-block;font-family:Canaro, sans-serif;text-align:center;text-decoration:none;padding: 0px 10px;display:none" target="_blank">$100 USD OFF</a></td>
		</tr>
		<tr style="height:10px">
		</tr>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 18px; color: #000000;margin-left:10px">Flights</span></td>
			<td align="right" valign="middle"><span style="font-family: 'Interstate Light Cond', sans-serif; font-size: 18px; color: #82cf45;">Included</span></td>
		</tr>
		<tr style="height:10px">
		</tr>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 18px; color: #000000;margin-left:10px">Organized Adventure</span></td>
			<td align="right" valign="middle"><span style="font-family: 'Interstate Light Cond', sans-serif; font-size: 18px; color: #82cf45;">Included</span></td>
		</tr>
		<tr style="height:10px">
		</tr>
		<tr>
			<td align="left" valign="middle">&nbsp;</td>
			<td align="right" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 14px;font-style:italic">{{ \Carbon\Carbon::parse($order->start)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($order->end)->format('M d, Y') }}</span></td>
		</tr>
		<tr>
			<td align="left" valign="middle">&nbsp;</td>
			<td align="right" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 14px;font-style:italic">
				 @foreach ($order->passengers as $acc)
                                        <p>
                                            <span style="color: #82CF45;">
                                                {{ $acc['passengers'] }}
                                            </span>
                                            × {{ $acc['name'] }}
                                        </p>
                                    @endforeach
			</span>
			</td>
		</tr>
		<tr style="height:10px">
		</tr>
		<tr>
			<td align="left" valign="middle"><span style="font-family: Canaro, sans-serif; font-size: 20px; color: #000000;">Payment history</span></td>
			<td align="right" valign="middle">&nbsp;</td>
		</tr>
	</tbody>
</table>

<table border="0" cellpadding="0" cellspacing="0" style="width:100%;margin-top:10px">
	<tbody>
		<tr>
			<th align="center" valign="middle"><a href="#" style="font-size:14px;line-height: 18px;width: fit-content;border: 2px dotted #82cf45;border-radius:10px;display:inline-block;font-family:Canaro, sans-serif;text-align:center;text-decoration:none;padding: 2px 10px;color: #4f4f4f;" target="_blank">Date and time</a></th>
			<th align="center" valign="middle"><a href="#" style="font-size:14px;line-height: 18px;width: fit-content;border: 2px dotted #82cf45;border-radius:10px;display:inline-block;font-family:Canaro, sans-serif;text-align:center;text-decoration:none;padding: 2px 10px;color: #4f4f4f;" target="_blank">Payment method</a></th>
			<th align="center" valign="middle"><a href="#" style="font-size:14px;line-height: 18px;width: fit-content;border: 2px dotted #82cf45;border-radius:10px;display:inline-block;font-family:Canaro, sans-serif;text-align:center;text-decoration:none;padding: 2px 10px;color: #4f4f4f;" target="_blank">Payment status</a></th>
			<th align="center" valign="middle"><a href="#" style="font-size:14px;line-height: 18px;width: fit-content;border: 2px dotted #82cf45;border-radius:10px;display:inline-block;font-family:Canaro, sans-serif;text-align:center;text-decoration:none;padding: 2px 10px;color: #4f4f4f;" target="_blank">Amount</a></th>
		</tr>
		<tr style="height:10px">
		</tr>
		<tr>
			<td align="center" valign="middle"><span style="font-family: Inter, sans-serif; font-size: 13px; color: #000000;">{{ \Carbon\Carbon::parse($order->stripe_created)->format('M d, Y') }}</span></td>
			<td align="center" valign="middle"><span style="font-family: Inter, sans-serif; font-size: 13px; color: #000000;">@if ($order->last_4)<strong>Visa</strong>****{{$order->last_4}}@else<strong>{{$order->payment_method}}</strong>@endif</span></td>
			@if ($order->booking_status =='confirmed')
			<td align="center" valign="middle"><span style="font-family: Inter, sans-serif;font-size: 13px;color: #82cf45;background: #def9cb;padding: 2px 8px;border-radius: 4px;border: 1px solid #82cf45;font-weight: bold;">Succeeded ✔</span></td>
			@else
			<td align="center" valign="middle"><span style="font-family: Inter, sans-serif;font-size: 13px;color: #82cf45;background: #def9cb;padding: 2px 8px;border-radius: 4px;border: 1px solid #82cf45;font-weight: bold;">Pending ✔</span></td>
			@endif
			<td align="center" valign="middle"><span style="font-family: Inter, sans-serif; font-size: 13px; color: #000000; font-weight: bold;">${{ number_format( ceil($order->paid), 2 ) }} USD</span></td>
		</tr>
	</tbody>
</table>
</div>
</div>
@endif
</body>
</html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel</title>
    <meta http-equiv="Content-Type" content="text/html;"/>
    <meta charset="UTF-8">
	<style media="all">
		@font-face {
            font-family: 'Roboto';
            src: url("{{ asset('fonts/Roboto-Regular.ttf') }}") format("truetype");
            font-weight: normal;
            font-style: normal;
        }
        *{
            margin: 0;
            padding: 0;
            line-height: 1;
            font-family: 'Roboto';
            color: #333542;
        }
		body{
			font-size: .875rem;
		}
		.gry-color *,
		.gry-color{
			color:#878f9c;
		}
		table{
			width: 100%;
		}
		table th{
			font-weight: normal;
		}
		table.padding th{
			padding: .0rem .7rem;
		}
		table.padding td{
			padding: .0rem .7rem;
		}
		table.sm-padding td{
			padding: .0rem .7rem;
		}
		.border-bottom td,
		.border-bottom th{
			border-bottom:1px solid #eceff4;
		}
		.text-left{
			text-align:left;
		}
		.text-right{
			text-align:right;
		}
		.small{
			font-size: .85rem;
		}
		.currency{

		}
	</style>
</head>
<body>
	<div style="margin-left:auto;margin-right:auto;">

		<div style="background: #eceff4;padding: 0.5rem;">
			<table>
				<tr>
					<td style="font-size: 2.5rem;" class="text-right strong">INVOICE</td>
				</tr>
			</table>
		</div>
		<div style="padding: 1.5rem;padding-bottom: 0">
            
                <table>
						<tr>
                            <td style="font-size: 1.2rem;" class="strong" width="50%" valign="top">Bill by: {{ $setting->store }}</td>
                            <td width="10%">&nbsp;</td>
                            <td style="font-size: 1.2rem;" class="strong" width="50%" valign="top">Bill to: {{ $user->name_order }}</td>
                        </tr>
                        <tr>
                            <td class="gry-color small" valign="top">{{ $setting->location_store }}</td>
                            <td>&nbsp;</td>
                            <td class="gry-color small" valign="top">{{ $user->address }}</td>
                        </tr>
                        <tr>
                            <td class="gry-color small" valign="top">Phone: {{ $setting->contact }}</td>
                            <td>&nbsp;</td>
                            <td class="gry-color small" valign="top">Phone: {{ $user->phone }}</td>
                        </tr>
                </table>
            
			
		</div>

		

	    <div style="padding: 1.5rem;">
            <table>
            <tr>
                <td class="gry-color small" valign="top">Invoices: {{ $order->invoice_number }}</td>
            </tr>
            </table>
			<table class="padding text-left small border-bottom">
				<thead>
	                <tr class="gry-color" style="background: #eceff4;">
						<th><span>Name</span></th>
						<th><span>Price</span></th>
						<th><span>Quantity</span></th>
						<th><span>Total</span></th>
	                </tr>
				</thead>
				<tbody class="strong">
				@foreach ($orderProductTmp as $key => $item)
                <tr>
                  <td>
                    <span>{{ $item->name_product }}</span>
                  </td>
                  <td>
                    <span 
                      >{{  $item->price_product }}
					  @if ($item->promotion_name != null)
                      <p>
                        discount {{  $item->promotion_name }}
                      </p>
					  @endif
                    </span>
                  </td>
                  <td>
                    <span>{{  $item->quantity }}</span>
                  </td>
                  <td>
                    <span >{{  $item->quantity *  $item->price_product }}</span>
                  </td>
                </tr>
				@endforeach
	            </tbody>
			</table>
            Pengiriman:<br>
            <br>
            {{ $order->courier_service }}
		</div>

	    <div style="padding:0 1.5rem;">
	        <table style="width: 40%;margin-left:auto;" class="text-right sm-padding small strong">
		        <tbody>
				<h5><span>Additional Notes** </span></h5>
					<div>
					<p>Items that have been purchased cannot be returned</p>
					</div>
		        </tbody>
		    </table>
	    </div>

		<div style="padding:0 1.5rem;">
	        <table style="width: 40%;margin-left:auto;" class="text-right sm-padding small strong">
		        <tbody>
			        <tr>
			            <th class="gry-color text-left">Tax</th>
			            <td class="currency text-right">{{ $order->tax }}</td>
			        </tr>
			        <tr>
			            <th class="gry-color text-left">Courier Cost</th>
			            <td class="currency text-right">{{ $order->total_bill - $courierCost }}</td>
			        </tr>
			        <tr>
			            <th class="text-left strong">Tota Billl</th>
			            <td class="currency text-right">{{ $order->total_bill }}</td>
			        </tr>
		        </tbody>
		    </table>
	    </div>

	</div>
</body>
</html>
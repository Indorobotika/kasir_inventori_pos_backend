<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Setting;

class PosController extends Controller {
    public function paymentPos(Request $request) {
        $request->validate(['carts' => 'required', 'payment_method' => 'required|in:CASH,CARD,QRIS']);

        return DB::transaction(function () use ($request) {
            $carts = is_string($request->carts) ? json_decode($request->carts, true) : $request->carts;
            $platform = $request->platform ?? 'Offline Store';
            $setting = Setting::first();
            $taxRate = $setting ? $setting->tax : 11;

            $subtotal = 0;
            $totalCost = 0;
            $preparedItems = [];

            foreach ($carts as $item) {
                $product = Product::with(['recipes.rawMaterial', 'platformPrices'])->lockForUpdate()->findOrFail($item['id']);
                $qty = (int) $item['quantity'];

                $platformPrice = $product->platformPrices->where('platform_name', $platform)->first();
                $unitPrice = $platformPrice ? $platformPrice->price : $product->price;

                $unitCost = $product->cost_price;
                if ($product->product_type === 'resep') {
                    $unitCost = 0;
                    foreach ($product->recipes as $recipe) {
                        $needed = $recipe->qty_needed * $qty;
                        if ($recipe->rawMaterial->stock < $needed) {
                            throw new \Exception("Stok bahan baku {$recipe->rawMaterial->name} tidak mencukupi.");
                        }
                        $recipe->rawMaterial->decrement('stock', $needed);
                        $unitCost += ($recipe->rawMaterial->cost_price * $recipe->qty_needed);
                    }
                } else {
                    if ($product->stock < $qty) {
                        throw new \Exception("Stok produk {$product->title} tidak mencukupi.");
                    }
                    $product->decrement('stock', $qty);
                }

                $lineSubtotal = $unitPrice * $qty;
                $subtotal += $lineSubtotal;
                $totalCost += ($unitCost * $qty);

                $preparedItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'cost_product' => $unitCost,
                    'price_product' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];
            }

            $discountAmount = 0;
            $promo = Promotion::where('is_active', true)->where('required', '<=', $subtotal)->orderByDesc('result')->first();
            if ($promo) {
                $discountAmount = ($promo->typ_promotion_id == 2) ? ($subtotal * ($promo->result / 100)) : $promo->result;
            }

            $afterDiscount = max(0, $subtotal - $discountAmount);
            $taxAmount = round($afterDiscount * ($taxRate / 100));
            $totalBill = $afterDiscount + $taxAmount;

            $cashReceived = (float) ($request->nominalCash ?? $totalBill);
            $cashChange = $request->payment_method === 'CASH' ? max(0, $cashReceived - $totalBill) : 0;

            $order = Order::create([
                'invoice_number' => 'INV-' . date('ymd') . '-' . rand(10000000, 99999999),
                'user_id' => $request->user()->id,
                'customer_name' => $request->customer_name ?? 'Umum',
                'platform' => $platform,
                'payment_method' => $request->payment_method,
                'numberCard' => $request->numberCard,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax' => $taxAmount,
                'total_cost' => $totalCost,
                'total_bill' => $totalBill,
                'cash_received' => $cashReceived,
                'cash_change' => $cashChange,
                'status' => $request->payment_method === 'QRIS' ? 'pending' : 'success',
            ]);

            foreach ($preparedItems as $pItem) {
                $pItem['order_id'] = $order->id;
                OrderDetail::create($pItem);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_id' => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'total_bill' => $totalBill,
                    'cash_change' => $cashChange,
                    'status' => $order->status,
                ]
            ]);
        });
    }

    public function generateQris(Request $request) {
        $order = Order::findOrFail($request->order_id);
        $qrisPayload = "00020101021226590014ID.CO.QRIS.WWW01189360091430000000000215" . $order->invoice_number . "5405" . (int)$order->total_bill . "5802ID5914TOKO SEJAHTERA6007BANDUNG6304ABCD";
        return response()->json(['status' => 'success', 'invoice_number' => $order->invoice_number, 'amount' => $order->total_bill, 'qris_string' => $qrisPayload]);
    }

    public function qrisWebhook(Request $request) {
        $order = Order::where('invoice_number', $request->order_id)->firstOrFail();
        if (in_array($request->transaction_status, ['settlement', 'capture'])) {
            $order->update(['status' => 'success']);
        } elseif (in_array($request->transaction_status, ['cancel', 'expire'])) {
            $order->update(['status' => 'canceled']);
        }
        return response()->json(['status' => 'ok']);
    }

    public function reportOrder(Request $request) {
        $order = Order::with('user')->findOrFail($request->id);
        $details = OrderDetail::join('products', 'order_details.product_id', '=', 'products.id')
            ->where('order_details.order_id', $order->id)
            ->select('order_details.*', 'products.title as name_product')
            ->get();
        return response()->json(['data' => $order, 'product_order' => $details, 'setting' => Setting::first()]);
    }

    public function listOrders(Request $request) {
        $start = $request->start_date ?? date('Y-m-d');
        $end = $request->end_date ?? date('Y-m-d');
        $query = Order::whereBetween(DB::raw('DATE(created_at)'), [$start, $end])->orderByDesc('created_at');
        if ($request->status) $query->where('status', $request->status);
        $orders = $query->get();
        return response()->json([
            'summary' => [
                'total_transactions' => $orders->where('status', 'success')->count(),
                'omzet' => $orders->where('status', 'success')->sum('total_bill'),
                'pending' => $orders->where('status', 'pending')->count(),
                'canceled' => $orders->where('status', 'canceled')->count(),
            ],
            'orders' => $orders
        ]);
    }
}

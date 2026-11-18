<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\Cashflow;

class PosController extends Controller
{
    public function paymentPos(Request $request)
    {
        $request->validate([
            'carts'          => 'required',
            'payment_method' => 'required|in:CASH,CARD,QRIS',
        ]);

        return DB::transaction(function () use ($request) {
            $carts = is_string($request->carts) ? json_decode($request->carts, true) : $request->carts;
            if (!is_array($carts) || count($carts) === 0) {
                throw new \Exception('Keranjang belanja kosong.');
            }

            $platform = $request->platform ?? 'Offline Store';
            $setting  = Setting::first();
            $taxRate  = $setting ? $setting->tax : 11;

            $subtotal      = 0;
            $totalCost     = 0;
            $preparedItems = [];

            foreach ($carts as $item) {
                $product = Product::with(['recipes.rawMaterial', 'platformPrices'])
                    ->lockForUpdate()
                    ->findOrFail($item['id']);
                $qty = (int) $item['quantity'];

                $platformPrice = $product->platformPrices->where('platform_name', $platform)->first();
                $unitPrice     = $platformPrice ? $platformPrice->price : $product->price;

                $unitCost = $product->cost_price;

                // Mendukung pengecekan product_type maupun type secara fleksibel
                $productType = strtolower(trim($product->product_type ?? $product->type ?? 'retail'));

                if ($productType === 'resep' || $productType === 'recipe') {
                    $unitCost = 0;

                    if (!$product->recipes || $product->recipes->count() === 0) {
                        throw new \Exception("Produk resep {$product->title} tidak memiliki komponen bahan baku.");
                    }

                    foreach ($product->recipes as $recipe) {
                        // Ambil ID bahan baku secara langsung dari kolom foreign key tabel resep
                        $rawMaterialId = $recipe->raw_material_id ?? ($recipe->rawMaterial ? $recipe->rawMaterial->id : null);

                        if (!$rawMaterialId) {
                            throw new \Exception("ID bahan baku pada resep {$product->title} tidak valid.");
                        }

                        $needed = (float) $recipe->qty_needed * $qty;

                        // Lock baris bahan baku berdasarkan ID yang benar untuk mencegah race condition
                        $rawMaterial = RawMaterial::lockForUpdate()->find($rawMaterialId);

                        if (!$rawMaterial || $rawMaterial->stock < $needed) {
                            $matName = $rawMaterial ? $rawMaterial->name : 'Bahan Baku';
                            throw new \Exception("Stok bahan baku '{$matName}' tidak mencukupi (Butuh: {$needed}, Tersedia: " . ($rawMaterial ? $rawMaterial->stock : 0) . ").");
                        }

                        // Kurangi stok masing-masing bahan baku secara akurat
                        $rawMaterial->decrement('stock', $needed);

                        // Akumulasi HPP berdasarkan HPP seluruh bahan baku yang dikonsumsi
                        $unitCost += ($rawMaterial->cost_price * (float) $recipe->qty_needed);
                    }
                } else {
                    if ($product->stock < $qty) {
                        throw new \Exception("Stok produk {$product->title} tidak mencukupi.");
                    }
                    $product->decrement('stock', $qty);
                }

                $lineSubtotal = $unitPrice * $qty;
                $subtotal    += $lineSubtotal;
                $totalCost   += ($unitCost * $qty);

                $preparedItems[] = [
                    'product_id'    => $product->id,
                    'quantity'      => $qty,
                    'cost_product'  => $unitCost,
                    'price_product' => $unitPrice,
                    'subtotal'      => $lineSubtotal,
                ];
            }

            $discountAmount = 0;
            $promoId        = null;
            $promo          = Promotion::where('is_active', true)
                ->where('required', '<=', $subtotal)
                ->orderByDesc('result')
                ->first();

            if ($promo) {
                $promoId        = $promo->id;
                $discountAmount = ($promo->typ_promotion_id == 2)
                    ? round($subtotal * ($promo->result / 100))
                    : $promo->result;
            }

            $afterDiscount = max(0, $subtotal - $discountAmount);
            $taxAmount     = round($afterDiscount * ($taxRate / 100));
            $totalBill     = $afterDiscount + $taxAmount;

            $cashReceived = (float) ($request->nominalCash ?? $totalBill);
            $cashChange   = $request->payment_method === 'CASH' ? max(0, $cashReceived - $totalBill) : 0;
            $orderStatus  = $request->payment_method === 'QRIS' ? 'pending' : 'success';

            $order = Order::create([
                'invoice_number'  => 'INV-' . date('ymd') . '-' . rand(10000000, 99999999),
                'user_id'         => $request->user()->id,
                'customer_name'   => $request->customer_name ?? 'Umum',
                'platform'        => $platform,
                'payment_method'  => $request->payment_method,
                'numberCard'      => $request->numberCard,
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'tax'             => $taxAmount,
                'total_cost'      => $totalCost,
                'total_bill'      => $totalBill,
                'cash_received'   => $cashReceived,
                'cash_change'     => $cashChange,
                'status'          => $orderStatus,
            ]);

            foreach ($preparedItems as $pItem) {
                $pItem['order_id']     = $order->id;
                $pItem['promotion_id'] = $promoId;
                OrderDetail::create($pItem);
            }

            if ($orderStatus === 'success') {
                Cashflow::create([
                    'user_id'          => $request->user()->id,
                    'type'             => 'IN',
                    'category'         => 'Penjualan Kasir',
                    'amount'           => $totalBill,
                    'description'      => 'Pendapatan Order #' . $order->invoice_number,
                    'transaction_date' => now()->toDateString(),
                ]);
            }

            // Hapus cache produk agar stok terbaru langsung tersinkronisasi
            Cache::forget('products_all_all');
            Cache::forget('raw_materials_all'); // <-- TAMBAHKAN INI

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'order_id'       => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'total_bill'     => $totalBill,
                    'cash_change'    => $cashChange,
                    'status'         => $order->status,
                ],
            ]);
        });
    }

   public function generateQris(Request $request)
    {
        $order     = Order::findOrFail($request->order_id);
        $setting   = Setting::first();
        $storeName = strtoupper(substr(preg_replace('/[^A-Za-z0-9 ]/', '', $setting->store_name ?? 'TOKO KASIR'), 0, 25));

        $qrisPayload = "00020101021226590014ID.CO.QRIS.WWW01189360091430000000000215"
            . $order->invoice_number
            . "5405" . (int) $order->total_bill
            . "5802ID5914" . $storeName . "6007JAKARTA6304ABCD";

        return response()->json([
            'status'         => 'success',
            'invoice_number' => $order->invoice_number,
            'amount'         => $order->total_bill,
            'qris_string'    => $qrisPayload,
            'qris_image_url' => $setting ? $setting->qris_path_url : null, // <-- Menyertakan URL gambar QRIS setting
        ]);
    }

    public function qrisWebhook(Request $request)
    {
        $order = Order::where('invoice_number', $request->order_id)->firstOrFail();

        if (in_array($request->transaction_status, ['settlement', 'capture'])) {
            if ($order->status !== 'success') {
                $order->update(['status' => 'success']);

                Cashflow::create([
                    'user_id'          => $order->user_id,
                    'type'             => 'IN',
                    'category'         => 'Penjualan Kasir (QRIS)',
                    'amount'           => $order->total_bill,
                    'description'      => 'Pelunasan QRIS Order #' . $order->invoice_number,
                    'transaction_date' => now()->toDateString(),
                ]);
            }
        } elseif (in_array($request->transaction_status, ['cancel', 'expire'])) {
            $order->update(['status' => 'canceled']);
        }

        return response()->json(['status' => 'ok']);
    }

    public function reportOrder(Request $request)
    {
        $order   = Order::with('user')->findOrFail($request->id);
        $details = OrderDetail::join('products', 'order_details.product_id', '=', 'products.id')
            ->where('order_details.order_id', $order->id)
            ->select('order_details.*', 'products.title as name_product')
            ->get();

        return response()->json([
            'data'          => $order,
            'product_order' => $details,
            'setting'       => Setting::first(),
        ]);
    }

    public function listOrders(Request $request)
    {
        $start = $request->start_date ?? date('Y-m-d');
        $end   = $request->end_date ?? date('Y-m-d');

        $query = Order::with(['details.product'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
            ->orderByDesc('created_at');

        if ($request->status && $request->status !== 'Semua status') {
            $dbStatus = strtolower($request->status) === 'selesai'
                ? 'success'
                : (strtolower($request->status) === 'dibatalkan' ? 'canceled' : 'pending');
            $query->where('status', $dbStatus);
        }

        $orders = $query->get();

        return response()->json([
            'summary' => [
                'total_transactions' => $orders->where('status', 'success')->count(),
                'omzet'              => $orders->where('status', 'success')->sum('total_bill'),
                'pending'            => $orders->where('status', 'pending')->count(),
                'canceled'           => $orders->where('status', 'canceled')->count(),
            ],
            'orders'  => $orders,
        ]);
    }

    public function markAsPaid(Request $request, $id)
    {
        return DB::transaction(function () use ($id) {
            $order = Order::findOrFail($id);

            if ($order->status === 'success') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Pesanan sudah lunas.',
                ], 400);
            }

            $order->update(['status' => 'success']);

            Cashflow::create([
                'user_id'          => $order->user_id,
                'type'             => 'IN',
                'category'         => 'Penjualan Kasir (QRIS/Pelunasan)',
                'amount'           => $order->total_bill,
                'description'      => 'Pelunasan Order #' . $order->invoice_number,
                'transaction_date' => now()->toDateString(),
            ]);

            // Bersihkan cache bahan baku juga di sini jika diperlukan
            Cache::forget('raw_materials_all');

            return response()->json([
                'status'  => 'success',
                'message' => 'Pesanan berhasil dilunasi.',
                'data'    => $order,
            ]);
        });
    }


}
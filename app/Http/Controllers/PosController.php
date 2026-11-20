<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderCancellationReport;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\Cashflow;

class PosController extends Controller
{
    /**
     * Bersihkan seluruh cache terkait produk dan bahan baku agar stok/status selalu segar.
     */
    private function clearInventoryCache()
    {
        $keys = [
            'products_all_all',
            'products_all_retail',
            'products_all_resep',
            'products_all_recipe',
            'raw_materials_all',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Cek apakah platform penjualan termasuk channel marketplace (bukan toko offline).
     */
    private function isMarketplacePlatform($platform)
    {
        $normalized = strtolower(trim((string) $platform));
        return $normalized !== '' && !in_array($normalized, ['offline store', 'offline', 'pos', 'toko'], true);
    }

    /**
     * Hitung nominal uang riil bersih yang masuk ke cashflow (sudah bebas dari PPN toko).
     */
    private function calculateCleanCashflowAmount(Order $order)
    {
        $totalBill = (float) ($order->total_bill ?? 0);
        $tax       = (float) ($order->tax ?? 0);

        return max(0, round($totalBill - $tax, 2));
    }

    /**
     * Catat arus kas masuk bersih ke tabel cashflows.
     */
    private function recordCleanSaleCashflow(Order $order, $category, $description)
    {
        $cleanAmount = $this->calculateCleanCashflowAmount($order);

        Cashflow::create([
            'user_id'          => $order->user_id,
            'type'             => 'IN',
            'category'         => $category,
            'amount'           => $cleanAmount,
            'description'      => $description,
            'transaction_date' => now()->toDateString(),
        ]);
    }

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
            $taxRate  = $setting ? (float) $setting->tax : 11;

            $subtotal      = 0;
            $totalCost     = 0;
            $preparedItems = [];

            foreach ($carts as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    throw new \Exception('Jumlah item tidak valid.');
                }

                $isRawMaterialItem = (isset($item['source_type']) && $item['source_type'] === 'raw_material')
                    || (!empty($item['raw_material_id']))
                    || (isset($item['id']) && strpos((string) $item['id'], 'rm_') === 0);

                if ($isRawMaterialItem) {
                    $rawId = !empty($item['raw_material_id'])
                        ? (int) $item['raw_material_id']
                        : (int) str_replace('rm_', '', (string) $item['id']);

                    $rawMaterial = RawMaterial::with('category')
                        ->lockForUpdate()
                        ->findOrFail($rawId);

                    if (!$rawMaterial->is_sellable) {
                        throw new \Exception("Bahan baku '{$rawMaterial->name}' sedang tidak aktif dijual di kasir.");
                    }

                    if ((float) $rawMaterial->stock < $qty) {
                        throw new \Exception("Stok bahan baku '{$rawMaterial->name}' tidak mencukupi (Butuh: {$qty}, Tersedia: {$rawMaterial->stock}).");
                    }

                    $rawMaterial->decrement('stock', $qty);

                    $unitPrice    = (float) ($rawMaterial->sell_price > 0 ? $rawMaterial->sell_price : ($item['price'] ?? 0));
                    $unitCost     = (float) ($rawMaterial->cost_price ?? 0);
                    $lineSubtotal = $unitPrice * $qty;

                    $subtotal  += $lineSubtotal;
                    $totalCost += ($unitCost * $qty);

                    $preparedItems[] = [
                        'product_id'      => null,
                        'raw_material_id' => $rawMaterial->id,
                        'item_name'       => $rawMaterial->name,
                        'quantity'        => $qty,
                        'cost_product'    => $unitCost,
                        'price_product'   => $unitPrice,
                        'subtotal'        => $lineSubtotal,
                    ];
                } else {
                    $product = Product::with(['recipes.rawMaterial', 'platformPrices'])
                        ->lockForUpdate()
                        ->findOrFail($item['id']);

                    // Ambil harga platform bila tersedia tanpa mengubah harga master produk ($product->price)
                    $platformPrice = $product->platformPrices->where('platform_name', $platform)->first();
                    $unitPrice     = (float) ($platformPrice ? $platformPrice->price : $product->price);
                    $unitCost      = (float) ($product->cost_price ?? 0);

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

                            if (!$rawMaterial || (float) $rawMaterial->stock < $needed) {
                                $matName = $rawMaterial ? $rawMaterial->name : 'Bahan Baku';
                                throw new \Exception("Stok bahan baku '{$matName}' tidak mencukupi (Butuh: {$needed}, Tersedia: " . ($rawMaterial ? $rawMaterial->stock : 0) . ").");
                            }

                            // Kurangi stok masing-masing bahan baku secara akurat
                            $rawMaterial->decrement('stock', $needed);

                            // Akumulasi HPP berdasarkan HPP seluruh bahan baku yang dikonsumsi
                            $unitCost += ((float) $rawMaterial->cost_price * (float) $recipe->qty_needed);
                        }
                    } else {
                        if ((float) $product->stock < $qty) {
                            throw new \Exception("Stok produk {$product->title} tidak mencukupi.");
                        }
                        $product->decrement('stock', $qty);
                    }

                    $lineSubtotal = $unitPrice * $qty;
                    $subtotal    += $lineSubtotal;
                    $totalCost   += ($unitCost * $qty);

                    $preparedItems[] = [
                        'product_id'      => $product->id,
                        'raw_material_id' => null,
                        'item_name'       => $product->title,
                        'quantity'        => $qty,
                        'cost_product'    => $unitCost,
                        'price_product'   => $unitPrice,
                        'subtotal'        => $lineSubtotal,
                    ];
                }
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

            $afterDiscount       = max(0, $subtotal - $discountAmount);
            $isMarketplaceFinal  = $this->isMarketplacePlatform($platform)
                && filter_var($request->input('is_marketplace_final_price', false), FILTER_VALIDATE_BOOLEAN)
                && $request->filled('final_received_price');

            if ($isMarketplaceFinal) {
                $finalReceivedPrice = max(0, round((float) $request->input('final_received_price'), 2));
                $supplierSalesTax   = $request->filled('supplier_sales_tax')
                    ? max(0, round((float) $request->input('supplier_sales_tax'), 2))
                    : max(0, round($afterDiscount - $finalReceivedPrice, 2));

                // Pada Pola A marketplace, PPN kasir offline tidak dihitung ganda dan total_bill adalah dana bersih diterima
                $taxAmount = 0;
                $totalBill = $finalReceivedPrice;
            } else {
                $supplierSalesTax = max(0, round((float) $request->input('supplier_sales_tax', 0), 2));
                $taxAmount        = round($afterDiscount * ($taxRate / 100));
                $totalBill        = $afterDiscount + $taxAmount;
            }

            $cashReceived = (float) ($request->nominalCash ?? $totalBill);
            $cashChange   = $request->payment_method === 'CASH' ? max(0, $cashReceived - $totalBill) : 0;
            $orderStatus  = $request->payment_method === 'QRIS' ? 'pending' : 'success';

            $order = Order::create([
                'invoice_number'     => 'INV-' . date('ymd') . '-' . rand(10000000, 99999999),
                'user_id'            => $request->user()->id,
                'customer_name'      => $request->customer_name ?? 'Umum',
                'platform'           => $platform,
                'payment_method'     => $request->payment_method,
                'numberCard'         => $request->numberCard,
                'subtotal'           => $subtotal,
                'discount_amount'    => $discountAmount,
                'tax'                => $taxAmount,
                'supplier_sales_tax' => $supplierSalesTax,
                'total_cost'         => $totalCost,
                'total_bill'         => $totalBill,
                'cash_received'      => $cashReceived,
                'cash_change'        => $cashChange,
                'status'             => $orderStatus,
            ]);

            foreach ($preparedItems as $pItem) {
                $pItem['order_id']     = $order->id;
                $pItem['promotion_id'] = $promoId;
                OrderDetail::create($pItem);
            }

            if ($orderStatus === 'success') {
                $this->recordCleanSaleCashflow(
                    $order,
                    'Penjualan Kasir',
                    'Pendapatan Bersih Order #' . $order->invoice_number
                );
            }

            $this->clearInventoryCache();

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'order_id'           => $order->id,
                    'invoice_number'     => $order->invoice_number,
                    'supplier_sales_tax' => $supplierSalesTax,
                    'total_bill'         => $totalBill,
                    'cash_change'        => $cashChange,
                    'status'             => $order->status,
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
            'qris_image_url' => $setting ? $setting->qris_path_url : null,
        ]);
    }

    public function qrisWebhook(Request $request)
    {
        $order = Order::where('invoice_number', $request->order_id)->firstOrFail();

        if (in_array($request->transaction_status, ['settlement', 'capture'])) {
            if ($order->status !== 'success') {
                $order->update(['status' => 'success']);

                $this->recordCleanSaleCashflow(
                    $order,
                    'Penjualan Kasir (QRIS)',
                    'Pelunasan QRIS Bersih Order #' . $order->invoice_number
                );

                $this->clearInventoryCache();
            }
        } elseif (in_array($request->transaction_status, ['cancel', 'expire'])) {
            $order->update(['status' => 'canceled']);
            $this->clearInventoryCache();
        }

        return response()->json(['status' => 'ok']);
    }

    public function reportOrder(Request $request)
    {
        $order   = Order::with('user')->findOrFail($request->id);
        $details = OrderDetail::leftJoin('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('raw_materials', 'order_details.raw_material_id', '=', 'raw_materials.id')
            ->where('order_details.order_id', $order->id)
            ->select(
                'order_details.*',
                DB::raw('COALESCE(order_details.item_name, products.title, raw_materials.name) as name_product')
            )
            ->get();

        return response()->json([
            'data'          => $order,
            'product_order' => $details,
            'setting'       => Setting::first(),
        ]);
    }

    public function listOrders(Request $request)
    {
        $startStr = $request->query('start_date') ?? now()->toDateString();
        $endStr   = $request->query('end_date') ?? now()->toDateString();

        $startAt = Carbon::parse($startStr)->startOfDay()->toDateTimeString();
        $endAt   = Carbon::parse($endStr)->endOfDay()->toDateTimeString();

        $allowedSizes = [10, 20, 50];
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, $allowedSizes, true)) {
            $perPage = 10;
        }

        $query = Order::with(['details.product', 'details.rawMaterial'])
            ->whereBetween('created_at', [$startAt, $endAt])
            ->orderByDesc('created_at');

        if ($request->filled('status') && $request->query('status') !== 'Semua status') {
            $statusParam = strtolower(trim((string) $request->query('status')));
            $dbStatus = $statusParam === 'selesai' || $statusParam === 'success'
                ? 'success'
                : (($statusParam === 'dibatalkan' || $statusParam === 'canceled') ? 'canceled' : 'pending');
            $query->where('status', $dbStatus);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                  ->orWhere('customer_name', 'like', '%' . $search . '%');
            });
        }

        $allMatching = (clone $query)->get();
        $paginated   = $query->paginate($perPage);

        return response()->json([
            'summary' => [
                'total_transactions' => $allMatching->where('status', 'success')->count(),
                'omzet'              => $allMatching->where('status', 'success')->sum('total_bill'),
                'pending'            => $allMatching->where('status', 'pending')->count(),
                'canceled'           => $allMatching->where('status', 'canceled')->count(),
            ],
            'orders'     => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem() ?? 0,
                'to'           => $paginated->lastItem() ?? 0,
            ],
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

            $this->recordCleanSaleCashflow(
                $order,
                'Penjualan Kasir (QRIS/Pelunasan)',
                'Pelunasan Bersih Order #' . $order->invoice_number
            );

            $this->clearInventoryCache();

            return response()->json([
                'status'  => 'success',
                'message' => 'Pesanan berhasil dilunasi.',
                'data'    => $order,
            ]);
        });
    }

    public function cancelOrder(Request $request, $id)
    {
        $request->validate([
            'berita_acara' => 'required|string|min:3|max:1000',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $order = Order::with(['details.product.recipes.rawMaterial', 'details.rawMaterial'])
                ->lockForUpdate()
                ->findOrFail($id);

            $invoiceNumber = $order->invoice_number;
            $nominal       = (float) $order->total_bill;

            // 1. Kembalikan stok bahan baku & produk berdasarkan detail order
            foreach ($order->details as $detail) {
                $qty = (float) ($detail->quantity ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                if (!empty($detail->raw_material_id)) {
                    $rawMaterial = RawMaterial::lockForUpdate()->find($detail->raw_material_id);
                    if ($rawMaterial) {
                        $rawMaterial->increment('stock', $qty);
                    }
                } elseif (!empty($detail->product_id)) {
                    $product = Product::with('recipes')->lockForUpdate()->find($detail->product_id);
                    if ($product) {
                        $productType = strtolower(trim((string) ($product->product_type ?? $product->type ?? 'retail')));

                        if ($productType === 'resep' || $productType === 'recipe') {
                            foreach ($product->recipes as $recipe) {
                                $rawMaterialId = $recipe->raw_material_id ?? ($recipe->rawMaterial ? $recipe->rawMaterial->id : null);
                                $needed = (float) ($recipe->qty_needed ?? 0) * $qty;
                                if ($rawMaterialId && $needed > 0) {
                                    $rawMaterial = RawMaterial::lockForUpdate()->find($rawMaterialId);
                                    if ($rawMaterial) {
                                        $rawMaterial->increment('stock', $needed);
                                    }
                                }
                            }
                        } else {
                            $product->increment('stock', $qty);
                        }
                    }
                }
            }

            // 2. Simpan berita acara pembatalan ke tabel khusus (no invoice & nominal)
            $report = OrderCancellationReport::create([
                'order_id'       => $order->id,
                'invoice_number' => $invoiceNumber,
                'nominal'        => $nominal,
                'berita_acara'   => trim($request->berita_acara),
                'canceled_by'    => $request->user() ? $request->user()->id : null,
                'canceled_at'    => now(),
            ]);

            // 3. Kalkulasi ulang cashflow sesuai nomor order (hapus entri cashflow terkait invoice)
            Cashflow::where('description', 'like', '%' . $invoiceNumber . '%')->delete();

            // 4. Hapus detail & order agar terhapus dari layar pesanan
            OrderDetail::where('order_id', $order->id)->delete();
            $order->delete();

            $this->clearInventoryCache();

            return response()->json([
                'status'  => 'success',
                'message' => "Pesanan {$invoiceNumber} berhasil dibatalkan, stok dikembalikan, dan cashflow diperbarui.",
                'data'    => $report,
            ]);
        });
    }
}
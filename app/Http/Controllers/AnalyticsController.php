<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonPeriod;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\RawMaterial;
use App\Models\Product;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request)
    {
        $days         = max(1, (int) ($request->get('days', 30)));
        $selectedYear = max(2000, (int) ($request->get('year', now()->year)));
        $now          = now();
        $endOfDay     = $now->copy()->endOfDay();
        $startDate    = $now->copy()->subDays($days - 1)->startOfDay();
        $prevStart    = $startDate->copy()->subDays($days);
        $prevEnd      = $startDate->copy()->subSecond();
        $today        = $now->toDateString();

        $sanitizeUtf8 = function ($value) use (&$sanitizeUtf8) {
            if (is_array($value)) {
                $clean = [];
                foreach ($value as $k => $v) {
                    $clean[$k] = $sanitizeUtf8($v);
                }
                return $clean;
            }
            if (is_object($value)) {
                return $sanitizeUtf8((array) $value);
            }
            if (is_string($value)) {
                return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
            return $value;
        };

        $calcGrowth = function ($current, $previous) {
            $curr = (float) $current;
            $prev = (float) $previous;
            if ($prev <= 0.0) {
                return $curr > 0.0 ? 100.0 : 0.0;
            }
            return round((($curr - $prev) / $prev) * 100, 1);
        };

        // Ekspresi penjualan bersih setelah dipotong tax (Pola A marketplace sudah tax = 0)
        $cleanOrderSalesExpr = 'GREATEST(total_bill - tax, 0)';
        $cleanOrderProfitExpr = "GREATEST(total_bill - tax, 0) - total_cost";

        // 1. Ringkasan Hari Ini (Agregasi SQL langsung)
        $todayStats = Order::where('status', 'success')
            ->whereDate('created_at', $today)
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(total_bill), 0) AS revenue')
            ->first();

        $todayRevenue = (float) ($todayStats && isset($todayStats->revenue) ? $todayStats->revenue : 0);
        $todayCount   = (int) ($todayStats && isset($todayStats->orders) ? $todayStats->orders : 0);

        // 2. Ringkasan Periode Berjalan (KPI)
        $periodStats = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate->toDateTimeString())
            ->where('created_at', '<=', $endOfDay->toDateTimeString())
            ->selectRaw("
                COUNT(*) AS transactions,
                COALESCE(SUM({$cleanOrderSalesExpr}), 0) AS net_sales,
                COALESCE(SUM(total_cost), 0) AS total_hpp,
                COALESCE(SUM(total_bill), 0) AS total_bill,
                COALESCE(SUM(discount_amount), 0) AS total_discount,
                COALESCE(SUM(tax + supplier_sales_tax), 0) AS total_tax_deduction
            ")
            ->first();

        // Periode Sebelumnya (untuk indikator pertumbuhan %)
        $prevStats = Order::where('status', 'success')
            ->whereBetween('created_at', [$prevStart->toDateTimeString(), $prevEnd->toDateTimeString()])
            ->selectRaw("
                COUNT(*) AS transactions,
                COALESCE(SUM({$cleanOrderSalesExpr}), 0) AS net_sales,
                COALESCE(SUM({$cleanOrderProfitExpr}), 0) AS gross_profit
            ")
            ->first();

        $netSales         = (float) ($periodStats && isset($periodStats->net_sales) ? $periodStats->net_sales : 0);
        $totalHpp         = (float) ($periodStats && isset($periodStats->total_hpp) ? $periodStats->total_hpp : 0);
        $grossProfit      = $netSales - $totalHpp;
        $margin           = $netSales > 0.0 ? round(($grossProfit / $netSales) * 100, 1) : 0.0;
        $totalBillPeriod  = (float) ($periodStats && isset($periodStats->total_bill) ? $periodStats->total_bill : 0);
        $transactionCount = (int) ($periodStats && isset($periodStats->transactions) ? $periodStats->transactions : 0);

        $itemsSold = (float) OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'success')
            ->where('orders.created_at', '>=', $startDate->toDateTimeString())
            ->where('orders.created_at', '<=', $endOfDay->toDateTimeString())
            ->sum('order_details.quantity');

        // 3. Tren Harian dengan Zero-Filling
        $rawTrend = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate->toDateTimeString())
            ->where('created_at', '<=', $endOfDay->toDateTimeString())
            ->select(
                DB::raw('DATE(created_at) AS date'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('COALESCE(SUM(total_bill), 0) AS total'),
                DB::raw("COALESCE(SUM({$cleanOrderProfitExpr}), 0) AS profit")
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('date');

        $dailyTrend = [];
        $periodRange = CarbonPeriod::create($startDate->toDateString(), $now->toDateString());
        foreach ($periodRange as $dateObj) {
            $dStr = $dateObj->format('Y-m-d');
            $row  = $rawTrend->get($dStr);
            $dailyTrend[] = [
                'date'   => $dStr,
                'label'  => $dateObj->format('d M'),
                'count'  => $row ? (int) $row->count : 0,
                'total'  => $row ? (float) $row->total : 0.0,
                'profit' => $row ? (float) $row->profit : 0.0,
            ];
        }

        // 3b. Tren Bulanan 12 Bulan dalam Satu Tahun (Net Profit = Harga Bersih - Tax - HPP)
        $rawMonthly = Order::where('status', 'success')
            ->whereYear('created_at', $selectedYear)
            ->selectRaw("
                MONTH(created_at) AS month_num,
                COUNT(*) AS transactions,
                COALESCE(SUM({$cleanOrderSalesExpr}), 0) AS net_sales,
                COALESCE(SUM(total_cost), 0) AS total_cost,
                COALESCE(SUM({$cleanOrderProfitExpr}), 0) AS net_profit
            ")
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy(DB::raw('MONTH(created_at)'))
            ->get()
            ->keyBy('month_num');

        $monthLabels = [
            1  => 'Jan',
            2  => 'Feb',
            3  => 'Mar',
            4  => 'Apr',
            5  => 'Mei',
            6  => 'Jun',
            7  => 'Jul',
            8  => 'Agu',
            9  => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        $monthlyTrend = [];
        for ($m = 1; $m <= 12; $m++) {
            $mRow = $rawMonthly->get($m);
            $mNetSales  = $mRow ? (float) $mRow->net_sales : 0.0;
            $mTotalCost = $mRow ? (float) $mRow->total_cost : 0.0;
            $mNetProfit = $mRow ? (float) $mRow->net_profit : 0.0;

            $monthlyTrend[] = [
                'month'        => $m,
                'label'        => $monthLabels[$m],
                'transactions' => $mRow ? (int) $mRow->transactions : 0,
                'net_sales'    => round($mNetSales, 2),
                'total_cost'   => round($mTotalCost, 2),
                'net_profit'   => round($mNetProfit, 2),
            ];
        }

        $availableYears = Order::where('status', 'success')
            ->selectRaw('DISTINCT YEAR(created_at) AS yr')
            ->whereNotNull('created_at')
            ->orderByDesc('yr')
            ->pluck('yr')
            ->map(function ($y) {
                return (int) $y;
            })
            ->values()
            ->all();

        if (!in_array((int) $now->year, $availableYears, true)) {
            array_unshift($availableYears, (int) $now->year);
        }
        if (!in_array($selectedYear, $availableYears, true)) {
            $availableYears[] = $selectedYear;
            rsort($availableYears);
        }

        // 4. Produk & Bahan Terlaris (Berdasarkan Omzet & Profit)
        $idExpr         = 'COALESCE(order_details.product_id, order_details.raw_material_id)';
        $prodTypeExpr   = "CASE WHEN order_details.raw_material_id IS NOT NULL THEN 'raw_material' ELSE COALESCE(products.product_type, 'retail') END";
        $sourceTypeExpr = "CASE WHEN order_details.raw_material_id IS NOT NULL THEN 'raw_material' ELSE 'product' END";
        $uniqueKeyExpr  = "CONCAT(CASE WHEN order_details.raw_material_id IS NOT NULL THEN 'rm_' ELSE 'prod_' END, COALESCE(order_details.product_id, order_details.raw_material_id, order_details.id))";
        $titleExpr      = "COALESCE(order_details.item_name, products.title, raw_materials.name, 'Item')";

        $topProducts = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('raw_materials', 'order_details.raw_material_id', '=', 'raw_materials.id')
            ->where('orders.status', 'success')
            ->where('orders.created_at', '>=', $startDate->toDateTimeString())
            ->where('orders.created_at', '<=', $endOfDay->toDateTimeString())
            ->select(
                DB::raw("{$idExpr} AS id"),
                DB::raw("{$prodTypeExpr} AS product_type"),
                DB::raw("{$sourceTypeExpr} AS source_type"),
                DB::raw("{$uniqueKeyExpr} AS unique_key"),
                DB::raw("{$titleExpr} AS title"),
                DB::raw('COALESCE(SUM(order_details.quantity), 0) AS sold_qty'),
                DB::raw('COALESCE(SUM(order_details.subtotal), 0) AS revenue'),
                DB::raw('COALESCE(SUM(order_details.subtotal - (order_details.cost_product * order_details.quantity)), 0) AS profit')
            )
            ->groupBy(
                DB::raw($uniqueKeyExpr),
                DB::raw($idExpr),
                DB::raw($prodTypeExpr),
                DB::raw($sourceTypeExpr),
                DB::raw($titleExpr)
            )
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id'           => isset($item->id) ? (int) $item->id : null,
                    'product_type' => isset($item->product_type) ? (string) $item->product_type : 'retail',
                    'source_type'  => isset($item->source_type) ? (string) $item->source_type : 'product',
                    'unique_key'   => isset($item->unique_key) ? (string) $item->unique_key : '',
                    'title'        => isset($item->title) ? (string) $item->title : 'Item',
                    'sold_qty'     => (float) (isset($item->sold_qty) ? $item->sold_qty : 0),
                    'revenue'      => (float) (isset($item->revenue) ? $item->revenue : 0),
                    'profit'       => (float) (isset($item->profit) ? $item->profit : 0),
                ];
            })
            ->values()
            ->all();

        // 5. Porsi per Kategori
        $categoryExpr = "COALESCE(categories.name, 'Bahan Dijual')";
        $categoryShare = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_details.product_id', '=', 'products.id')
            ->leftJoin('raw_materials', 'order_details.raw_material_id', '=', 'raw_materials.id')
            ->leftJoin('categories', function ($join) {
                $join->on(
                    DB::raw('COALESCE(products.category_id, raw_materials.category_id)'),
                    '=',
                    'categories.id'
                );
            })
            ->where('orders.status', 'success')
            ->where('orders.created_at', '>=', $startDate->toDateTimeString())
            ->where('orders.created_at', '<=', $endOfDay->toDateTimeString())
            ->select(
                DB::raw("{$categoryExpr} AS name"),
                DB::raw('COALESCE(SUM(order_details.quantity), 0) AS qty'),
                DB::raw('COALESCE(SUM(order_details.subtotal), 0) AS revenue')
            )
            ->groupBy(DB::raw($categoryExpr))
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($row) {
                return [
                    'name'    => isset($row->name) ? (string) $row->name : 'Bahan Dijual',
                    'qty'     => (float) (isset($row->qty) ? $row->qty : 0),
                    'revenue' => (float) (isset($row->revenue) ? $row->revenue : 0),
                ];
            })
            ->values()
            ->all();

        // 6. Metode Pembayaran & Platform
        $paymentMethods = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate->toDateTimeString())
            ->where('created_at', '<=', $endOfDay->toDateTimeString())
            ->select(
                'payment_method',
                DB::raw('COUNT(*) AS count'),
                DB::raw('COALESCE(SUM(total_bill), 0) AS total')
            )
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                return [
                    'payment_method' => isset($row->payment_method) ? (string) $row->payment_method : 'CASH',
                    'count'          => (int) (isset($row->count) ? $row->count : 0),
                    'total'          => (float) (isset($row->total) ? $row->total : 0),
                ];
            })
            ->values()
            ->all();

        $platformExpr = "COALESCE(platform, 'Offline Store')";
        $platformShare = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate->toDateTimeString())
            ->where('created_at', '<=', $endOfDay->toDateTimeString())
            ->select(
                DB::raw("{$platformExpr} AS platform"),
                DB::raw('COUNT(*) AS count'),
                DB::raw('COALESCE(SUM(total_bill), 0) AS total')
            )
            ->groupBy(DB::raw($platformExpr))
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                return [
                    'platform' => isset($row->platform) ? (string) $row->platform : 'Offline Store',
                    'count'    => (int) (isset($row->count) ? $row->count : 0),
                    'total'    => (float) (isset($row->total) ? $row->total : 0),
                ];
            })
            ->values()
            ->all();

        // 7. Analisis Jam Sibuk (Peak Hours)
        $peakHours = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate->toDateTimeString())
            ->where('created_at', '<=', $endOfDay->toDateTimeString())
            ->select(
                DB::raw('HOUR(created_at) AS hour'),
                DB::raw('COUNT(*) AS count'),
                DB::raw('COALESCE(SUM(total_bill), 0) AS total')
            )
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get()
            ->map(function ($row) {
                return [
                    'hour'  => (int) (isset($row->hour) ? $row->hour : 0),
                    'count' => (int) (isset($row->count) ? $row->count : 0),
                    'total' => (float) (isset($row->total) ? $row->total : 0),
                ];
            })
            ->values()
            ->all();

        // 8. Bahan Baku & Produk Retail Menipis
        $lowRawMaterials = RawMaterial::whereColumn('stock', '<=', 'min_stock')
            ->get()
            ->map(function ($rm) {
                return [
                    'id'         => (int) $rm->id,
                    'name'       => (string) $rm->name,
                    'unit'       => (string) $rm->unit,
                    'stock'      => (float) $rm->stock,
                    'min_stock'  => (float) $rm->min_stock,
                    'cost_price' => (float) $rm->cost_price,
                ];
            })
            ->values()
            ->all();

        $lowRetailProducts = Product::where('product_type', 'retail')
            ->where('stock', '<=', 5)
            ->select('id', 'title', 'stock', 'unit')
            ->get()
            ->map(function ($prod) {
                return [
                    'id'    => (int) $prod->id,
                    'title' => (string) $prod->title,
                    'stock' => (int) $prod->stock,
                    'unit'  => (string) $prod->unit,
                ];
            })
            ->values()
            ->all();

        // 9. Valuasi Stok Saat Ini (Berbasis HPP / cost_price)
        $rawMaterialsAll = RawMaterial::select('id', 'name', 'stock', 'cost_price')->get();
        $rawMaterialValuation = $rawMaterialsAll->sum(function ($rm) {
            $stock = isset($rm->stock) ? (float) $rm->stock : 0.0;
            $cost  = isset($rm->cost_price) ? (float) $rm->cost_price : 0.0;
            return max(0.0, $stock) * max(0.0, $cost);
        });

        $nonRecipeProductsAll = Product::select('id', 'title', 'product_type', 'stock', 'cost_price')->get()
            ->filter(function ($prod) {
                $pType = strtolower(trim((string) (isset($prod->product_type) ? $prod->product_type : 'retail')));
                return !in_array($pType, ['resep', 'recipe'], true) && $prod->stock !== null;
            });

        $nonRecipeProductValuation = $nonRecipeProductsAll->sum(function ($prod) {
            $stock = isset($prod->stock) ? (float) $prod->stock : 0.0;
            $cost  = isset($prod->cost_price) ? (float) $prod->cost_price : 0.0;
            return max(0.0, $stock) * max(0.0, $cost);
        });

        $totalInventoryValuation = $rawMaterialValuation + $nonRecipeProductValuation;

        $payload = $sanitizeUtf8([
            'today' => [
                'revenue'    => $todayRevenue,
                'orders'     => $todayCount,
                'avg_basket' => $todayCount > 0 ? round($todayRevenue / $todayCount) : 0,
            ],
            'kpi' => [
                'net_sales'           => $netSales,
                'net_sales_growth'    => $calcGrowth($netSales, (float) (isset($prevStats->net_sales) ? $prevStats->net_sales : 0)),
                'transactions'        => $transactionCount,
                'transactions_growth' => $calcGrowth($transactionCount, (int) (isset($prevStats->transactions) ? $prevStats->transactions : 0)),
                'hpp'                 => $totalHpp,
                'total_discount'      => (float) (isset($periodStats->total_discount) ? $periodStats->total_discount : 0),
                'total_tax_deduction' => (float) (isset($periodStats->total_tax_deduction) ? $periodStats->total_tax_deduction : 0),
                'items_sold'          => $itemsSold,
                'gross_profit'        => $grossProfit,
                'profit_growth'       => $calcGrowth($grossProfit, (float) (isset($prevStats->gross_profit) ? $prevStats->gross_profit : 0)),
                'margin_percent'      => $margin,
                'avg_transaction'     => $transactionCount > 0 ? round($totalBillPeriod / $transactionCount) : 0,
                'total_bill'          => $totalBillPeriod,
            ],
            'inventory_valuation' => [
                'total_valuation'              => round($totalInventoryValuation, 2),
                'raw_material_valuation'       => round($rawMaterialValuation, 2),
                'raw_material_count'           => $rawMaterialsAll->count(),
                'non_recipe_product_valuation' => round($nonRecipeProductValuation, 2),
                'non_recipe_product_count'     => $nonRecipeProductsAll->count(),
            ],
            'selected_year'       => $selectedYear,
            'available_years'     => $availableYears,
            'daily_trend'         => $dailyTrend,
            'monthly_trend'       => $monthlyTrend,
            'top_products'        => $topProducts,
            'category_share'      => $categoryShare,
            'payment_methods'     => $paymentMethods,
            'platform_share'      => $platformShare,
            'peak_hours'          => $peakHours,
            'low_stocks'          => $lowRawMaterials,
            'low_retail_products' => $lowRetailProducts,
        ]);

        return response()->json($payload);
    }
}
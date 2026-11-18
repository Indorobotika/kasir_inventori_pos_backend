<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\RawMaterial;
use App\Models\Product;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request)
    {
        $days      = max(1, (int) ($request->days ?? 30));
        $startDate = now()->subDays($days - 1)->startOfDay();
        $prevStart = (clone $startDate)->subDays($days);
        $prevEnd   = (clone $startDate)->subSecond();
        $today     = now()->toDateString();

        // 1. Ringkasan Hari Ini (Agregasi SQL langsung)
        $todayStats = Order::where('status', 'success')
            ->whereDate('created_at', $today)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total_bill), 0) as revenue')
            ->first();

        $todayRevenue = (float) $todayStats->revenue;
        $todayCount   = (int) $todayStats->orders;

        // 2. Ringkasan Periode Berjalan (KPI)
        $periodStats = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as transactions,
                COALESCE(SUM(subtotal - discount_amount), 0) as net_sales,
                COALESCE(SUM(total_cost), 0) as total_hpp,
                COALESCE(SUM(total_bill), 0) as total_bill,
                COALESCE(SUM(discount_amount), 0) as total_discount
            ')
            ->first();

        // Periode Sebelumnya (untuk indikator pertumbuhan %)
        $prevStats = Order::where('status', 'success')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->selectRaw('
                COUNT(*) as transactions,
                COALESCE(SUM(subtotal - discount_amount), 0) as net_sales,
                COALESCE(SUM(subtotal - discount_amount - total_cost), 0) as gross_profit
            ')
            ->first();

        $netSales         = (float) $periodStats->net_sales;
        $totalHpp         = (float) $periodStats->total_hpp;
        $grossProfit      = $netSales - $totalHpp;
        $margin           = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 1) : 0;
        $totalBillPeriod  = (float) $periodStats->total_bill;
        $transactionCount = (int) $periodStats->transactions;

        $itemsSold = (int) OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->where('orders.status', 'success')
            ->where('orders.created_at', '>=', $startDate)
            ->sum('order_details.quantity');

        // Helper hitung persentase pertumbuhan
        $calcGrowth = function ($current, $previous) {
            if ($previous <= 0) return $current > 0 ? 100 : 0;
            return round((($current - $previous) / $previous) * 100, 1);
        };

        // 3. Tren Harian dengan Zero-Filling (agar grafik tidak bolong pada hari tanpa penjualan)
        $rawTrend = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_bill) as total'),
                DB::raw('SUM(subtotal - discount_amount - total_cost) as profit')
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $dailyTrend = [];
        $periodRange = CarbonPeriod::create($startDate->toDateString(), now()->toDateString());
        foreach ($periodRange as $dateObj) {
            $dStr = $dateObj->format('Y-m-d');
            $row  = $rawTrend->get($dStr);
            $dailyTrend[] = [
                'date'   => $dStr,
                'label'  => $dateObj->format('d M'),
                'count'  => $row ? (int) $row->count : 0,
                'total'  => $row ? (float) $row->total : 0,
                'profit' => $row ? (float) $row->profit : 0,
            ];
        }

        // 4. Produk Terlaris (Berdasarkan Omzet & Profit)
        $topProducts = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->where('orders.status', 'success')
            ->where('orders.created_at', '>=', $startDate)
            ->select(
                'products.id',
                'products.title',
                'products.product_type',
                DB::raw('SUM(order_details.quantity) as sold_qty'),
                DB::raw('SUM(order_details.subtotal) as revenue'),
                DB::raw('SUM(order_details.subtotal - (order_details.cost_product * order_details.quantity)) as profit')
            )
            ->groupBy('products.id', 'products.title', 'products.product_type')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // 5. Porsi per Kategori
        $categoryShare = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.status', 'success')
            ->where('orders.created_at', '>=', $startDate)
            ->select(
                'categories.name',
                DB::raw('SUM(order_details.quantity) as qty'),
                DB::raw('SUM(order_details.subtotal) as revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get();

        // 6. Metode Pembayaran & Platform
        $paymentMethods = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate)
            ->select(
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_bill) as total')
            )
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        $platformShare = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw("COALESCE(platform, 'Offline Store') as platform"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_bill) as total')
            )
            ->groupBy('platform')
            ->orderByDesc('total')
            ->get();

        // 7. Analisis Jam Sibuk (Peak Hours)
        $peakHours = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_bill) as total')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // 8. Bahan Baku & Produk Retail Menipis
        $lowRawMaterials = RawMaterial::whereColumn('stock', '<=', 'min_stock')->get();
        $lowRetailProducts = Product::where('product_type', 'retail')
            ->where('stock', '<=', 5)
            ->select('id', 'title', 'stock', 'unit')
            ->get();

        return response()->json([
            'today' => [
                'revenue'    => $todayRevenue,
                'orders'     => $todayCount,
                'avg_basket' => $todayCount > 0 ? round($todayRevenue / $todayCount) : 0,
            ],
            'kpi' => [
                'net_sales'           => $netSales,
                'net_sales_growth'    => $calcGrowth($netSales, (float) $prevStats->net_sales),
                'transactions'        => $transactionCount,
                'transactions_growth' => $calcGrowth($transactionCount, (int) $prevStats->transactions),
                'hpp'                 => $totalHpp,
                'total_discount'      => (float) $periodStats->total_discount,
                'items_sold'          => $itemsSold,
                'gross_profit'        => $grossProfit,
                'profit_growth'       => $calcGrowth($grossProfit, (float) $prevStats->gross_profit),
                'margin_percent'      => $margin,
                'avg_transaction'     => $transactionCount > 0 ? round($totalBillPeriod / $transactionCount) : 0,
                'total_bill'          => $totalBillPeriod,
            ],
            'daily_trend'         => $dailyTrend,
            'top_products'        => $topProducts,
            'category_share'      => $categoryShare,
            'payment_methods'     => $paymentMethods,
            'platform_share'      => $platformShare,
            'peak_hours'          => $peakHours,
            'low_stocks'          => $lowRawMaterials,
            'low_retail_products' => $lowRetailProducts,
        ]);
    }
}
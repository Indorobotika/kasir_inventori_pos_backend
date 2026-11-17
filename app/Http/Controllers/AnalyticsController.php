<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\RawMaterial;

class AnalyticsController extends Controller 
{
    public function dashboard(Request $request) 
    {
        $days = (int) ($request->days ?? 30);
        $startDate = now()->subDays($days)->startOfDay();
        $today = now()->toDateString();

        // 1. Ringkasan Hari Ini
        $todayOrders = Order::where('status', 'success')->whereDate('created_at', $today)->get();
        $todayRevenue = (float) $todayOrders->sum('total_bill');
        $todayCount = $todayOrders->count();

        // 2. Ringkasan Periode (KPI)
        $periodOrders = Order::where('status', 'success')->where('created_at', '>=', $startDate)->get();
        $netSales = (float) ($periodOrders->sum('subtotal') - $periodOrders->sum('discount_amount'));
        $totalHpp = (float) $periodOrders->sum('total_cost');
        $grossProfit = $netSales - $totalHpp;
        $margin = $netSales > 0 ? round(($grossProfit / $netSales) * 100) : 0;
        $totalBillPeriod = (float) $periodOrders->sum('total_bill');
        $transactionCount = $periodOrders->count();

        // 3. Tren Harian
        $dailyTrend = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_bill) as total'),
                DB::raw('SUM(subtotal - discount_amount - total_cost) as profit')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 4. Produk Terlaris
        $topProducts = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->where('orders.status', 'success')
            ->where('orders.created_at', '>=', $startDate)
            ->select(
                'products.id',
                'products.title',
                DB::raw('SUM(order_details.quantity) as sold_qty'),
                DB::raw('SUM(order_details.subtotal) as revenue'),
                DB::raw('SUM(order_details.subtotal - (order_details.cost_product * order_details.quantity)) as profit')
            )
            ->groupBy('products.id', 'products.title')
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
                DB::raw('SUM(order_details.subtotal) as revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get();

        // 6. Metode Pembayaran
        $paymentMethods = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate)
            ->select(
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_bill) as total')
            )
            ->groupBy('payment_method')
            ->get();

        // 7. Bahan Baku Menipis
        $lowStocks = RawMaterial::whereColumn('stock', '<=', 'min_stock')->get();

        return response()->json([
            'today' => [
                'revenue' => $todayRevenue,
                'orders' => $todayCount,
                'avg_basket' => $todayCount > 0 ? round($todayRevenue / $todayCount) : 0
            ],
            'kpi' => [
                'net_sales' => $netSales,
                'transactions' => $transactionCount,
                'hpp' => $totalHpp,
                'items_sold' => (int) OrderDetail::whereIn('order_id', $periodOrders->pluck('id'))->sum('quantity'),
                'gross_profit' => $grossProfit,
                'margin_percent' => $margin,
                'avg_transaction' => $transactionCount > 0 ? round($totalBillPeriod / $transactionCount) : 0,
                'total_bill' => $totalBillPeriod,
            ],
            'daily_trend' => $dailyTrend,
            'top_products' => $topProducts,
            'category_share' => $categoryShare,
            'payment_methods' => $paymentMethods,
            'low_stocks' => $lowStocks,
        ]);
    }
}
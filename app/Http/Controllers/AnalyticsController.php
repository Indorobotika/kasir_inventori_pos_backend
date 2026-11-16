<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\RawMaterial;

class AnalyticsController extends Controller {
    public function dashboard(Request $request) {
        $days = (int) ($request->days ?? 30);
        $startDate = now()->subDays($days)->startOfDay();
        $today = now()->toDateString();

        $todayOrders = Order::where('status', 'success')->whereDate('created_at', $today)->get();
        $todayRevenue = $todayOrders->sum('total_bill');
        $todayCount = $todayOrders->count();

        $periodOrders = Order::where('status', 'success')->where('created_at', '>=', $startDate)->get();
        $netSales = $periodOrders->sum('subtotal') - $periodOrders->sum('discount_amount');
        $totalHpp = $periodOrders->sum('total_cost');
        $grossProfit = $netSales - $totalHpp;
        $margin = $netSales > 0 ? round(($grossProfit / $netSales) * 100) : 0;
        $totalBillPeriod = $periodOrders->sum('total_bill');

        $dailyTrend = Order::where('status', 'success')
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_bill) as total'), DB::raw('SUM(subtotal - discount_amount - total_cost) as profit'))
            ->groupBy('date')->orderBy('date')->get();

        $topProducts = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->where('orders.status', 'success')->where('orders.created_at', '>=', $startDate)
            ->select('products.id', 'products.title', DB::raw('SUM(order_details.quantity) as sold_qty'), DB::raw('SUM(order_details.subtotal) as revenue'), DB::raw('SUM(order_details.subtotal - (order_details.cost_product * order_details.quantity)) as profit'))
            ->groupBy('products.id', 'products.title')->orderByDesc('revenue')->limit(10)->get();

        $categoryShare = OrderDetail::join('orders', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.status', 'success')->where('orders.created_at', '>=', $startDate)
            ->select('categories.name', DB::raw('SUM(order_details.subtotal) as revenue'))
            ->groupBy('categories.id', 'categories.name')->orderByDesc('revenue')->get();

        $paymentMethods = Order::where('status', 'success')->where('created_at', '>=', $startDate)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_bill) as total'))
            ->groupBy('payment_method')->get();

        $lowStocks = RawMaterial::whereColumn('stock', '<=', 'min_stock')->get();

        return response()->json([
            'today' => ['revenue' => $todayRevenue, 'orders' => $todayCount, 'avg_basket' => $todayCount > 0 ? round($todayRevenue / $todayCount) : 0],
            'kpi' => [
                'net_sales' => $netSales,
                'transactions' => $periodOrders->count(),
                'hpp' => $totalHpp,
                'items_sold' => OrderDetail::whereIn('order_id', $periodOrders->pluck('id'))->sum('quantity'),
                'gross_profit' => $grossProfit,
                'margin_percent' => $margin,
                'avg_transaction' => $periodOrders->count() > 0 ? round($totalBillPeriod / $periodOrders->count()) : 0,
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

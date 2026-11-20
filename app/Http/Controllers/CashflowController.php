<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Cashflow;

class CashflowController extends Controller
{
    /**
     * Bersihkan cache terkait analitik/cashflow jika ada.
     */
    private function clearCashflowCache()
    {
        $keys = [
            'analytics_dashboard',
            'cashflows_summary',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    public function index(Request $request)
    {
        $month = $request->query('month', date('Y-m'));

        $records = Cashflow::where('transaction_date', 'like', $month . '%')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $in      = (float) $records->where('type', 'IN')->sum('amount');
        $out     = (float) $records->where('type', 'OUT')->sum('amount');
        $balance = $in - $out;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary' => [
                    'in'      => $in,
                    'out'     => $out,
                    'balance' => $balance,
                ],
                'records' => $records,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'             => 'required|in:IN,OUT',
            'category'         => 'required|string|max:100',
            'amount'           => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'description'      => 'nullable|string|max:500',
        ]);

        $cf = Cashflow::create([
            'user_id'          => $request->user() ? $request->user()->id : null,
            'type'             => $request->type,
            'category'         => trim($request->category),
            'amount'           => (float) $request->amount,
            'description'      => $request->description ? trim($request->description) : null,
            'transaction_date' => $request->transaction_date,
        ]);

        $this->clearCashflowCache();

        return response()->json([
            'status'  => 'success',
            'message' => 'Catatan cashflow berhasil disimpan',
            'data'    => $cf,
        ]);
    }

    public function destroy($id)
    {
        $cashflow = Cashflow::findOrFail($id);
        $cashflow->delete();

        $this->clearCashflowCache();

        return response()->json([
            'status'  => 'success',
            'message' => 'Cashflow berhasil dihapus',
        ]);
    }
}
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cashflow;

class CashflowController extends Controller {
    public function index(Request $request) {
        $month = $request->query('month', date('Y-m'));
        
        $records = Cashflow::where('transaction_date', 'like', $month . '%')
                           ->orderByDesc('transaction_date')
                           ->orderByDesc('id')
                           ->get();
                           
        $in = $records->where('type', 'IN')->sum('amount');
        $out = $records->where('type', 'OUT')->sum('amount');
        $balance = $in - $out;

        // Dibungkus agar selaras dengan helper extractData / format standar Vuex
        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'in' => $in, 
                    'out' => $out, 
                    'balance' => $balance
                ], 
                'records' => $records
            ]
        ]);
    }

    public function store(Request $request) {
        $request->validate([
            'type'             => 'required|in:IN,OUT',
            'category'         => 'required|string|max:100',
            'amount'           => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'description'      => 'nullable|string|max:500',
        ]);

        $cf = Cashflow::create([
            'user_id'          => $request->user()->id,
            'type'             => $request->type,
            'category'         => $request->category,
            'amount'           => $request->amount,
            'description'      => $request->description,
            'transaction_date' => $request->transaction_date,
        ]);

        return response()->json(['status' => 'success', 'data' => $cf]);
    }

    public function destroy($id) {
        $cashflow = Cashflow::findOrFail($id);
        $cashflow->delete();
        
        return response()->json(['status' => 'success', 'message' => 'Cashflow berhasil dihapus']);
    }
}
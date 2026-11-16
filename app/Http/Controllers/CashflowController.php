<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Cashflow;

class CashflowController extends Controller {
    public function index(Request $request) {
        $month = $request->month ?? date('Y-m');
        $records = Cashflow::where('transaction_date', 'like', $month . '%')->orderByDesc('transaction_date')->get();
        $in = $records->where('type', 'IN')->sum('amount');
        $out = $records->where('type', 'OUT')->sum('amount');
        return response()->json(['summary' => ['in' => $in, 'out' => $out, 'balance' => $in - $out], 'records' => $records]);
    }

    public function store(Request $request) {
        $cf = Cashflow::create([
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'category' => $request->category,
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->transaction_date ?? date('Y-m-d'),
        ]);
        return response()->json(['status' => 'success', 'data' => $cf]);
    }

    public function destroy($id) {
        Cashflow::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }
}

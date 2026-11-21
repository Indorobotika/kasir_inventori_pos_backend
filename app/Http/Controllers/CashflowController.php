<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Cashflow;
use App\Models\Order;

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

    /**
     * Deteksi sumber dana riil (CASH vs REKENING) dari teks kategori/keterangan.
     */
    private function detectSource(Cashflow $row)
    {
        $text = strtolower(trim(
            ($row->category ?? '') . ' ' . ($row->description ?? '') . ' ' . ($row->payment_method ?? '')
        ));

        if (strpos($text, 'cash') !== false || strpos($text, 'tunai') !== false) {
            return 'CASH';
        }

        if (
            strpos($text, 'qris') !== false ||
            strpos($text, 'card') !== false ||
            strpos($text, 'transfer') !== false ||
            strpos($text, 'rekening') !== false ||
            strpos($text, 'bank') !== false ||
            strpos($text, 'shopee') !== false ||
            strpos($text, 'tokopedia') !== false
        ) {
            return 'REKENING';
        }

        return 'CASH';
    }

    public function index(Request $request)
    {
        $month        = $request->query('month', date('Y-m'));
        $allowedSizes = [10, 20, 50];
        $perPage      = (int) $request->query('per_page', 10);
        if (!in_array($perPage, $allowedSizes, true)) {
            $perPage = 10;
        }

        $typeFilter   = strtoupper(trim((string) $request->query('type', 'ALL')));
        $sourceFilter = strtoupper(trim((string) $request->query('source', 'ALL')));

        // Ambil seluruh catatan pada bulan terkait untuk menghitung ringkasan saldo riil bulan ini
        $allMonthRecords = Cashflow::where('transaction_date', 'like', $month . '%')
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $in      = (float) $allMonthRecords->where('type', 'IN')->sum('amount');
        $out     = (float) $allMonthRecords->where('type', 'OUT')->sum('amount');
        $balance = $in - $out;

        $cashBalance = (float) $allMonthRecords->sum(function ($r) {
            if ($this->detectSource($r) !== 'CASH') return 0;
            return $r->type === 'IN' ? (float) $r->amount : -((float) $r->amount);
        });

        $bankBalance = (float) $allMonthRecords->sum(function ($r) {
            if ($this->detectSource($r) !== 'REKENING') return 0;
            return $r->type === 'IN' ? (float) $r->amount : -((float) $r->amount);
        });

        // Hitung Dana CSR terkumpul & tersalurkan pada bulan terpilih
        try {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateTimeString();
            $monthEnd   = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateTimeString();
        } catch (\Exception $e) {
            $monthStart = Carbon::now()->startOfMonth()->toDateTimeString();
            $monthEnd   = Carbon::now()->endOfMonth()->toDateTimeString();
        }

        $csrCollected = round((float) Order::where('status', 'success')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('csr_amount'), 2);

        $csrDisbursed = round((float) $allMonthRecords
            ->where('type', 'OUT')
            ->filter(function ($r) {
                return strtolower(trim((string) ($r->category ?? ''))) === 'dana csr';
            })
            ->sum('amount'), 2);

        // Saldo sisa Dana CSR kumulatif sepanjang waktu
        $totalCsrCollectedAll = (float) Order::where('status', 'success')->sum('csr_amount');
        $totalCsrDisbursedAll = (float) Cashflow::where('type', 'OUT')
            ->whereRaw("LOWER(TRIM(COALESCE(category, ''))) = 'dana csr'")
            ->sum('amount');
        $csrRemaining = max(0, round($totalCsrCollectedAll - $totalCsrDisbursedAll, 2));

        // Query terurut descending berdasarkan tanggal masuk terbaru paling atas
        $query = Cashflow::where('transaction_date', 'like', $month . '%')
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (in_array($typeFilter, ['IN', 'OUT'], true)) {
            $query->where('type', $typeFilter);
        }

        if ($sourceFilter === 'CASH') {
            $query->where(function ($q) {
                $q->whereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) LIKE '%cash%'")
                  ->orWhereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) LIKE '%tunai%'")
                  ->orWhere(function ($sub) {
                      $sub->whereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) NOT LIKE '%qris%'")
                          ->whereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) NOT LIKE '%card%'")
                          ->whereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) NOT LIKE '%transfer%'")
                          ->whereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) NOT LIKE '%rekening%'")
                          ->whereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) NOT LIKE '%bank%'")
                          ->whereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) NOT LIKE '%shopee%'")
                          ->whereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) NOT LIKE '%tokopedia%'");
                  });
            });
        } elseif ($sourceFilter === 'REKENING') {
            $query->where(function ($q) {
                $q->whereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) LIKE '%qris%'")
                  ->orWhereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) LIKE '%card%'")
                  ->orWhereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) LIKE '%transfer%'")
                  ->orWhereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) LIKE '%rekening%'")
                  ->orWhereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) LIKE '%bank%'")
                  ->orWhereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) LIKE '%shopee%'")
                  ->orWhereRaw("LOWER(CONCAT(COALESCE(category, ''), ' ', COALESCE(description, ''))) LIKE '%tokopedia%'");
            });
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary' => [
                    'in'            => $in,
                    'out'           => $out,
                    'balance'       => $balance,
                    'cash_balance'  => $cashBalance,
                    'bank_balance'  => $bankBalance,
                    'csr_collected' => $csrCollected,
                    'csr_disbursed' => $csrDisbursed,
                    'csr_remaining' => $csrRemaining,
                ],
                'records'    => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'from'         => $paginated->firstItem() ?? 0,
                    'to'           => $paginated->lastItem() ?? 0,
                ],
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
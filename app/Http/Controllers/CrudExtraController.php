<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Models\Promotion;
use App\Models\Setting;

class CrudExtraController extends Controller {
    
    protected $cacheTtl = 3600;

    public function index() { 
        $promotions = Cache::remember('promotions_all', $this->cacheTtl, function () {
            return Promotion::all();
        });

        return response()->json(['data' => $promotions]); 
    }

    public function store(Request $request) {
        $request->validate([
            'title'            => 'required|string|max:255',
            'typ_promotion_id' => 'required|in:1,2',
            'required'         => 'required|numeric|min:0',
            'result'           => 'required|numeric|min:0',
        ]);

        $promo = Promotion::create($request->only([
            'title', 'typ_promotion_id', 'required', 'result', 'is_active'
        ]));

        Cache::forget('promotions_all');

        return response()->json(['status' => 'success', 'data' => $promo]);
    }

    public function update(Request $request, $id) {
        $promo = Promotion::findOrFail($id);
        
        $request->validate([
            'title'            => 'required|string|max:255',
            'typ_promotion_id' => 'required|in:1,2',
            'required'         => 'required|numeric|min:0',
            'result'           => 'required|numeric|min:0',
        ]);

        $promo->update($request->only([
            'title', 'typ_promotion_id', 'required', 'result', 'is_active'
        ]));

        Cache::forget('promotions_all');

        return response()->json(['status' => 'success', 'data' => $promo]);
    }

    public function destroy($id) {
        $promo = Promotion::findOrFail($id);
        $promo->delete();

        Cache::forget('promotions_all');

        return response()->json(['status' => 'success']);
    }

    public function getSettings() { 
        $setting = Cache::remember('store_settings', $this->cacheTtl, function () {
            return Setting::firstOrCreate(
                ['id' => 1],
                [
                    'store_name'     => 'Toko Baru',
                    'location_store' => '',
                    'contact'        => '',
                    'tax'            => 11,
                    'logo_path_url'  => '',
                    'qris_path_url'  => '', // Default QRIS URL
                    'receipt_width'  => '58 mm',
                    'receipt_footer' => 'Terima kasih telah berbelanja'
                ]
            );
        });

        return response()->json(['data' => $setting]); 
    }

    public function updateSettings(Request $request) {
        $validatedData = $request->validate([
            'store_name'     => 'required|string|max:255',
            'location_store' => 'nullable|string|max:500',
            'contact'        => 'nullable|string|max:50',
            'tax'            => 'required|numeric|min:0|max:100',
            'logo'           => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'qris'           => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Validasi file QRIS maks 2MB
            'receipt_width'  => 'required|string',
            'receipt_footer' => 'nullable|string|max:255',
        ]);

        $setting = Setting::firstOrCreate(['id' => 1]);

        // Proses penyimpanan file Logo
        if ($request->hasFile('logo')) {
            if ($setting->logo_path_url) {
                $oldPath = str_replace(asset('storage/'), '', $setting->logo_path_url);
                Storage::disk('public')->delete($oldPath);
            }
            $file = $request->file('logo');
            $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('logos', $filename, 'public');
            $validatedData['logo_path_url'] = asset('storage/' . $path);
        } else {
            unset($validatedData['logo']);
        }

        // Proses penyimpanan file QRIS ke folder payment
        if ($request->hasFile('qris')) {
            if ($setting->qris_path_url) {
                $oldQrisPath = str_replace(asset('storage/'), '', $setting->qris_path_url);
                Storage::disk('public')->delete($oldQrisPath);
            }
            $qrisFile = $request->file('qris');
            $qrisFilename = 'qris_' . time() . '.' . $qrisFile->getClientOriginalExtension();
            $qrisPath = $qrisFile->storeAs('payment', $qrisFilename, 'public'); // Disimpan di folder storage/app/public/payment
            $validatedData['qris_path_url'] = asset('storage/' . $qrisPath);
        } else {
            unset($validatedData['qris']);
        }

        $setting->update($validatedData);

        Cache::forget('store_settings');

        return response()->json([
            'status'  => 'success', 
            'message' => 'Pengaturan berhasil diperbarui',
            'data'    => $setting
        ]);
    }
}
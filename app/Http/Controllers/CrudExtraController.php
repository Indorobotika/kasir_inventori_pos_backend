<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Promotion;
use App\Models\Setting;

class CrudExtraController extends Controller
{
    protected $cacheTtl = 3600;

    /**
     * Daftar folder publik fisik yang harus disinkronkan saat symlink tidak aktif.
     */
    private function getPublicMirrorDirs()
    {
        $dirs = [
            public_path('storage'),
        ];

        // Contoh jika ada folder publik terpisah di cPanel, aktifkan & sesuaikan jika perlu:
        // $extraDir = '/home/USER_CPANEL/kasir.indorobotika.com/storage';
        // if (is_dir(dirname($extraDir))) {
        //     $dirs[] = $extraDir;
        // }

        $normalized = array();
        foreach ($dirs as $d) {
            $normalized[] = rtrim($d, '/\\');
        }

        return array_values(array_unique($normalized));
    }

    private function buildPublicStorageUrl(Request $request, $relativePath)
    {
        $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
        $host = $request->getHost();

        if ($host === 'localhost' || $host === '127.0.0.1') {
            return asset('public/storage/' . $relativePath);
        }

        $rootUrl = rtrim($request->getSchemeAndHttpHost(), '/');
        return $rootUrl . '/public/storage/' . $relativePath;
    }

    private function extractRelativeStoragePath($fullUrl)
    {
        if (!$fullUrl) {
            return null;
        }

        $path = parse_url($fullUrl, PHP_URL_PATH);
        $path = $path ? $path : '';
        $path = preg_replace('#^.*?/(?:public/)?storage/#', '', ltrim($path, '/'));
        $clean = ltrim((string) $path, '/');

        return $clean !== '' ? $clean : null;
    }

    /**
     * Salin file dari storage/app/public/{relativePath} ke seluruh mirror publik fisik.
     */
    private function syncFileToPublicMirrors($relativePath)
    {
        $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
        $source = storage_path('app/public/' . $relativePath);

        if (!is_file($source)) {
            return;
        }

        foreach ($this->getPublicMirrorDirs() as $baseDir) {
            $target = $baseDir . '/' . $relativePath;
            $targetDir = dirname($target);

            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }

            if (@copy($source, $target)) {
                @chmod($target, 0644);
            }
        }
    }

    /**
     * Hapus file dari disk 'public' maupun seluruh mirror publik fisik.
     */
    private function deleteMediaEverywhere($fullUrlOrRelativePath)
    {
        if (!$fullUrlOrRelativePath) {
            return;
        }

        $raw = (string) $fullUrlOrRelativePath;
        $isUrlOrAbsPath = (strpos($raw, '://') !== false) || (substr($raw, 0, 1) === '/');
        $relativePath = $isUrlOrAbsPath
            ? $this->extractRelativeStoragePath($raw)
            : ltrim(str_replace('\\', '/', $raw), '/');

        if (!$relativePath) {
            return;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }

        foreach ($this->getPublicMirrorDirs() as $baseDir) {
            $target = $baseDir . '/' . $relativePath;
            if (is_file($target) || is_link($target)) {
                @unlink($target);
            }
        }
    }

    public function index()
    {
        $promotions = Cache::remember('promotions_all', $this->cacheTtl, function () {
            return Promotion::all();
        });

        return response()->json(['data' => $promotions]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'typ_promotion_id' => 'required|in:1,2',
            'required'         => 'required|numeric|min:0',
            'result'           => 'required|numeric|min:0',
            'is_active'        => 'nullable|boolean',
        ]);

        $promo = Promotion::create([
            'title'            => $validated['title'],
            'typ_promotion_id' => $validated['typ_promotion_id'],
            'required'         => $validated['required'],
            'result'           => $validated['result'],
            'is_active'        => isset($validated['is_active']) ? (bool) $validated['is_active'] : true,
        ]);

        Cache::forget('promotions_all');

        return response()->json(['status' => 'success', 'data' => $promo]);
    }

    public function update(Request $request, $id)
    {
        $promo = Promotion::findOrFail($id);

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'typ_promotion_id' => 'required|in:1,2',
            'required'         => 'required|numeric|min:0',
            'result'           => 'required|numeric|min:0',
            'is_active'        => 'nullable|boolean',
        ]);

        $payload = [
            'title'            => $validated['title'],
            'typ_promotion_id' => $validated['typ_promotion_id'],
            'required'         => $validated['required'],
            'result'           => $validated['result'],
        ];

        if ($request->has('is_active')) {
            $payload['is_active'] = (bool) $validated['is_active'];
        }

        $promo->update($payload);

        Cache::forget('promotions_all');

        return response()->json(['status' => 'success', 'data' => $promo]);
    }

    public function destroy($id)
    {
        $promo = Promotion::findOrFail($id);
        $promo->delete();

        Cache::forget('promotions_all');

        return response()->json(['status' => 'success']);
    }

    public function getSettings()
    {
        $setting = Cache::remember('store_settings', $this->cacheTtl, function () {
            return Setting::firstOrCreate(
                ['id' => 1],
                [
                    'store_name'     => 'Toko Baru',
                    'location_store' => '',
                    'contact'        => '',
                    'tax'            => 11,
                    'logo_path_url'  => '',
                    'qris_path_url'  => '',
                    'receipt_width'  => '58 mm',
                    'receipt_footer' => 'Terima kasih telah berbelanja',
                ]
            );
        });

        return response()->json(['data' => $setting]);
    }

    public function updateSettings(Request $request)
    {
        $validatedData = $request->validate([
            'store_name'     => 'required|string|max:255',
            'location_store' => 'nullable|string|max:500',
            'contact'        => 'nullable|string|max:50',
            'tax'            => 'required|numeric|min:0|max:100',
            'logo'           => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'qris'           => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'remove_logo'    => 'nullable|boolean',
            'remove_qris'    => 'nullable|boolean',
            'receipt_width'  => 'required|string',
            'receipt_footer' => 'nullable|string|max:255',
        ]);

        $setting = Setting::firstOrCreate(['id' => 1]);

        $oldLogoUrl = $setting->logo_path_url;
        $oldQrisUrl = $setting->qris_path_url;
        $newLogoPath = null;
        $newQrisPath = null;

        unset($validatedData['logo'], $validatedData['qris'], $validatedData['remove_logo'], $validatedData['remove_qris']);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = 'logo_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            $newLogoPath = $file->storeAs('logos', $filename, 'public');
            $this->syncFileToPublicMirrors($newLogoPath);
            $validatedData['logo_path_url'] = $this->buildPublicStorageUrl($request, $newLogoPath);
        } elseif ($request->boolean('remove_logo')) {
            $validatedData['logo_path_url'] = '';
        }

        if ($request->hasFile('qris')) {
            $qrisFile = $request->file('qris');
            $qrisFilename = 'qris_' . time() . '_' . Str::random(6) . '.' . $qrisFile->getClientOriginalExtension();
            $newQrisPath = $qrisFile->storeAs('payment', $qrisFilename, 'public');
            $this->syncFileToPublicMirrors($newQrisPath);
            $validatedData['qris_path_url'] = $this->buildPublicStorageUrl($request, $newQrisPath);
        } elseif ($request->boolean('remove_qris')) {
            $validatedData['qris_path_url'] = '';
        }

        try {
            $setting->update($validatedData);
            $setting->refresh();
        } catch (\Exception $e) {
            if ($newLogoPath) {
                $this->deleteMediaEverywhere($newLogoPath);
            }
            if ($newQrisPath) {
                $this->deleteMediaEverywhere($newQrisPath);
            }
            throw $e;
        }

        if ($newLogoPath || $request->boolean('remove_logo')) {
            if ($oldLogoUrl && $oldLogoUrl !== $setting->logo_path_url) {
                $this->deleteMediaEverywhere($oldLogoUrl);
            }
        }

        if ($newQrisPath || $request->boolean('remove_qris')) {
            if ($oldQrisUrl && $oldQrisUrl !== $setting->qris_path_url) {
                $this->deleteMediaEverywhere($oldQrisUrl);
            }
        }

        Cache::forget('store_settings');

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengaturan berhasil diperbarui',
            'data'    => $setting,
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\RawMaterial;

class RawMaterialController extends Controller
{
    private function getPublicMirrorDirs()
    {
        $dirs = [
            public_path('storage'),
        ];

        $normalized = array();
        foreach ($dirs as $d) {
            $normalized[] = rtrim($d, '/\\');
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Bersihkan cache bahan baku & katalog kasir agar perubahan stok/harga jual langsung sinkron.
     */
    private function clearRawMaterialCache()
    {
        $keys = [
            'raw_materials_all',
            'products_all_all',
            'products_all_retail',
            'products_all_resep',
            'products_all_recipe',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    private function buildStorageUrl(Request $request, $relativePath)
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

    private function deleteImageEverywhere($fullUrlOrRelativePath)
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

    private function sanitizeEmptyCategoryInput(Request $request)
    {
        if ($request->has('category_id') && $request->input('category_id') === '') {
            $request->merge(['category_id' => null]);
        }
    }

    public function index()
    {
        $rawMaterials = RawMaterial::with('category')->orderByDesc('id')->get();
        return response()->json(['data' => $rawMaterials]);
    }

    public function store(Request $request)
    {
        $this->sanitizeEmptyCategoryInput($request);

        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:raw_materials,name',
            'unit'        => 'required|in:kg,gram,liter,ml,pcs,pack,box',
            'stock'       => 'required|numeric|min:0',
            'min_stock'   => 'required|numeric|min:0',
            'cost_price'  => 'required|numeric|min:0',
            'is_sellable' => 'nullable|boolean',
            'sell_price'  => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'cover'       => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $coverUrl = null;

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $filename = 'raw_material_' . time() . '_' . Str::random(4) . '.' . $file->getClientOriginalExtension();
            $newRelativePath = $file->storeAs('raw_materials', $filename, 'public');
            $this->syncFileToPublicMirrors($newRelativePath);
            $coverUrl = $this->buildStorageUrl($request, $newRelativePath);
        }

        $isSellable = $request->boolean('is_sellable');

        $rm = RawMaterial::create([
            'name'        => $validated['name'],
            'unit'        => $validated['unit'],
            'stock'       => (float) $validated['stock'],
            'min_stock'   => (float) $validated['min_stock'],
            'cost_price'  => (float) $validated['cost_price'],
            'is_sellable' => $isSellable,
            'sell_price'  => $isSellable ? (float) ($validated['sell_price'] ?? 0) : 0,
            'category_id' => $isSellable ? ($validated['category_id'] ?? null) : null,
            'cover'       => $coverUrl,
        ]);

        $this->clearRawMaterialCache();

        return response()->json([
            'status' => 'success',
            'data'   => $rm->load('category'),
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->sanitizeEmptyCategoryInput($request);
        $rm = RawMaterial::findOrFail($id);

        $validated = $request->validate([
            'name'         => 'required|string|max:255|unique:raw_materials,name,' . $id,
            'unit'         => 'required|in:kg,gram,liter,ml,pcs,pack,box',
            'stock'        => 'required|numeric|min:0',
            'min_stock'    => 'required|numeric|min:0',
            'cost_price'   => 'required|numeric|min:0',
            'is_sellable'  => 'nullable|boolean',
            'sell_price'   => 'nullable|numeric|min:0',
            'category_id'  => 'nullable|exists:categories,id',
            'cover'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'remove_cover' => 'nullable|boolean',
        ]);

        $oldCoverUrl = $rm->cover;
        $coverUrl = $oldCoverUrl;
        $shouldDeleteOld = false;

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $filename = 'raw_material_' . time() . '_' . Str::random(4) . '.' . $file->getClientOriginalExtension();
            $newRelativePath = $file->storeAs('raw_materials', $filename, 'public');
            $this->syncFileToPublicMirrors($newRelativePath);
            $coverUrl = $this->buildStorageUrl($request, $newRelativePath);
            $shouldDeleteOld = true;
        } elseif ($request->boolean('remove_cover')) {
            $coverUrl = null;
            $shouldDeleteOld = true;
        }

        $isSellable = $request->has('is_sellable') ? $request->boolean('is_sellable') : (bool) $rm->is_sellable;
        $categoryId = $isSellable
            ? (array_key_exists('category_id', $validated) ? $validated['category_id'] : $rm->category_id)
            : null;

        $rm->update([
            'name'        => $validated['name'],
            'unit'        => $validated['unit'],
            'stock'       => (float) $validated['stock'],
            'min_stock'   => (float) $validated['min_stock'],
            'cost_price'  => (float) $validated['cost_price'],
            'is_sellable' => $isSellable,
            'sell_price'  => $isSellable
                ? (float) ($validated['sell_price'] ?? $rm->sell_price)
                : 0,
            'category_id' => $categoryId,
            'cover'       => $coverUrl,
        ]);

        if ($shouldDeleteOld && $oldCoverUrl && $oldCoverUrl !== $coverUrl) {
            $this->deleteImageEverywhere($oldCoverUrl);
        }

        $this->clearRawMaterialCache();

        return response()->json([
            'status' => 'success',
            'data'   => $rm->load('category'),
        ]);
    }

    public function destroy($id)
    {
        $rm = RawMaterial::findOrFail($id);
        $oldCoverUrl = $rm->cover;

        $rm->delete();

        if ($oldCoverUrl) {
            $this->deleteImageEverywhere($oldCoverUrl);
        }

        $this->clearRawMaterialCache();

        return response()->json(['status' => 'success']);
    }

    public function stockOpname(Request $request, $id)
    {
        $rm = RawMaterial::findOrFail($id);

        $request->validate([
            'actual_stock' => 'required|numeric|min:0',
        ]);

        $rm->update(['stock' => (float) $request->actual_stock]);

        $this->clearRawMaterialCache();

        return response()->json([
            'status' => 'success',
            'data'   => $rm->load('category'),
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductRecipe;
use App\Models\ProductPricePlatform;

class ProductController extends Controller
{
    /**
     * Daftar folder publik fisik yang disinkronkan agar aman tanpa symlink.
     */
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
     * Bersihkan cache terkait produk dan kategori agar data selalu segar.
     */
    private function clearProductCache()
    {
        $keys = [
            'products_all_all',
            'products_all_retail',
            'products_all_resep',
            'products_all_recipe',
            'categories_all',
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

    private function syncProductRecipes($productId, $rawRecipes)
    {
        ProductRecipe::where('product_id', $productId)->delete();

        if (empty($rawRecipes)) {
            return;
        }

        $recipes = is_string($rawRecipes) ? json_decode($rawRecipes, true) : $rawRecipes;
        if (!is_array($recipes)) {
            return;
        }

        foreach ($recipes as $r) {
            if (
                !empty($r['raw_material_id']) &&
                isset($r['qty_needed']) &&
                (float) $r['qty_needed'] > 0
            ) {
                ProductRecipe::create([
                    'product_id'      => (int) $productId,
                    'raw_material_id' => (int) $r['raw_material_id'],
                    'qty_needed'      => (float) $r['qty_needed'],
                ]);
            }
        }
    }

    private function buildProductQuery($categoryId, $search = '')
    {
        $query = Product::with(['category', 'recipes.rawMaterial', 'platformPrices'])
            ->orderByDesc('id');

        if ($categoryId !== null) {
            $query->where('category_id', (int) $categoryId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('sku', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    public function categories(Request $request)
    {
        $categories = Category::orderByDesc('id')->get();

        return response()->json(['data' => $categories]);
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        $cat = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        $this->clearProductCache();

        return response()->json(['status' => 'success', 'data' => $cat]);
    }

    public function index(Request $request)
    {
        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $search     = trim((string) $request->get('q', ''));
        $products   = $this->buildProductQuery($categoryId, $search)->get();

        return response()->json(['data' => $products]);
    }

    public function productsByCategorySlug(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)->first();

        if (!$category) {
            return response()->json(['data' => []]);
        }

        $products = $this->buildProductQuery((int) $category->id)->get();

        return response()->json(['data' => $products]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id'  => 'required|exists:categories,id',
            'title'        => 'required|string|max:255',
            'unit'         => 'required|in:pcs,cup,botol,porsi,box,pack,bowl,plate',
            'price'        => 'required|numeric|min:0',
            'product_type' => 'required|in:retail,resep',
            'cover'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $sku = $request->sku ? $request->sku : ('SKU-' . strtoupper(Str::random(6)));

        $newRelativePath = null;
        $coverUrl = null;

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $filename = 'product_' . time() . '_' . Str::random(4) . '.' . $file->getClientOriginalExtension();
            $newRelativePath = $file->storeAs('products', $filename, 'public');
            $this->syncFileToPublicMirrors($newRelativePath);
            $coverUrl = $this->buildStorageUrl($request, $newRelativePath);
        }

        try {
            $product = DB::transaction(function () use ($request, $sku, $coverUrl) {
                $productType = strtolower($request->product_type);

                $product = Product::create([
                    'category_id'  => $request->category_id,
                    'sku'          => $sku,
                    'title'        => $request->title,
                    'unit'         => $request->unit,
                    'product_type' => $productType,
                    'cover'        => $coverUrl,
                    'cost_price'   => isset($request->cost_price) ? $request->cost_price : 0,
                    'price'        => $request->price,
                    'stock'        => $productType === 'resep' ? 0 : (isset($request->stock) ? $request->stock : 0),
                    'weight'       => isset($request->weight) ? $request->weight : 0,
                ]);

                if ($productType === 'resep' && $request->has('recipes')) {
                    $this->syncProductRecipes($product->id, $request->recipes);
                }

                return $product;
            });
        } catch (\Exception $e) {
            if ($newRelativePath) {
                $this->deleteImageEverywhere($newRelativePath);
            }
            throw $e;
        }

        $this->clearProductCache();

        return response()->json([
            'status' => 'success',
            'data'   => $product->load(['category', 'recipes.rawMaterial', 'platformPrices']),
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'category_id'  => 'required|exists:categories,id',
            'title'        => 'required|string|max:255',
            'unit'         => 'required|in:pcs,cup,botol,porsi,box,pack,bowl,plate',
            'price'        => 'required|numeric|min:0',
            'product_type' => 'required|in:retail,resep',
            'cover'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'remove_cover' => 'nullable|boolean',
        ]);

        $oldCoverUrl = $product->cover;
        $coverUrl = $oldCoverUrl;
        $newUploadedRelativePath = null;
        $shouldDeleteOld = false;

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $filename = 'product_' . time() . '_' . Str::random(4) . '.' . $file->getClientOriginalExtension();
            $newUploadedRelativePath = $file->storeAs('products', $filename, 'public');
            $this->syncFileToPublicMirrors($newUploadedRelativePath);
            $coverUrl = $this->buildStorageUrl($request, $newUploadedRelativePath);
            $shouldDeleteOld = true;
        } elseif ($request->boolean('remove_cover')) {
            $coverUrl = null;
            $shouldDeleteOld = true;
        }

        try {
            DB::transaction(function () use ($request, $product, $coverUrl) {
                $productType = strtolower($request->product_type);

                $product->update([
                    'category_id'  => $request->category_id,
                    'sku'          => $request->sku ? $request->sku : $product->sku,
                    'title'        => $request->title,
                    'unit'         => $request->unit,
                    'product_type' => $productType,
                    'cover'        => $coverUrl,
                    'cost_price'   => isset($request->cost_price) ? $request->cost_price : 0,
                    'price'        => $request->price,
                    'stock'        => $productType === 'resep' ? 0 : (isset($request->stock) ? $request->stock : $product->stock),
                    'weight'       => isset($request->weight) ? $request->weight : $product->weight,
                ]);

                if ($productType === 'resep' && $request->has('recipes')) {
                    $this->syncProductRecipes($product->id, $request->recipes);
                } elseif ($productType !== 'resep') {
                    ProductRecipe::where('product_id', $product->id)->delete();
                }
            });
        } catch (\Exception $e) {
            if ($newUploadedRelativePath) {
                $this->deleteImageEverywhere($newUploadedRelativePath);
            }
            throw $e;
        }

        if ($shouldDeleteOld && $oldCoverUrl && $oldCoverUrl !== $coverUrl) {
            $this->deleteImageEverywhere($oldCoverUrl);
        }

        $this->clearProductCache();

        return response()->json([
            'status' => 'success',
            'data'   => $product->load(['category', 'recipes.rawMaterial', 'platformPrices']),
        ]);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $oldCoverUrl = $product->cover;

        DB::transaction(function () use ($product) {
            ProductRecipe::where('product_id', $product->id)->delete();
            ProductPricePlatform::where('product_id', $product->id)->delete();
            $product->delete();
        });

        if ($oldCoverUrl) {
            $this->deleteImageEverywhere($oldCoverUrl);
        }

        $this->clearProductCache();

        return response()->json(['status' => 'success']);
    }

    public function stockOpname(Request $request, $id)
    {
        $request->validate([
            'actual_stock' => 'required|integer|min:0',
        ]);

        $product = Product::findOrFail($id);
        $product->update(['stock' => (int) $request->actual_stock]);

        $this->clearProductCache();

        return response()->json(['status' => 'success', 'data' => $product]);
    }
}
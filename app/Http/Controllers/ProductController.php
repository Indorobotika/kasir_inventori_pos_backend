<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductRecipe;
use App\Models\ProductPricePlatform;

class ProductController extends Controller 
{
    protected $cacheTtl = 3600;

    public function categories() {
        $categories = Cache::remember('categories_all', $this->cacheTtl, function () {
            return Category::orderByDesc('id')->get();
        });

        return response()->json(['data' => $categories]);
    }

    public function storeCategory(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name'
        ]);

        $cat = Category::create([
            'name' => $request->name, 
            'slug' => Str::slug($request->name)
        ]);

        Cache::forget('categories_all');

        return response()->json(['status' => 'success', 'data' => $cat]);
    }

    public function index(Request $request) {
        $query = Product::with(['category', 'recipes.rawMaterial', 'platformPrices'])->orderByDesc('id');
        
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $products = Cache::remember('products_all_' . ($request->category_id ?? 'all'), $this->cacheTtl, function () use ($query) {
            return $query->get();
        });

        return response()->json(['data' => $products]);
    }

    public function store(Request $request) {
        $request->validate([
            'category_id'  => 'required|exists:categories,id',
            'title'        => 'required|string|max:255',
            'unit'         => 'required|in:pcs,cup,botol,porsi,box,pack,bowl,plate',
            'price'        => 'required|numeric|min:0',
            'product_type' => 'required|in:retail,resep',
            'cover'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $sku = $request->sku;
        if (empty($sku)) {
            $sku = 'SKU-' . strtoupper(Str::random(6));
        }

        $coverUrl = null;
        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $filename = 'product_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $filename, 'public');
            $coverUrl = asset('storage/' . $path);
        }

        $product = Product::create([
            'category_id'  => $request->category_id,
            'sku'          => $sku,
            'title'        => $request->title,
            'unit'         => $request->unit,
            'product_type' => strtolower($request->product_type),
            'cover'        => $coverUrl,
            'cost_price'   => $request->cost_price ?? 0,
            'price'        => $request->price,
            'stock'        => $request->product_type === 'resep' ? 0 : ($request->stock ?? 0),
            'weight'       => $request->weight ?? 0,
        ]);

        if ($request->product_type === 'resep' && $request->has('recipes')) {
            $recipes = is_string($request->recipes) ? json_decode($request->recipes, true) : $request->recipes;
            if (is_array($recipes)) {
                foreach ($recipes as $r) {
                    if (!empty($r['raw_material_id']) && !empty($r['qty_needed'])) {
                        ProductRecipe::create([
                            'product_id'      => $product->id,
                            'raw_material_id' => $r['raw_material_id'],
                            'qty_needed'      => $r['qty_needed'],
                        ]);
                    }
                }
            }
        }

        Cache::forget('products_all_all');

        return response()->json([
            'status' => 'success', 
            'data' => $product->load(['category', 'recipes', 'platformPrices'])
        ]);
    }

    public function update(Request $request, $id) {
        $product = Product::findOrFail($id);

        $request->validate([
            'category_id'  => 'required|exists:categories,id',
            'title'        => 'required|string|max:255',
            'unit'         => 'required|in:pcs,cup,botol,porsi,box,pack,bowl,plate',
            'price'        => 'required|numeric|min:0',
            'product_type' => 'required|in:retail,resep',
            'cover'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        
        $coverUrl = $product->cover;
        if ($request->hasFile('cover')) {
            if ($product->cover) {
                $oldPath = str_replace(url('storage') . '/', '', $product->cover);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $file = $request->file('cover');
            $filename = 'product_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $filename, 'public');
            $coverUrl = asset('storage/' . $path);
        }

        $product->update([
            'category_id'  => $request->category_id,
            'sku'          => $request->sku ?: $product->sku,
            'title'        => $request->title,
            'unit'         => $request->unit,
            'product_type' => strtolower($request->product_type),
            'cover'        => $coverUrl,
            'cost_price'   => $request->cost_price ?? 0,
            'price'        => $request->price,
            'stock'        => $request->product_type === 'resep' ? 0 : ($request->stock ?? $product->stock),
        ]);

        ProductRecipe::where('product_id', $product->id)->delete();
        if ($request->product_type === 'resep' && $request->has('recipes')) {
            $recipes = is_string($request->recipes) ? json_decode($request->recipes, true) : $request->recipes;
            if (is_array($recipes)) {
                foreach ($recipes as $r) {
                    if (!empty($r['raw_material_id']) && !empty($r['qty_needed'])) {
                        ProductRecipe::create([
                            'product_id'      => $product->id,
                            'raw_material_id' => $r['raw_material_id'],
                            'qty_needed'      => $r['qty_needed'],
                        ]);
                    }
                }
            }
        }

        Cache::forget('products_all_all');

        return response()->json(['status' => 'success', 'data' => $product->load(['category', 'recipes'])]);
    }

    public function destroy($id) {
        $product = Product::findOrFail($id);
        if ($product->cover) {
            $oldPath = str_replace(url('storage') . '/', '', $product->cover);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }
        ProductRecipe::where('product_id', $product->id)->delete();
        ProductPricePlatform::where('product_id', $product->id)->delete();
        $product->delete();
        
        Cache::forget('products_all_all');

        return response()->json(['status' => 'success']);
    }

    public function stockOpname(Request $request, $id) {
        $product = Product::findOrFail($id);
        $product->update(['stock' => (int) $request->actual_stock]);
        
        Cache::forget('products_all_all');

        return response()->json(['status' => 'success', 'data' => $product]);
    }
}
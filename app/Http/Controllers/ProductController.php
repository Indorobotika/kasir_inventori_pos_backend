<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductRecipe;
use App\Models\ProductPricePlatform;

class ProductController extends Controller {
    public function categories() {
        return response()->json(['data' => Category::all()]);
    }

    public function storeCategory(Request $request) {
        $cat = Category::create(['name' => $request->name, 'slug' => Str::slug($request->name)]);
        return response()->json(['status' => 'success', 'data' => $cat]);
    }

    public function index(Request $request) {
        $query = Product::with(['category', 'recipes.rawMaterial', 'platformPrices']);
        if ($request->category_id) $query->where('category_id', $request->category_id);
        return response()->json(['data' => $query->get()]);
    }

    public function productsByCategorySlug($slug) {
        $category = Category::where('slug', $slug)->firstOrFail();
        $products = Product::with(['recipes.rawMaterial', 'platformPrices'])->where('category_id', $category->id)->paginate(20);
        return response()->json(['data' => ['category' => $category, 'products' => $products]]);
    }

    public function store(Request $request) {
        $product = Product::create($request->only([
            'category_id', 'sku', 'title', 'unit', 'product_type', 'cover', 'cost_price', 'price', 'stock', 'weight'
        ]));

        if ($request->has('recipes') && is_array($request->recipes)) {
            foreach ($request->recipes as $r) {
                ProductRecipe::create([
                    'product_id' => $product->id,
                    'raw_material_id' => $r['raw_material_id'],
                    'qty_needed' => $r['qty_needed'],
                ]);
            }
        }

        if ($request->has('platform_prices') && is_array($request->platform_prices)) {
            foreach ($request->platform_prices as $p) {
                ProductPricePlatform::create([
                    'product_id' => $product->id,
                    'platform_name' => $p['platform_name'],
                    'price' => $p['price'],
                ]);
            }
        }

        return response()->json(['status' => 'success', 'data' => $product->load(['category', 'recipes', 'platformPrices'])]);
    }

    public function update(Request $request, $id) {
        $product = Product::findOrFail($id);
        $product->update($request->only([
            'category_id', 'sku', 'title', 'unit', 'product_type', 'cover', 'cost_price', 'price', 'stock', 'weight'
        ]));
        return response()->json(['status' => 'success', 'data' => $product]);
    }

    public function destroy($id) {
        Product::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    public function stockOpname(Request $request, $id) {
        $product = Product::findOrFail($id);
        $product->update(['stock' => (int) $request->actual_stock]);
        return response()->json(['status' => 'success', 'data' => $product]);
    }
}

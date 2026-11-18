<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\RawMaterial;

class RawMaterialController extends Controller 
{
    protected $cacheTtl = 3600; // Cache selama 1 jam

    public function index() {
        // Menggunakan cache, pastikan Cache::forget('raw_materials_all') dipanggil saat stok berubah
        $rawMaterials = Cache::remember('raw_materials_all', $this->cacheTtl, function () {
            return RawMaterial::orderByDesc('id')->get();
        });

        return response()->json(['data' => $rawMaterials]);
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'name'       => 'required|string|max:255|unique:raw_materials,name',
            'unit'       => 'required|in:kg,gram,liter,ml,pcs,pack,box',
            'stock'      => 'required|numeric|min:0',
            'min_stock'  => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
        ]);

        $rm = RawMaterial::create($validated);
        Cache::forget('raw_materials_all');

        return response()->json(['status' => 'success', 'data' => $rm]);
    }

    public function update(Request $request, $id) {
        $rm = RawMaterial::findOrFail($id);

        $validated = $request->validate([
            'name'       => 'required|string|max:255|unique:raw_materials,name,' . $id,
            'unit'       => 'required|in:kg,gram,liter,ml,pcs,pack,box',
            'unit'       => 'required|in:kg,gram,liter,ml,pcs,pack,box',
            'stock'      => 'required|numeric|min:0',
            'min_stock'  => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
        ]);

        $rm->update($validated);
        Cache::forget('raw_materials_all');

        return response()->json(['status' => 'success', 'data' => $rm]);
    }

    public function destroy($id) {
        $rm = RawMaterial::findOrFail($id);
        $rm->delete();

        Cache::forget('raw_materials_all');

        return response()->json(['status' => 'success']);
    }

    public function stockOpname(Request $request, $id) {
        $rm = RawMaterial::findOrFail($id);

        $request->validate([
            'actual_stock' => 'required|numeric|min:0'
        ]);

        $rm->update(['stock' => (float) $request->actual_stock]);

        Cache::forget('raw_materials_all');

        return response()->json(['status' => 'success', 'data' => $rm]);
    }
}
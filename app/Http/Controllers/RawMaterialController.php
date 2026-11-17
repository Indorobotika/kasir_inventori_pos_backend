<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RawMaterial;

class RawMaterialController extends Controller 
{
    public function index() {
        return response()->json(['data' => RawMaterial::orderBy('name')->get()]);
    }

    public function store(Request $request) {
        $request->validate([
            'name'       => 'required|string|max:255|unique:raw_materials,name',
            'unit'       => 'required|string|max:50',
            'stock'      => 'required|numeric|min:0',
            'min_stock'  => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
        ]);

        $rm = RawMaterial::create($request->only([
            'name', 'unit', 'stock', 'min_stock', 'cost_price'
        ]));

        return response()->json(['status' => 'success', 'data' => $rm]);
    }

    public function update(Request $request, $id) {
        $rm = RawMaterial::findOrFail($id);

        $request->validate([
            'name'       => 'required|string|max:255|unique:raw_materials,name,' . $id,
            'unit'       => 'required|string|max:50',
            'stock'      => 'required|numeric|min:0',
            'min_stock'  => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
        ]);

        $rm->update($request->only([
            'name', 'unit', 'stock', 'min_stock', 'cost_price'
        ]));

        return response()->json(['status' => 'success', 'data' => $rm]);
    }

    public function destroy($id) {
        $rm = RawMaterial::findOrFail($id);
        $rm->delete();

        return response()->json(['status' => 'success']);
    }

    public function stockOpname(Request $request, $id) {
        $rm = RawMaterial::findOrFail($id);

        $request->validate([
            'actual_stock' => 'required|numeric|min:0'
        ]);

        $rm->update(['stock' => (float) $request->actual_stock]);

        return response()->json(['status' => 'success', 'data' => $rm]);
    }
}
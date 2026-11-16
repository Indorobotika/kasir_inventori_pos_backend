<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\RawMaterial;

class RawMaterialController extends Controller {
    public function index() {
        return response()->json(['data' => RawMaterial::orderBy('name')->get()]);
    }

    public function store(Request $request) {
        $rm = RawMaterial::create($request->only(['name', 'unit', 'stock', 'min_stock', 'cost_price']));
        return response()->json(['status' => 'success', 'data' => $rm]);
    }

    public function update(Request $request, $id) {
        $rm = RawMaterial::findOrFail($id);
        $rm->update($request->only(['name', 'unit', 'stock', 'min_stock', 'cost_price']));
        return response()->json(['status' => 'success', 'data' => $rm]);
    }

    public function destroy($id) {
        RawMaterial::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    public function stockOpname(Request $request, $id) {
        $rm = RawMaterial::findOrFail($id);
        $rm->update(['stock' => (float) $request->actual_stock]);
        return response()->json(['status' => 'success', 'data' => $rm]);
    }
}

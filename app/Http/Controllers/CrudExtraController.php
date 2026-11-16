<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Promotion;
use App\Models\Setting;

class CrudExtraController extends Controller {
    public function index() { return response()->json(['data' => Promotion::all()]); }
    public function store(Request $request) { return response()->json(['data' => Promotion::create($request->all())]); }
    public function update(Request $request, $id) {
        $promo = Promotion::findOrFail($id);
        $promo->update($request->all());
        return response()->json(['data' => $promo]);
    }
    public function destroy($id) {
        Promotion::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    public function getSettings() { return response()->json(['data' => Setting::first()]); }
    public function updateSettings(Request $request) {
        $setting = Setting::firstOrCreate(['id' => 1]);
        $setting->update($request->all());
        return response()->json(['status' => 'success', 'data' => $setting]);
    }
}

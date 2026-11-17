<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Promotion;
use App\Models\Setting;

class CrudExtraController extends Controller {
    public function index() { 
        return response()->json(['data' => Promotion::all()]); 
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

        return response()->json(['status' => 'success', 'data' => $promo]);
    }

    public function destroy($id) {
        Promotion::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    public function getSettings() { 
        return response()->json(['data' => Setting::first()]); 
    }

    public function updateSettings(Request $request) {
        $setting = Setting::firstOrCreate(['id' => 1]);
        $setting->update($request->all());
        return response()->json(['status' => 'success', 'data' => $setting]);
    }
}
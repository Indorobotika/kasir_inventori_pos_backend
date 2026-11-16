<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductRecipe extends Model {
    public $timestamps = false;
    protected $fillable = ['product_id', 'raw_material_id', 'qty_needed'];
    public function rawMaterial() { return $this->belongsTo(RawMaterial::class); }
}

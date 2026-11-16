<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    protected $fillable = ['category_id', 'sku', 'title', 'unit', 'product_type', 'cover', 'cost_price', 'price', 'stock', 'weight'];
    public function category() { return $this->belongsTo(Category::class); }
    public function recipes() { return $this->hasMany(ProductRecipe::class); }
    public function platformPrices() { return $this->hasMany(ProductPricePlatform::class); }
}

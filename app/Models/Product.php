<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    protected $fillable = [
        'category_id', 
        'sku', 
        'title', 
        'unit', 
        'product_type', 
        'cover', 
        'cost_price', 
        'price', 
        'stock', 
        'weight'
    ];

    // Memaksa Eloquent untuk selalu menyertakan data kategori secara otomatis
    protected $with = ['category'];

    public function category() { 
        return $this->belongsTo(Category::class, 'category_id', 'id'); 
    }

    public function recipes() { 
        return $this->hasMany(ProductRecipe::class, 'product_id', 'id'); 
    }

    public function platformPrices() { 
        return $this->hasMany(ProductPricePlatform::class, 'product_id', 'id'); 
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    protected $fillable = [
        'name',
        'unit',
        'stock',
        'min_stock',
        'cost_price',
        'is_sellable',
        'sell_price',
        'category_id',
        'cover',
    ];

    protected $casts = [
        'stock' => 'float',
        'min_stock' => 'float',
        'cost_price' => 'float',
        'sell_price' => 'float',
        'is_sellable' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
}
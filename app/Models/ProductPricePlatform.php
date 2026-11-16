<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductPricePlatform extends Model {
    public $timestamps = false;
    protected $table = 'product_prices_platform';
    protected $fillable = ['product_id', 'platform_name', 'price'];
}

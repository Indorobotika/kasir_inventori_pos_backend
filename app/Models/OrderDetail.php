<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model {
    protected $fillable = ['order_id', 'product_id', 'promotion_id', 'quantity', 'cost_product', 'price_product', 'subtotal'];
    public function product() { return $this->belongsTo(Product::class); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'invoice_number',
        'user_id',
        'customer_name',
        'platform',
        'payment_method',
        'numberCard',
        'subtotal',
        'discount_amount',
        'tax',
        'supplier_sales_tax',
        'total_cost',
        'csr_percentage',
        'csr_amount',
        'total_bill',
        'cash_received',
        'cash_change',
        'status',
        'qris_reference',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCancellationReport extends Model
{
    protected $table = 'order_cancellation_reports';

    protected $fillable = [
        'order_id',
        'invoice_number',
        'nominal',
        'berita_acara',
        'canceled_by',
        'canceled_at',
    ];

    protected $casts = [
        'nominal'     => 'float',
        'canceled_at' => 'datetime',
    ];
}
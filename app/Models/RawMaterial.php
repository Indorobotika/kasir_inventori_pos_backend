<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model {
    protected $fillable = ['name', 'unit', 'stock', 'min_stock', 'cost_price'];
}

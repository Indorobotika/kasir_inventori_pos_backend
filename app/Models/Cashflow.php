<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Cashflow extends Model {
    protected $fillable = ['user_id', 'type', 'category', 'amount', 'description', 'transaction_date'];
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model {
    protected $fillable = ['title', 'typ_promotion_id', 'required', 'result', 'is_active'];
}

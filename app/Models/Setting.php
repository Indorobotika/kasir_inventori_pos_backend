<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model {
    protected $fillable = [
        'store_name', 
        'location_store', 
        'contact', 
        'logo_path_url', 
        'qris_path_url', // <-- Tambahkan ini agar dapat disimpan secara massal (mass assignment)
        'tax', 
        'receipt_width', 
        'receipt_footer'
    ];
}
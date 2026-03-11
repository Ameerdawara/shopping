<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    public $fillable = [
        'product_id',
        'description',
        'discount_percentage',
        'discount_price',
        'starts_at',
        'ends_at',
        'is_active',
    ];  

     public function product(){
        return $this->belongsTo(Product::class);
    }
}

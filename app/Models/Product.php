<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public function cartItem(){
        return $this->hasMany(Product::class);
    }
    public function offer(){
        return $this->belongsTo(Offer::class);
    }
     public function reviews(){
        return $this->hasMany(Review::class);
    }

}

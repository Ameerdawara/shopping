<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
     public function product(){
        return $this->hasOne(Product::class);
    }
}

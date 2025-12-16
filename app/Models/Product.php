<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public $guarded = ['id'];
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
    // App\Models\Product.php

    public function sizes()
    {
        return $this->hasMany(ProductSize::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable =
    [
        'name',
        'description',
        'price',
        'buyCount',
        'category',
        'brand'
    ];
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
    public function offer()
    {
        return $this->hasOne(Offer::class);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
    // App\Models\Product.php

    public function sizes()
    {
        return $this->hasMany(ProductSize::class);
    }
    protected $appends = ['image_url'];

public function getImageUrlAttribute()
{
    if ($this->images->count()) {
        return asset('storage/' . $this->images->first()->image);
    }
    return null;
}
    
}

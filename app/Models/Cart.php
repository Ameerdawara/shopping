<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
    ];
    protected $appends = ['total_price'];
    public function user()
    {

        return $this->belongsTo(User::class);
    }
    public function cartItem()
    {
        return $this->hasMany(CartItem::class);
    }
    protected $table = 'cards';

    public function getTotalPriceAttribute()
    {
        return $this->cartItem()
            ->sum(DB::raw('unit_price * quantity'));
    }
}

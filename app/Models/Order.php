<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{

    protected $fillable = [
        'user_id',
        'total_price',
        'status',
        'is_paid',
        'shipping_address',
        'delivered_at',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function orderItem()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}

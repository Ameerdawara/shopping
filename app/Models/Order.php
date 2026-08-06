<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'total_price',
        'currency',
        'payment_method',      // cash, shamcash, usdt
        'status',              // pending, pending_approval, processing, cancelled, completed
        'is_paid',
        'shipping_address',
        'delivered_at',
        'transaction_id',      // رقم العملية/المعاملة
        'sender_name',         // اسم المرسل
        'payment_proof',       // JSON: بيانات إثبات الدفع
        'paid_at',             // تاريخ تأكيد الدفع
        'ip_address',          // عنوان IP العميل
        'user_agent',          // متصفح/جهاز العميل
    ];

    protected $casts = [
        'is_paid'       => 'boolean',
        'delivered_at'  => 'datetime',
        'paid_at'       => 'datetime',
        'payment_proof' => 'array', // JSON casting
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

    // Scopes مفيدة
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }
}

<?php
// app/Models/ExchangeRate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ExchangeRate extends Model
{
    protected $fillable = ['rate', 'updated_by'];

    protected $casts = [
        'rate' => 'decimal:4',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * جلب آخر سعر صرف مفعّل (مع كاش لتقليل الاستعلامات على كل عملية سلة/طلب)
     */
    public static function current(): float
    {
        return (float) Cache::remember('exchange_rate:current', now()->addMinutes(10), function () {
            $latest = static::latest('id')->first();
            return $latest ? (float) $latest->rate : 1.0;
        });
    }

    /**
     * تحويل مبلغ من العملة الأساسية إلى العملة المحلية حسب آخر سعر صرف
     */
    public static function convert(float $amountInBaseCurrency): float
    {
        return round($amountInBaseCurrency * static::current(), 2);
    }

    protected static function booted()
    {
        // أي سعر جديد بيلغي الكاش القديم فوراً
        static::created(function () {
            Cache::forget('exchange_rate:current');
        });
    }
}

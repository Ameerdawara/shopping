<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method static string|null get(string $key, $default = null)
 * @method static void set(string $key, $value)
 * @method static \App\Models\PaymentSetting firstOrCreate(array $attributes, array $values = [])
 * @method static \App\Models\PaymentSetting updateOrCreate(array $attributes, array $values = [])
 */
class PaymentSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

   public static function set(string $key, $value): void
{
    // updateOrCreate returns the model, not void
    static::updateOrCreate(['key' => $key], ['value' => $value]);
    // Remove the @var annotation or change return type
}

}

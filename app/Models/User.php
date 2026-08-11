<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone',
        'phone_verified_at', 'otp_code', 'otp_expires_at', 'role'
    ];

    protected $hidden = [
        'password', 'remember_token', 'otp_code', 'otp_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'otp_expires_at'    => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ---------------------------------------------------------
    // MUTATOR: Force E.164 on ANY write (Seeder, Tinker, Other Controllers)
    // ---------------------------------------------------------
    public function setPhoneAttribute($value)
    {
        $normalized = PhoneNumber::normalize($value);

        if ($normalized) {
            $this->attributes['phone'] = $normalized;
        } else {
            // Log warning but save raw to avoid silent data loss during dev/seeding
            // In strict production, you might throw \InvalidArgumentException
            \Log::warning('User Model: Phone normalization failed, saving raw', ['input' => $value]);
            $this->attributes['phone'] = $value;
        }
    }

    // ---------------------------------------------------------
    // ACCESSOR: Pretty format for Frontend Display (09XXXXXXXX)
    // ---------------------------------------------------------
    public function getPhoneDisplayAttribute(): string
    {
        return PhoneNumber::getNationalNumber($this->phone);
    }

    // Relationships
    public function profile() { return $this->hasOne(Profile::class); }
    public function carts()   { return $this->hasOne(Cart::class); }
    public function orders()  { return $this->hasMany(Order::class); }
    public function reviews() { return $this->hasMany(Review::class); }
    public function notifications() { return $this->hasMany(Notification::class); }

    public function getIsAdminAttribute() { return $this->role === 'admin'; }
    public function getIsPhoneVerifiedAttribute() { return !is_null($this->phone_verified_at); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $fillable = [
        'name',
        'image',
        'user_id',
        'email',
        'phone',
        'total_purchases',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

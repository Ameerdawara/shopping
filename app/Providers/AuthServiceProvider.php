<?php

namespace App\Providers;

use App\Models\Cart;
use App\Models\Review;
use App\Models\Notification;
use App\Policies\CartPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\NotificationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Cart::class  => CartPolicy::class,
        Review::class => ReviewPolicy::class,
        Notification::class => NotificationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}

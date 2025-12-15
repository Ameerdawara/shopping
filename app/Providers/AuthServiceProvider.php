<?php

namespace App\Providers;

use App\Models\Cart;
use App\Models\Offer;
use App\Policies\CartItemPolicy;
use App\Policies\CartPolicy;
use App\Policies\OfferPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Cart::class => CartPolicy::class,
        Offer::class=>OfferPolicy::class,
        Cart::class=>CartItemPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}

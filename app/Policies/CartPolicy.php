<?php

namespace App\Policies;

use App\Models\Cart;
use App\Models\User;

class CartPolicy
{
 
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }


    public function view(User $user, Cart $cart): bool
    {
        return $user->is_admin || $cart->user_id === $user->id;
    }


    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Cart $cart): bool
    {
        return $user->is_admin || $cart->user_id === $user->id;
    }


    public function delete(User $user, Cart $cart): bool
    {
        return $user->is_admin;
    }


    public function restore(User $user, Cart $cart): bool
    {
        return $user->is_admin;
    }


    public function forceDelete(User $user, Cart $cart): bool
    {
        return $user->is_admin;
    }
}

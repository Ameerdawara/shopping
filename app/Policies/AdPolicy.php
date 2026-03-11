<?php

namespace App\Policies;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AdPolicy
{
   public function viewAny(?User $user = null)
{
    return true;
}


    public function create(User $user)
    {
        return $user->is_admin;
    }

    public function update(User $user, Ad $ad)
    {
        return $user->is_admin;
    }

    public function delete(User $user, Ad $ad)
    {
        return $user->is_admin;
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProfileRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Hash;


use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function me()
    {
        $user = Auth::user();

        return response()->json([
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => optional($user->profile)->phone,
        ]);
    }


    public function updateMe(UpdateProfileRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // تحديث الاسم والإيميل
        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        if ($request->filled('email')) {
            $user->email = $request->email;
        }

        // تحديث كلمة المرور (إن وُجدت)
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // تحديث البروفايل
        if ($user->profile) {
            $user->profile->update(
                $request->only(['image', 'phone', 'bio'])
            );
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => optional($user->profile)->phone,
                'image' => optional($user->profile)->image,
                'bio' => optional($user->profile)->bio,
            ]
        ]);
    }
}

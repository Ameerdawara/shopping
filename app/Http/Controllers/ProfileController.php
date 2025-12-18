<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProfileRequest;
use App\Http\Requests\UpdateProfileRequest;

use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
   public function me()
    {
        $user = Auth::user();

        if (!$user->profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        return response()->json($user->profile);
    }
     public function updateMe(UpdateProfileRequest $request)
    {
        $user = Auth::user();

        if (!$user->profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        $user->profile->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user->profile
        ]);
    }
}

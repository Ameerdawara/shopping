<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProfileRequest;
use App\Http\Requests\UpdateProfileRequest;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); // أو auth:api
    }

    public function index()
    {
        return response()->json(Profile::all(), 200);
    }

    public function show(Profile $profile)
    {
        return response()->json($profile, 200);
    }

    public function store(StoreProfileRequest $request)
    {
        $profile = Profile::create($request->validated());

        return response()->json([
            'message' => 'Profile created successfully',
            'data' => $profile,
        ], 201);
    }

    public function update(UpdateProfileRequest $request, Profile $profile)
    {
        $profile->update($request->validated());

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $profile,
        ], 200);
    }

    public function destroy(Profile $profile)
    {
        $profile->delete();

        return response()->json([
            'message' => 'Profile deleted successfully',
        ], 200);
    }
}

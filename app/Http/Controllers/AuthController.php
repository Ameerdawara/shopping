<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /////////////////////// REGISTER
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'user',
        ]);

        // ✅ إنشاء Profile تلقائيًا
        $user->profile()->create([
            'name' => $request->name,
            'phone' => null,
            'address' => null,
        ]);
        $user->carts()->create([
            'user_id' => $user->id,
        ]);


        // إنشاء توكن
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'message' => 'تم التسجيل بنجاح',
            'token' => $token,
            'user' => $user->load('profile'),
            'cart' => $user->carts()->first(),
        ], 201);
    }

    /////////////////////// LOGIN
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

       
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'البيانات غير صحيحة'
            ], 401);
        }

        // حذف التوكنات القديمة (اختياري)
        $user->tokens()->delete();

        // إنشاء توكن جديد
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'token' => $token,
            'user' => $user->load('profile')
        ]);
    }

    /////////////////////// LOGOUT
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }
}

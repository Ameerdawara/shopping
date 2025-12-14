<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller
{
    //////////////////////////////////regiter

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
        ]);

        Auth::login($user);
        return response()->json($user, 201);
    }



    ///////////////////////////login


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
        'user' => $user
    ]);
}

    //////////////////////////////logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}

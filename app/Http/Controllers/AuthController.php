<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsGateService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected SmsGateService $smsGateService;

    public function __construct(SmsGateService $smsGateService)
    {
        $this->smsGateService = $smsGateService;
    }

    // ---------------------------------------------------------
    // REGISTER: Create User + Send OTP (No Token Yet)
    // ---------------------------------------------------------
    public function register(Request $request)
    {
        // 1. Validate Input
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'phone'    => 'required|string|max:20', // Loose validation here, we normalize below
        ]);

        // 2. NORMALIZE PHONE TO E.164
        $normalizedPhone = PhoneNumber::normalize($validated['phone']);

        if (!$normalizedPhone) {
            return response()->json([
                'message' => 'صيغة رقم الهاتف غير صحيحة. يرجى إدخاله بصيغة: 09XXXXXXXX أو +9639XXXXXXXX',
                'errors'  => ['phone' => ['رقم الهاتف غير صالح']],
            ], 422);
        }

        // 3. Check Uniqueness on Normalized Phone
        if (User::where('phone', $normalizedPhone)->exists()) {
            return response()->json([
                'message' => 'رقم الهاتف مسجل مسبقاً',
                'errors'  => ['phone' => ['رقم الهاتف مستخدم بالفعل']],
            ], 422);
        }

        // 4. Generate OTP
        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 5. Create User (Store E.164 in DB)
        $user = User::create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'password'       => bcrypt($validated['password']),
            'phone'          => $normalizedPhone, // STORE E.164
            'role'           => 'user',
            'otp_code'       => $otpCode,
            'otp_expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // 6. Send SMS (Non-blocking: don't fail registration if SMS fails, user can resend)
        $sent = $this->smsGateService->sendOtp($user->phone, $otpCode);

        if (!$sent) {
            Log::channel('sms')->warning('AuthController: Registration succeeded but SMS failed', [
                'user_id' => $user->id,
                'phone'   => $user->phone,
            ]);

            return response()->json([
                'message' => 'تم إنشاء الحساب، لكن حدث خطأ مؤقت في إرسال رمز التحقق. يرجى الضغط على "إعادة الإرسال" في صفحة التفعيل.',
                'phone'   => PhoneNumber::getNationalNumber($user->phone), // Return pretty format for UI
            ], 201);
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق',
            'phone'   => PhoneNumber::getNationalNumber($user->phone),
        ], 201);
    }

    // ---------------------------------------------------------
    // VERIFY OTP: Activate Account + Issue Token
    // ---------------------------------------------------------
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'phone'    => 'required|string',
            'otp_code' => 'required|string|size:6',
        ]);

        // Normalize incoming phone
        $normalizedPhone = PhoneNumber::normalize($validated['phone']);
        if (!$normalizedPhone) {
            return response()->json(['message' => 'رقم هاتف غير صالح'], 400);
        }

        $user = User::where('phone', $normalizedPhone)->first();

        if (!$user) {
            return response()->json(['message' => 'رقم الهاتف غير مسجل'], 404);
        }

        if ($user->phone_verified_at) {
            return response()->json(['message' => 'الحساب مفعل مسبقاً', 'redirect' => 'login'], 409);
        }

        if (!$user->otp_code || !$user->otp_expires_at) {
            return response()->json(['message' => 'لا يوجد رمز تحقق فعال، يرجى طلب رمز جديد'], 400);
        }

        if (Carbon::now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد'], 410);
        }

        // Timing-safe comparison
        if (!hash_equals((string) $user->otp_code, (string) $validated['otp_code'])) {
            return response()->json(['message' => 'رمز التحقق غير صحيح'], 422);
        }

        // SUCCESS: Transactional User Activation
        return DB::transaction(function () use ($user) {
            $user->phone_verified_at = Carbon::now();
            $user->otp_code = null;
            $user->otp_expires_at = null;
            $user->save();

            // Create Profile & Cart (Deferred until verification)
            $user->profile()->firstOrCreate([], [
                'name'    => $user->name,
                'email'   => $user->email,
                'phone'   => $user->phone,
                'address' => null,
            ]);

            $user->carts()->firstOrCreate(['user_id' => $user->id]);

            $token = $user->createToken('api_token')->plainTextToken;

            return response()->json([
                'message' => 'تم تفعيل الحساب بنجاح',
                'token'   => $token,
                'user'    => $user->fresh()->load('profile'),
                'role'    => 'user',
            ], 200);
        });
    }

    // ---------------------------------------------------------
    // RESEND OTP
    // ---------------------------------------------------------
    public function resendOtp(Request $request)
    {
        $validated = $request->validate(['phone' => 'required|string']);

        $normalizedPhone = PhoneNumber::normalize($validated['phone']);
        if (!$normalizedPhone) {
            return response()->json(['message' => 'رقم هاتف غير صالح'], 400);
        }

        $user = User::where('phone', $normalizedPhone)->first();

        if (!$user) {
            return response()->json(['message' => 'رقم الهاتف غير مسجل'], 404);
        }

        if ($user->phone_verified_at) {
            return response()->json(['message' => 'الحساب مفعل مسبقاً'], 409);
        }

        // Generate New OTP
        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->otp_code = $otpCode;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // Send
        $sent = $this->smsGateService->sendOtp($user->phone, $otpCode);

        if (!$sent) {
            return response()->json([
                'message' => 'تعذر إرسال رمز التحقق حالياً، يرجى المحاولة لاحقاً',
            ], 502); // Bad Gateway - Upstream (SMS Gateway) failed
        }

        return response()->json([
            'message' => 'تم إعادة إرسال رمز التحقق',
            'phone'   => PhoneNumber::getNationalNumber($user->phone),
        ], 200);
    }

    // ---------------------------------------------------------
    // LOGIN
    // ---------------------------------------------------------
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'البيانات غير صحيحة'], 401);
        }

        // Enforce Phone Verification for non-admins
        if ($user->role !== 'admin' && !$user->phone_verified_at) {
            return response()->json([
                'message'           => 'يرجى تفعيل رقم هاتفك أولاً',
                'phone'             => PhoneNumber::getNationalNumber($user->phone),
                'requires_verification' => true,
            ], 403);
        }

        // Rotate Token
        $user->tokens()->delete();
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'token'   => $token,
            'user'    => $user->load('profile'),
        ]);
    }

    // ---------------------------------------------------------
    // LOGOUT
    // ---------------------------------------------------------
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }
}

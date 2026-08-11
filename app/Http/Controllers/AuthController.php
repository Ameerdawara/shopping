<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Services\SmsGateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected SmsGateService $smsGateService;

    public function __construct(SmsGateService $smsGateService)
    {
        $this->smsGateService = $smsGateService;
    }

    /////////////////////// REGISTER (Step 1: create user + send OTP, no token yet)
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'phone' => 'required|string|unique:users',
        ]);

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'phone' => $request->phone,
            'role' => 'user',
            'otp_code' => $otpCode,
            'otp_expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // ملاحظة: Profile و Cart لا يتم إنشاؤهما هنا، بل فقط بعد نجاح verifyOtp

        $sent = $this->smsGateService->sendOtp($user->phone, $otpCode);

        if (!$sent) {
            // لا نفشل التسجيل بالكامل، المستخدم بإمكانه طلب "إعادة إرسال" لاحقاً
            return response()->json([
                'message' => 'تم إنشاء الحساب، لكن حدث خطأ أثناء إرسال رمز التحقق. يرجى طلب إعادة الإرسال من صفحة التفعيل',
                'phone' => $user->phone,
            ], 201);
        }

        return response()->json([
            'message' => 'OTP sent',
            'phone' => $user->phone,
        ], 201);
    }

    /////////////////////// VERIFY OTP (Step 2: confirm phone, create Profile+Cart, issue token)
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp_code' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['message' => 'رقم الهاتف غير مسجل'], 404);
        }

        if ($user->phone_verified_at) {
            return response()->json(['message' => 'الحساب مفعّل مسبقاً'], 409);
        }

        if (!$user->otp_code || !$user->otp_expires_at) {
            return response()->json(['message' => 'لا يوجد رمز تحقق فعال، يرجى طلب رمز جديد'], 400);
        }

        if (Carbon::now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد'], 410);
        }

        if (!hash_equals((string) $user->otp_code, (string) $request->otp_code)) {
            return response()->json(['message' => 'رمز التحقق غير صحيح'], 422);
        }

        return DB::transaction(function () use ($user) {
            $user->phone_verified_at = Carbon::now();
            $user->otp_code = null;
            $user->otp_expires_at = null;
            $user->save();

            // ✅ إنشاء Profile تلقائيًا (بعد التحقق فقط)
            $user->profile()->firstOrCreate([], [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'password' => $user->password,
                'address' => null,
            ]);

            // ✅ إنشاء Cart تلقائيًا (بعد التحقق فقط)
            $user->carts()->firstOrCreate([
                'user_id' => $user->id,
            ]);

            $token = $user->createToken('api_token')->plainTextToken;

            return response()->json([
                'message' => 'تم تفعيل الحساب بنجاح',
                'token' => $token,
                'user' => $user->fresh()->load('profile'),
                'cart' => $user->carts()->first(),
                'role' => 'user',
            ], 200);
        });
    }

    /////////////////////// RESEND OTP
    public function resendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['message' => 'رقم الهاتف غير مسجل'], 404);
        }

        if ($user->phone_verified_at) {
            return response()->json(['message' => 'الحساب مفعّل مسبقاً'], 409);
        }

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->otp_code = $otpCode;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        $sent = $this->smsGateService->sendOtp($user->phone, $otpCode);

        if (!$sent) {
            return response()->json([
                'message' => 'تعذر إرسال رمز التحقق حالياً، يرجى المحاولة لاحقاً',
            ], 502);
        }

        return response()->json([
            'message' => 'تم إعادة إرسال رمز التحقق',
            'phone' => $user->phone,
        ], 200);
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

        // منع تسجيل الدخول قبل تفعيل رقم الهاتف عبر OTP
        if (!$user->phone_verified_at) {
            return response()->json([
                'message' => 'يرجى تفعيل رقم هاتفك أولاً',
                'phone' => $user->phone,
                'requires_verification' => true,
            ], 403);
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * المصادقة بنمط SPA.
 *
 * لا توكن يُعاد في الاستجابة: الجلسة تُنشأ في كوكي HttpOnly يضبطه الخادم،
 * فلا يستطيع أي سكربت في الصفحة قراءته ولو نجحت ثغرة XSS.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
            $request->hitRateLimiter();

            // رسالة واحدة للبريد الخاطئ ولكلمة المرور الخاطئة معاً،
            // فلا يصلح النموذج أداةً لكشف البريد المسجَّل من غيره
            throw ValidationException::withMessages([
                'email' => __('بيانات الدخول غير صحيحة.'),
            ]);
        }

        $user = $request->user();

        if ($user->status !== UserStatus::Active) {
            Auth::guard('web')->logout();

            // الجلسة قد لا تكون مهيّأة: مسارات الـ API تصير حالِيّة فقط
            // للأصول المصرَّح بها، وطلبٌ من غيرها يصل بلا وسيط جلسة.
            // استدعاء session() عندها يرمي RuntimeException ويحوّل رفضاً
            // مشروعاً إلى خطأ خادم.
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }

            throw ValidationException::withMessages([
                'email' => __('هذا الحساب موقوف. تواصل مع الإدارة.'),
            ]);
        }

        $request->clearRateLimiter();

        // تثبيت الجلسة: معرّف جديد بعد المصادقة يبطل أي معرّف زُرع قبلها
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->forceFill([
            // السابق يُحفظ قبل أن يُكتب الحالي: منه تُحسب «منذ آخر دخول»
            'previous_login_at' => $user->last_login_at,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        return response()->json([
            'message' => __('أهلاً بعودتك.'),
            'data' => new UserResource($user->load(['sponsor', 'staffProfile', 'roles'])),
        ]);
    }

    /** تسجيل كافل جديد. الموظفون يُنشأون من لوحة الإدارة لا من هنا. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = app(\App\Actions\Sponsors\RegisterSponsor::class)->handle(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            phone: $request->input('phone'),
            password: $request->string('password')->toString(),
        );

        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => __('تم إنشاء حسابك.'),
            'data' => new UserResource($user->load(['sponsor', 'staffProfile', 'roles'])),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()->load(['sponsor', 'staffProfile', 'roles'])),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __('تم تسجيل الخروج.')]);
    }
}

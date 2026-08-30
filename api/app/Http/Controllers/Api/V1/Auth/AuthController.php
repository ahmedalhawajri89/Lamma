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
                'email' => 'بيانات الدخول غير صحيحة.',
            ]);
        }

        $user = $request->user();

        if ($user->status !== UserStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => 'هذا الحساب موقوف. تواصل مع الإدارة.',
            ]);
        }

        $request->clearRateLimiter();

        // تثبيت الجلسة: معرّف جديد بعد المصادقة يبطل أي معرّف زُرع قبلها
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        return response()->json([
            'message' => 'أهلاً بعودتك.',
            'data' => new UserResource($user->load(['sponsor', 'staffProfile', 'roles'])),
        ]);
    }

    /** تسجيل كافل جديد. الموظفون يُنشأون من لوحة الإدارة لا من هنا. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => Hash::make($request->string('password')->toString()),
                'phone' => $request->input('phone'),
                'status' => UserStatus::Active,
            ]);

            $user->assignRole(UserRole::Sponsor->value);

            Sponsor::create([
                'user_id' => $user->id,
                'sponsor_code' => $this->nextSponsorCode(),
                'preferred_currency' => 'USD',
                'sponsoring_since' => now()->toDateString(),
                'communication_prefs' => ['email' => true, 'sms' => false],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'تم إنشاء حسابك.',
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
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    /**
     * ترقيم الكفلاء داخل معاملة مقفلة.
     * بلا القفل يحصل كافلان متزامنان على الرقم نفسه، فيفشل القيد الفريد.
     */
    private function nextSponsorCode(): string
    {
        $last = Sponsor::query()->lockForUpdate()->max('sponsor_code');
        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'SPN-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}

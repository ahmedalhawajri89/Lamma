<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * الحساب الموقوف يخرج فوراً، لا عند انتهاء جلسته.
 *
 * كان فحص الحالة عند الدخول وحده: موظف أُوقف وهو داخل يبقى بصلاحياته
 * الكاملة حتى تنتهي الجلسة (ساعات). هنا يُفحص مع كل طلب، والفحص رخيص
 * لأن المستخدم محمَّل أصلاً للمصادقة.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->status !== UserStatus::Active) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json(['message' => 'هذا الحساب موقوف. راجع مدير النظام.'], 401);
        }

        return $next($request);
    }
}

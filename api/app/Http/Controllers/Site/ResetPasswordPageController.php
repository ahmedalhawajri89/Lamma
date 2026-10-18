<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

/**
 * صفحة تعيين كلمة المرور من رابط البريد.
 *
 * الرابط يُفحص عند فتح الصفحة لا عند الحفظ: كان الزائر يكتب كلمته مرتين ثم
 * يُقال له إن الرابط انتهى. الفحص لا يستهلك الرمز (tokenExists)، ولا يكشف إن
 * كان البريد مسجّلاً: رمزٌ خاطئ لبريدٍ مسجّل أو غير مسجّل يعطي الحالة نفسها.
 */
class ResetPasswordPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = trim((string) $request->query('token', ''));
        $email = trim((string) $request->query('email', ''));

        $state = 'missing';
        if ($token !== '' && $email !== '') {
            $broker = Password::broker();
            $user = $broker->getUser(['email' => $email]);
            $state = $user !== null && $broker->tokenExists($user, $token) ? 'ready' : 'expired';
        }

        return response()
            ->view('site.auth.reset-password', ['state' => $state, 'token' => $token, 'email' => $email])
            // الرمز في العنوان: لا يُحفظ في ذاكرة وسيط، ولا يُرسل في Referer لأي رابط
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}

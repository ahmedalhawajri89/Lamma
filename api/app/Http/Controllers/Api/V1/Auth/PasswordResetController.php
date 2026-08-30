<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * استعادة كلمة المرور.
 *
 * كانت هذه الشاشة تعرض «أرسلنا لك رابط إعادة التعيين» وتُرسل شيئاً: نموذج
 * بلا وجهة يمرّ بفرع المحاكاة في forms.js. من ينسى كلمته كان يُقفَل خارج
 * حسابه ويُطلب منه انتظار بريد لن يأتي — وهذا أسوأ من غياب الرابط أصلاً.
 */
class PasswordResetController extends Controller
{
    /**
     * يرسل رابط إعادة التعيين.
     *
     * الردّ واحد سواء وُجد البريد أم لا. رسالة «هذا البريد غير مسجّل» تحوّل
     * الشاشة إلى أداة تعداد حسابات: يجرّب المهاجم قائمة بُرد ويعرف أيّها
     * لدينا. وهذا يخصّ كفلاءنا لا حساباتنا وحدها.
     */
    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->safe()->only('email'));

        // تجاوز حدّ الإرسال هو الحالة الوحيدة التي يفيد المستخدمَ أن يعرفها،
        // وإلا ضغط الزرّ مراراً وهو يظن أن شيئاً يحدث
        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'أرسلنا رابطاً قبل قليل. انتظر دقيقة قبل المحاولة مجدداً.',
            ])->status(429);
        }

        return response()->json([
            'message' => 'إن كان هذا البريد مسجّلاً لدينا فسيصلك رابط إعادة التعيين خلال دقائق.',
        ]);
    }

    /** يعيّن كلمة مرور جديدة مقابل رمز صالح. */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->safe()->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),

                    /**
                     * تدوير `remember_token` يُبطل «تذكّرني» على كل جهاز.
                     *
                     * من يعيد تعيين كلمته قد يفعل ذلك لأن جهازاً ضاع أو حساباً
                     * اختُرق؛ فترك الكوكيز القديمة صالحة يُفرغ العملية من معناها.
                     */
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'الرابط منتهي الصلاحية أو مستعمل من قبل. اطلب رابطاً جديداً.',
            ]);
        }

        return response()->json([
            'message' => 'تم تغيير كلمة المرور. يمكنك الدخول الآن.',
        ]);
    }
}

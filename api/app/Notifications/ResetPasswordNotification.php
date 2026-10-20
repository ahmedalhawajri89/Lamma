<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Locale;
use App\Support\SiteProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * رسالة إعادة تعيين كلمة المرور — بالعربية وبرابط يقصد صفحةً موجودة.
 *
 * إشعار لارافل الافتراضي يبني رابطه من مسار مُسمّى `password.reset` لا
 * وجود له هنا: هذا تطبيق API وموقعه صفحات ساكنة. فنبنيه صراحةً إلى
 * `/reset-password`، وهي الصفحة التي تقرأ الرمز والبريد من العنوان.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.users.expire', 60);

        // بلغة الحساب: صفحة التعيين بلغته، والرسالة تُبنى داخل لغته (preferredLocale).
        // الأساس من الإعداد لا من الطلب: رأس Host مزوّر كان سيضع في البريد رابطاً لموقع غيرنا
        $url = Locale::absolute('/reset-password', $notifiable->preferredLocale()).'?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('إعادة تعيين كلمة المرور | :site', ['site' => SiteProfile::site()['name']]))
            ->greeting(__('مرحباً'))
            ->line(__('وصلنا طلبٌ لإعادة تعيين كلمة مرور حسابك.'))
            ->action(__('إعادة تعيين كلمة المرور'), $url)
            ->line(__('الرابط صالح لمدة :minutes دقيقة.', ['minutes' => $minutes]))

            // لا نقول «تجاهل الرسالة» فقط: من لم يطلبها يحتاج أن يعرف أن
            // حسابه لم يُمسّ، وأن لا إجراء مطلوباً منه.
            ->line(__('إن لم تطلب هذا فلا حاجة لأي إجراء، فلم يتغيّر شيء في حسابك.'))
            ->salutation(__('فريق :site', ['site' => SiteProfile::site()['name']]));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['token' => Lang::get('sent')];
    }
}

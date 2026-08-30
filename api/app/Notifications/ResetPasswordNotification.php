<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * رسالة إعادة تعيين كلمة المرور — بالعربية وبرابط يقصد صفحةً موجودة.
 *
 * إشعار لارافل الافتراضي يبني رابطه من مسار مُسمّى `password.reset` لا
 * وجود له هنا: هذا تطبيق API وموقعه صفحات ساكنة. فنبنيه صراحةً إلى
 * `reset-password.html`، وهي الصفحة التي تقرأ الرمز والبريد من العنوان.
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

        $url = rtrim((string) config('app.url'), '/').'/reset-password.html?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور — كفالة')
            ->greeting('مرحباً')
            ->line('وصلنا طلبٌ لإعادة تعيين كلمة مرور حسابك.')
            ->action('إعادة تعيين كلمة المرور', $url)
            ->line("الرابط صالح لمدة {$minutes} دقيقة.")

            // لا نقول «تجاهل الرسالة» فقط: من لم يطلبها يحتاج أن يعرف أن
            // حسابه لم يُمسّ، وأن لا إجراء مطلوباً منه.
            ->line('إن لم تطلب هذا فلا حاجة لأي إجراء — لم يتغيّر شيء في حسابك.')
            ->salutation('فريق كفالة');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['token' => Lang::get('sent')];
    }
}

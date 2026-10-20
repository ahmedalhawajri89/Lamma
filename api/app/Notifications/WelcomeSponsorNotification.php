<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Locale;
use App\Support\SiteProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * من كفل من الموقع بلا حساب: أنشأنا له حساباً بكلمة مرورٍ عشوائية لا يعرفها،
 * وهذا رابطٌ يعيّن منه كلمته فيدخل ويتابع كفالته. صفحة الشكر تعده به.
 *
 * الرابط رمز إعادة تعيين عادي (صالحٌ ما يصلح له)، ومن فاته يطلب غيره من
 * «نسيت كلمة المرور» بالبريد نفسه.
 */
class WelcomeSponsorNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token, public string $child) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = SiteProfile::site()['name'];
        $url = Locale::absolute('/reset-password', $notifiable->preferredLocale()).'?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'welcome' => 1,
        ]);

        return (new MailMessage)
            ->subject(__('أهلاً بك في :site', ['site' => $site]))
            ->greeting(__('مرحباً :name،', ['name' => $notifiable->name]))
            ->line(__('شكراً لأنك صرت من لمّة :child. أنشأنا لك حساباً بهذا البريد تتابع منه الكفالة وتقاريرها وأين ذهب مالك.', ['child' => $this->child]))
            ->action(__('عيّن كلمة مرورك'), $url)
            ->line(__('الرابط صالح لمدة :minutes دقيقة. وإن فاتك فاطلب رابطاً جديداً من «نسيت كلمة المرور» بهذا البريد.', ['minutes' => config('auth.passwords.users.expire', 60)]))
            ->salutation(__('فريق :site', ['site' => $site]));
    }
}

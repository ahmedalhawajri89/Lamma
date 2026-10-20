<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\SponsorshipStatus;
use App\Mail\PaymentIssueMail;
use App\Mail\PaymentReceiptMail;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\WelcomeSponsorNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * البريد بلغة الكافل: من سجّل من الموقع الإنجليزي تصله رسائله بالإنجليزية،
 * ويصل كل ما تعد به صفحة الشكر (الإيصال، ورابط تعيين كلمة المرور).
 */
function englishCheckout(): array
{
    $orphan = Orphan::factory()->create(['monthly_need' => 100]);

    return [$orphan, [
        'orphan_reference' => $orphan->reference_code, 'monthly_amount' => 50, 'interval_months' => 1,
        'method' => 'card', 'card_token' => 'tok_demo_ok', 'card_last4' => '4242', 'disclosure' => true,
        'name' => 'Jane Doe', 'email' => 'jane@example.com',
    ]];
}

it('من كفل من الموقع الإنجليزي بلا حساب: حسابه بالإنجليزية، ويصله رابط تعيين كلمة المرور وإيصاله', function () {
    Notification::fake();
    Mail::fake();
    [, $payload] = englishCheckout();

    $this->postJson('/api/v1/public/checkout', $payload, ['X-Locale' => 'en'])->assertCreated();

    $user = User::where('email', 'jane@example.com')->sole();
    expect($user->locale)->toBe('en')
        ->and($user->preferredLocale())->toBe('en');

    Notification::assertSentTo($user, WelcomeSponsorNotification::class, function ($n) use ($user) {
        $mail = $n->toMail($user);
        expect($mail->actionUrl)->toContain('/en/reset-password?token=');

        return true;
    });
    Mail::assertSent(PaymentReceiptMail::class, fn ($m) => $m->hasTo('jane@example.com') && $m->locale === 'en');
});

it('الإيصال بالإنجليزية لمن لغته الإنجليزية، بعملته وما يعادلها', function () {
    $sponsor = Sponsor::factory()->create();
    // اسمٌ إنجليزي: الاسم كما كتبه صاحبه، ولا يُترجم
    $sponsor->user->update(['locale' => 'en', 'name' => 'Jane Doe']);
    $s = Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Active]);
    $s->orphan->forceFill(['translations' => ['en' => ['first_name' => 'Omnia']]])->save();
    $p = app(\App\Actions\Payments\RecordPayment::class)->handle($s, 'k-'.$s->id, method: PaymentMethod::BankTransfer, gatewayReference: 'TRX-1');

    $text = (new PaymentReceiptMail($p->fresh(['sponsorship.orphan', 'sponsor.user'])))->locale('en')->render();

    expect($text)->toContain('Receipt number: '.$p->receipt_number)
        ->toContain('Bank transfer')
        ->toContain('/en/dashboard#payments')
        ->not->toMatch('/\p{Arabic}{3,}/u');
});

it('الخصم الشهري الناجح يرسل إيصالاً، والمرفوض لا يرسل', function () {
    Mail::fake();
    $ok = Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Active, 'next_due_at' => now()->subDay(), 'billing_day' => 1,
        'payment_method' => PaymentMethod::Card->value, 'gateway_payment_method' => 'pm_demo_saved',
    ]);
    Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Active, 'next_due_at' => now()->subDay(), 'billing_day' => 1,
        'payment_method' => PaymentMethod::Card->value, 'gateway_payment_method' => 'pm_demo_decline',
    ]);

    $this->artisan('billing:run');

    Mail::assertSent(PaymentReceiptMail::class, 1);
    Mail::assertSent(PaymentReceiptMail::class, fn ($m) => $m->payment->sponsorship_id === $ok->id);
    Mail::assertSent(PaymentIssueMail::class, 1);
});

it('رابط إعادة التعيين من عنوان الموقع لا من رأس الطلب، وبلغة الحساب', function () {
    Notification::fake();
    $user = Sponsor::factory()->create()->user;
    $user->update(['locale' => 'en']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email], ['Host' => 'evil.example'])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use ($user) {
        $mail = $n->toMail($user);
        expect($mail->actionUrl)->toStartWith(rtrim(config('app.url'), '/').'/en/reset-password?')
            ->not->toContain('evil.example');

        return true;
    });
});

it('بريد التعثّر يُرسَل بلغة الكافل', function () {
    Mail::fake();
    $s = Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Active, 'next_due_at' => now()->subDay(), 'billing_day' => 1,
        'payment_method' => PaymentMethod::Card->value, 'gateway_payment_method' => 'pm_demo_decline',
    ]);
    $s->sponsor->user->update(['locale' => 'en']);

    $this->artisan('billing:run');

    Mail::assertSent(PaymentIssueMail::class, fn ($m) => $m->locale === 'en');
});

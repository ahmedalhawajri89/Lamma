<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\Fx;
use App\Support\Locale;
use App\Support\SiteProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * إيصال كل دفعةٍ وصلت: رقمه، والمبلغ بعملة الكافل وما يعادله، والطفل،
 * والكفالة، والتاريخ. كثيرٌ من الكفلاء يحتاجونه لزكاتهم أو ضرائبهم، وصفحة
 * الشكر تقول إنه وصل البريد. بلغة الكافل (User::preferredLocale).
 */
class PaymentReceiptMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('إيصال :number | :site', [
            'number' => $this->payment->receipt_number,
            'site' => SiteProfile::site()['name'],
        ]));
    }

    public function content(): Content
    {
        $p = $this->payment;
        $s = $p->sponsorship;
        $foreign = $p->currency !== Fx::base();

        return new Content(text: 'emails.payment-receipt', with: [
            'site' => SiteProfile::site()['name'],
            'child' => (string) ($s?->orphan?->translated('first_name') ?? $s?->orphan?->first_name),
            'amount' => number_format((float) $p->amount, 2).' '.$p->currency,
            'base' => $foreign ? number_format((float) $p->amount_base, 2).' '.Fx::base() : null,
            'date' => $p->paid_at?->timezone(config('kafala.display_timezone'))->locale($this->locale ?: app()->getLocale())->translatedFormat('j F Y'),
            'method' => match ($p->method) {
                PaymentMethod::Card, PaymentMethod::ApplePay => __('بطاقة').($p->card_last4 ? ' •••• '.$p->card_last4 : ''),
                PaymentMethod::BankTransfer => __('تحويل بنكي'),
                PaymentMethod::Cash => __('نقداً'),
            },
            'zakat' => (bool) $s?->is_zakat,
            'portal' => Locale::absolute('/dashboard', $this->locale).'#payments',
        ]);
    }
}

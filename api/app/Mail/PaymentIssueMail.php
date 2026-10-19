<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Sponsorship;
use App\Support\SiteProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * تعذّر خصم قسط الكافل: يُرسل مرة واحدة عند أول رفض في التعثّر، لا مع كل
 * محاولة. بلغة الكافل، ومعه رابط البوابة حيث يحدّث بطاقته.
 */
class PaymentIssueMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Sponsorship $sponsorship, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('تعذّر خصم قسط :name هذا الشهر', ['name' => $this->child()]));
    }

    public function content(): Content
    {
        return new Content(text: 'emails.payment-issue', with: [
            'site' => SiteProfile::site()['name'],
            'child' => $this->child(),
            // مطلقٌ لا نسبي: البريد يُقرأ خارج الموقع
            'portal' => url(lurl('/dashboard', $this->locale)).'#children',
        ]);
    }

    /** اسم الطفل بلغة الكافل: يُستدعى داخل لغة البريد، فيأخذ ترجمته إن وُجدت. */
    private function child(): string
    {
        return (string) ($this->sponsorship->orphan?->translated('first_name') ?? $this->sponsorship->orphan?->first_name);
    }
}

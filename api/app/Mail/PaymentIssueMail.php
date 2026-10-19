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
 * القسط المتعثّر، بلغة الكافل ومعه رابط البوابة، في ثلاث محطات:
 *  · failed: أول رفضٍ في التعثّر (لا مع كل محاولة)؛
 *  · covered: غطّى صندوق الاستمرارية شهر الطفل، وكم شهراً بقي؛
 *  · ended: انتهت الكفالة بعد أن نفدت التغطية، والطفل عاد ينتظر.
 */
class PaymentIssueMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Sponsorship $sponsorship,
        public string $reason = '',
        public string $stage = 'failed',
        public ?int $monthsLeft = null,
    ) {}

    public function envelope(): Envelope
    {
        $child = $this->child();

        return new Envelope(subject: match ($this->stage) {
            'covered' => __('غطّى صندوق الاستمرارية قسط :name', ['name' => $child]),
            'ended' => __('انتهت كفالتك لـ:name', ['name' => $child]),
            default => __('تعذّر خصم قسط :name هذا الشهر', ['name' => $child]),
        });
    }

    public function content(): Content
    {
        return new Content(text: 'emails.payment-issue', with: [
            'site' => SiteProfile::site()['name'],
            'child' => $this->child(),
            // مطلقٌ لا نسبي: البريد يُقرأ خارج الموقع
            'portal' => url(lurl('/dashboard', $this->locale)).'#children',
            'profile' => url(lurl('/orphan?id='.$this->sponsorship->orphan?->reference_code, $this->locale)),
        ]);
    }

    /** اسم الطفل بلغة الكافل: يُستدعى داخل لغة البريد، فيأخذ ترجمته إن وُجدت. */
    private function child(): string
    {
        return (string) ($this->sponsorship->orphan?->translated('first_name') ?? $this->sponsorship->orphan?->first_name);
    }
}

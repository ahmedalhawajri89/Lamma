<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * التحويل الشهري للجمعية: يُجهَّز مسوّدةً من الدفعات، ثم يُرسل بمرجع
 * الحوالة، ثم تؤكّد الجمعية وصوله. وما أُرسل لا يُعاد حسابه.
 */
enum TransferStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Sent = 'sent';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('مسوّدة'),
            self::Sent => __('أُرسل'),
            self::Confirmed => __('وصل'),
        };
    }

    /** المُرسَل والمؤكَّد مالٌ خرج فعلاً: يُعرض للكافل ولا يُعاد حسابه. */
    public function isOut(): bool
    {
        return $this !== self::Draft;
    }
}

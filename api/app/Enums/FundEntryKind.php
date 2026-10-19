<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * قيود صندوق الاستمرارية: حصةٌ شهرية من نسبة التشغيل، وتبرّعٌ له، تدخلانه؛
 * وتغطية قسطٍ متعثّر تخرج منه إلى جمعية الطفل.
 */
enum FundEntryKind: string
{
    use HasLabel;

    case Share = 'share';
    case Donation = 'donation';
    case Grant = 'grant';

    public function label(): string
    {
        return match ($this) {
            self::Share => __('حصة من التشغيل'),
            self::Donation => __('تبرّع للصندوق'),
            self::Grant => __('تغطية قسط'),
        };
    }

    public function isIn(): bool
    {
        return $this !== self::Grant;
    }
}

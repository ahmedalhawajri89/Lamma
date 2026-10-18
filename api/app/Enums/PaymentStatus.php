<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة الدفعة.
 */
enum PaymentStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('معلّقة'),
            self::Processing => __('قيد المعالجة'),
            self::Succeeded => __('مكتملة'),
            self::Failed => __('فاشلة'),
            self::Refunded => __('مُستردّة'),
        };
    }

    /** الدفعات وحدها التي تدخل في التقارير المالية. */
    public function isSettled(): bool
    {
        return $this === self::Succeeded;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة رسالة الكافل ↔ الطفل. الافتراضي مراجعةٌ إجبارية — سياسة حماية الطفل مطبَّقة في المخطّط لا في النيّة.
 */
enum MessageStatus: string
{
    use HasLabel;

    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => __('بانتظار المراجعة'),
            self::Approved => __('معتمَدة'),
            self::Rejected => __('مرفوضة'),
            self::Delivered => __('سُلّمت'),
        };
    }
}

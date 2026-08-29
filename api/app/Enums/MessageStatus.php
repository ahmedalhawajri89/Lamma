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
            self::PendingReview => 'بانتظار المراجعة',
            self::Approved => 'معتمَدة',
            self::Rejected => 'مرفوضة',
            self::Delivered => 'سُلّمت',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة رسالة التواصل.
 */
enum ContactStatus: string
{
    use HasLabel;

    case New = 'new';
    case InProgress = 'in_progress';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديدة',
            self::InProgress => 'قيد المعالجة',
            self::Closed => 'مغلقة',
        };
    }
}

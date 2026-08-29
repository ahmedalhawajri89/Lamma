<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة نشر المقال.
 */
enum PostStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسوّدة',
            self::Scheduled => 'مجدول',
            self::Published => 'منشور',
        };
    }
}

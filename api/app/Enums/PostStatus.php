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
            self::Draft => __('مسوّدة'),
            self::Scheduled => __('مجدول'),
            self::Published => __('منشور'),
        };
    }
}

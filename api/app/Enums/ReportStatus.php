<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * دورة اعتماد التقرير الربعي: يكتبه المشرف، ويعتمده المدير، ثم يُنشر للكافل.
 */
enum ReportStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسوّدة',
            self::Submitted => 'بانتظار المراجعة',
            self::Approved => 'معتمَد',
            self::Published => 'منشور للكافل',
        };
    }
}

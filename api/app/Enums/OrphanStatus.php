<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * دورة حياة ملف اليتيم — من المسوّدة إلى التخرّج.
 */
enum OrphanStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case FullyFunded = 'fully_funded';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';
    case Deceased = 'deceased';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('مسوّدة'),
            self::PendingVerification => __('بانتظار التوثيق'),
            self::Active => __('نشط'),
            self::FullyFunded => __('مكتمل التمويل'),
            self::Graduated => __('متخرّج'),
            self::Withdrawn => __('منسحب'),
            self::Deceased => __('متوفّى'),
        };
    }

    /** الحالات التي يجوز عرضها للجمهور على الموقع العام. */
    public function isPublic(): bool
    {
        return in_array($this, [self::Active, self::FullyFunded], true);
    }

    /** هل يقبل هذا الملف كفالة جديدة؟ */
    public function acceptsSponsorship(): bool
    {
        return $this === self::Active;
    }
}

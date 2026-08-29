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
            self::Draft => 'مسوّدة',
            self::PendingVerification => 'بانتظار التحقق',
            self::Active => 'نشط',
            self::FullyFunded => 'مكتمل التمويل',
            self::Graduated => 'متخرّج',
            self::Withdrawn => 'منسحب',
            self::Deceased => 'متوفّى',
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

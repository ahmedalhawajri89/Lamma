<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة الكفالة. الحالات الثلاث الأولى «حيّة» وتدخل في القيد الجزئي على قاعدة البيانات.
 */
enum SponsorshipStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار أول دفعة',
            self::Active => 'نشطة',
            self::Paused => 'موقوفة مؤقتاً',
            self::Cancelled => 'ملغاة',
            self::Completed => 'منتهية',
        };
    }

    /**
     * الحالات الحيّة — يجب أن تطابق تعريف العمود المُولَّد active_key
     * في الهجرة 2026_01_01_000006. تغييرُ إحداهما دون الأخرى يكسر القيد.
     */
    public function isLive(): bool
    {
        return in_array($this, [self::Pending, self::Active, self::Paused], true);
    }

    /** هل تُحتسب ضمن تمويل اليتيم؟ */
    public function countsTowardFunding(): bool
    {
        return in_array($this, [self::Active, self::Paused], true);
    }
}

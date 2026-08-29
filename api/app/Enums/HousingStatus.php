<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * وضع سكن الأسرة — يرجّح أولوية «كفالة الأسرة».
 */
enum HousingStatus: string
{
    use HasLabel;

    case Owned = 'owned';
    case Rented = 'rented';
    case Hosted = 'hosted';
    case Displaced = 'displaced';

    public function label(): string
    {
        return match ($this) {
            self::Owned => 'ملك',
            self::Rented => 'إيجار',
            self::Hosted => 'استضافة',
            self::Displaced => 'نزوح',
        };
    }
}

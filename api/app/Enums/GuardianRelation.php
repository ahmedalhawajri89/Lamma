<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * صلة الوصي باليتيم.
 */
enum GuardianRelation: string
{
    use HasLabel;

    case Mother = 'mother';
    case Father = 'father';
    case Grandmother = 'grandmother';
    case Grandfather = 'grandfather';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Sibling = 'sibling';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mother => 'الأم',
            self::Father => 'الأب',
            self::Grandmother => 'الجدة',
            self::Grandfather => 'الجد',
            self::Uncle => 'العم أو الخال',
            self::Aunt => 'العمة أو الخالة',
            self::Sibling => 'أخ أو أخت',
            self::Other => 'أخرى',
        };
    }
}

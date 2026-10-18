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
            self::Mother => __('الأم'),
            self::Father => __('الأب'),
            self::Grandmother => __('الجدة'),
            self::Grandfather => __('الجد'),
            self::Uncle => __('العم أو الخال'),
            self::Aunt => __('العمة أو الخالة'),
            self::Sibling => __('أخ أو أخت'),
            self::Other => __('أخرى'),
        };
    }
}

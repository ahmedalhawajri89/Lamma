<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * أدوار النظام الأربعة. المصدر الوحيد لأسماء الأدوار — لا نص سحري في أي مكان آخر.
 */
enum UserRole: string
{
    use HasLabel;

    case Admin = 'admin';
    case Accountant = 'accountant';
    case FieldSupervisor = 'field_supervisor';
    case Sponsor = 'sponsor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدير النظام',
            self::Accountant => 'محاسب',
            self::FieldSupervisor => 'مشرف ميداني',
            self::Sponsor => 'كافل',
        };
    }

    /** أدوار الموظفين — من يدخل لوحة الإدارة. */
    public static function staff(): array
    {
        return [self::Admin, self::Accountant, self::FieldSupervisor];
    }
}

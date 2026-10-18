<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة حساب المستخدم.
 */
enum UserStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('بانتظار التفعيل'),
            self::Active => __('نشط'),
            self::Suspended => __('موقوف'),
        };
    }
}

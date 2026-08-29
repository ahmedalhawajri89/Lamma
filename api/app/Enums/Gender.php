<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * جنس اليتيم.
 */
enum Gender: string
{
    use HasLabel;

    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'ذكر',
            self::Female => 'أنثى',
        };
    }
}

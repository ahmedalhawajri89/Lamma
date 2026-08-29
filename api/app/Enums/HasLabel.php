<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * سلوك مشترك لكل enums المشروع: تسمية عربية للعرض، وقائمة جاهزة للواجهة.
 * وجودها هنا يعني أن اللوحة والـ API يقرآن النص نفسه من مصدر واحد.
 */
trait HasLabel
{
    abstract public function label(): string;

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            static fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

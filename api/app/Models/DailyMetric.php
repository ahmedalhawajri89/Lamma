<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * صف واحد لكل يوم — ما تقرأ منه مخططات اللوحة.
 *
 * لا مفتاح مُتزايد هنا: التاريخ نفسه هو المفتاح الأساسي،
 * فيستحيل وجود صفّين لليوم نفسه.
 */
class DailyMetric extends Model
{
    protected $primaryKey = 'date';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'revenue_base' => 'decimal:2',
            'disbursed_base' => 'decimal:2',
        ];
    }
}

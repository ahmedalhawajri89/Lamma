<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * سعر صرف عملةٍ إلى عملة الأساس، الحاليّ وحده. يقرؤه App\Support\Fx.
 *
 * @property string $currency
 * @property string $rate_to_base
 * @property string $source
 * @property Carbon|null $fetched_at
 */
class ExchangeRate extends Model
{
    protected $fillable = ['currency', 'rate_to_base', 'source', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'rate_to_base' => 'decimal:8',
            'fetched_at' => 'datetime',
        ];
    }
}

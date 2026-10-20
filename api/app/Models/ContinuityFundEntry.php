<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FundEntryKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * قيدٌ في صندوق الاستمرارية. الرصيد مجموع الداخل ناقص الخارج، من القيود
 * نفسها لا من رقمٍ محفوظ.
 *
 * @property FundEntryKind $kind
 * @property Carbon $period
 */
class ContinuityFundEntry extends Model
{
    protected $fillable = [
        'kind', 'amount_base', 'period', 'sponsorship_id', 'orphan_id', 'association_id', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => FundEntryKind::class,
            'amount_base' => 'decimal:2',
            'period' => 'date',
        ];
    }

    public static function balance(): float
    {
        $in = (float) self::query()->whereIn('kind', [FundEntryKind::Share, FundEntryKind::Donation])->sum('amount_base');
        $out = (float) self::query()->where('kind', FundEntryKind::Grant)->sum('amount_base');

        return round($in - $out, 2);
    }

    /** @return BelongsTo<Sponsorship, $this> */
    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class);
    }

    /** @return BelongsTo<Orphan, $this> */
    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    /** @return BelongsTo<Association, $this> */
    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

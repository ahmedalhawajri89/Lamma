<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * الصرف — المال الخارج.
 * هذا الجدول يحوّل حلقة الشفافية من نسب مكتوبة يدوياً إلى تجميع مُدقَّق.
 *
 * @property int $id
 * @property int|null $orphan_id
 * @property int $category_id
 * @property string $amount
 * @property string $currency
 * @property string|null $fx_rate
 * @property string $amount_base
 * @property Carbon $spent_on
 * @property string|null $description
 * @property string|null $receipt_path
 * @property int|null $recorded_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
class Disbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'orphan_id', 'category_id', 'amount', 'currency', 'amount_base',
        'spent_on', 'description', 'receipt_path', 'recorded_by', 'updated_by', 'fx_rate',

        // الاعتماد فعلٌ منفصل عن التسجيل، ولا يملكه المسجِّل نفسه
        'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_base' => 'decimal:2',
            'spent_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Orphan, $this> */
    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    /** @return BelongsTo<DisbursementCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DisbursementCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * لا يدخل تقارير الشفافية إلا الصرف المعتمَد.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('spent_on', $year);
    }
}

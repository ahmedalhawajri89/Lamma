<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * الصرف — المال الخارج.
 * هذا الجدول يحوّل حلقة الشفافية من نسب مكتوبة يدوياً إلى تجميع مُدقَّق.
 */
class Disbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'orphan_id', 'category_id', 'amount', 'currency', 'amount_base',
        'spent_on', 'description', 'receipt_path', 'recorded_by',
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

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DisbursementCategory::class, 'category_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** لا يدخل تقارير الشفافية إلا الصرف المعتمَد. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('spent_on', $year);
    }
}

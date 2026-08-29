<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * التقرير الربعي.
 *
 * UNIQUE(orphan_id, period_year, period_quarter) في الهجرة يحوّل وعد
 * «تقرير كل ٣ أشهر» من نص تسويقي إلى قيد لا يمكن خرقه.
 */
class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'orphan_id', 'period_year', 'period_quarter', 'title', 'summary',
        'academic_note', 'health_note', 'psychosocial_note',
        'spending_breakdown', 'visit_date', 'supervisor_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_quarter' => 'integer',
            'spending_breakdown' => 'array',
            'visit_date' => 'date',
            'status' => ReportStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReportMedia::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(ReportView::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Published)->whereNotNull('published_at');
    }

    /**
     * التقارير التي لم يفتحها هذا الكافل بعد.
     * هذا ما يشغّل شارة «تقريران جديدان»، بدل الرقم المكتوب في dashboard.html.
     */
    public function scopeUnseenBy(Builder $query, int $sponsorId): Builder
    {
        return $query->published()->whereDoesntHave(
            'views',
            fn (Builder $q) => $q->where('sponsor_id', $sponsorId),
        );
    }

    public function periodLabel(): string
    {
        return "الربع {$this->period_quarter} — {$this->period_year}";
    }
}

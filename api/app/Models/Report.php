<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * التقرير الربعي.
 *
 * UNIQUE(orphan_id, period_year, period_quarter) في الهجرة يحوّل وعد
 * «تقرير كل ٣ أشهر» من نص تسويقي إلى قيد لا يمكن خرقه.
 *
 * @property int $id
 * @property int $orphan_id
 * @property int $period_year
 * @property int $period_quarter
 * @property string $title
 * @property ReportStatus $status
 * @property array<string, mixed>|null $spending_breakdown
 * @property Carbon|null $visit_date
 * @property Carbon|null $published_at
 * @property int|null $supervisor_id
 * @property-read Orphan|null $orphan
 */
class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'orphan_id', 'period_year', 'period_quarter', 'title', 'summary',
        'academic_note', 'health_note', 'psychosocial_note',
        'spending_breakdown', 'visit_date', 'supervisor_id', 'status',

        // تُسندها PublishReport بعد الاعتماد
        'approved_by', 'published_at',
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

    /** @return BelongsTo<Orphan, $this> */
    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<ReportMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ReportMedia::class);
    }

    /** @return HasMany<ReportView, $this> */
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
    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
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
        return "الربع {$this->period_quarter} من {$this->period_year}";
    }
}

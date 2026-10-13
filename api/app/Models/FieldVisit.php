<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** الزيارة الميدانية — ما يحوّل «١١٤٢٠ زيارة موثّقة» إلى COUNT.
 *
 * @property int $id
 * @property int $orphan_id
 * @property int|null $supervisor_id
 * @property Carbon $visited_at
 * @property string|null $findings
 * @property array<int, string>|null $needs_flagged
 * @property Carbon|null $next_visit_due
 */
class FieldVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'orphan_id', 'supervisor_id', 'visited_at', 'findings',
        'needs_flagged', 'next_visit_due', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'next_visit_due' => 'date',
            'needs_flagged' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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

    /**
     * الزيارات المتأخرة: **آخر** زيارة للطفل تجاوزت موعد الزيارة التالية.
     *
     * كانت تعدّ كل زيارة قديمة فات موعدها، ولو تلتها زيارة أحدث للطفل نفسه،
     * فتظهر «231 متأخرة» والمتأخر فعلاً أقل بكثير. الزيارة التي جاءت بعدها
     * زيارةٌ أخرى قد أُنجز ما بعدها.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            // «اليوم» يوم المنظمة لا يوم UTC
            ->whereDate('field_visits.next_visit_due', '<', now(config('kafala.display_timezone'))->toDateString())
            ->whereNotExists(fn ($later) => $later
                ->from('field_visits as later')
                ->whereColumn('later.orphan_id', 'field_visits.orphan_id')
                ->whereColumn('later.visited_at', '>', 'field_visits.visited_at'));
    }
}

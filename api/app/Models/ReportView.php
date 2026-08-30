<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** أثر فتح الكافل للتقرير — ما يُطفئ شارة «جديد».
 *
 * @property int $id
 * @property int $report_id
 * @property int $sponsor_id
 * @property Carbon $viewed_at
 */
class ReportView extends Model
{
    public $timestamps = false;

    protected $fillable = ['report_id', 'sponsor_id', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return BelongsTo<Sponsor, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }
}

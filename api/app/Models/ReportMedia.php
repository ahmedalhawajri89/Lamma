<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportMedia extends Model
{
    use HasFactory;

    protected $table = 'report_media';

    protected $fillable = ['report_id', 'type', 'path', 'caption', 'sort_order'];

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}

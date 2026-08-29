<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** الزيارة الميدانية — ما يحوّل «١١٤٢٠ زيارة موثّقة» إلى COUNT. */
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

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** الزيارات المتأخرة — تغذّي تنبيهات لوحة الإدارة. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereDate('next_visit_due', '<', now()->toDateString());
    }
}

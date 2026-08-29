<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HousingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * الأسرة — الطبقة التي تجعل «كفالة الأسرة» ممكنة،
 * وتربط الإخوة تلقائياً بلا جدول self-join.
 */
class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_code', 'guardian_id', 'country_id', 'city_id', 'address',
        'members_count', 'monthly_income', 'housing_status',
    ];

    protected function casts(): array
    {
        return [
            'members_count' => 'integer',
            'monthly_income' => 'decimal:2',
            'housing_status' => HousingStatus::class,
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function orphans(): HasMany
    {
        return $this->hasMany(Orphan::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** الدولة — جدول مرجعي يمنع تعدّد صيغ الاسم نفسه. */
class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'iso2', 'iso3', 'name_ar', 'name_en', 'flag_emoji',
        'currency_code', 'is_operational', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_operational' => 'boolean', 'sort_order' => 'integer'];
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }

    /** الدول التي نعمل فيها فعلاً — لا كل دول العالم. */
    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('is_operational', true)->orderBy('sort_order');
    }
}

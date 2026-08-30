<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $country_id
 * @property string $name_ar
 */
class City extends Model
{
    use HasFactory;

    protected $fillable = ['country_id', 'name_ar', 'name_en'];

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<Family, $this> */
    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }
}

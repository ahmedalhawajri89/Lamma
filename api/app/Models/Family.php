<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HousingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * الأسرة — الطبقة التي تجعل «كفالة الأسرة» ممكنة،
 * وتربط الإخوة تلقائياً بلا جدول self-join.
 *
 * @property int $id
 * @property string $family_code
 * @property int $guardian_id
 * @property int $country_id
 * @property int|null $city_id
 * @property string|null $address
 * @property int $members_count
 * @property string|null $monthly_income
 * @property HousingStatus $housing_status
 * @property-read Guardian|null $guardian
 * @property-read Country|null $country
 * @property-read City|null $city
 */
class Family extends Model
{
    use HasFactory;
    use LogsActivity;

    /** المكان والسكن والدخل: ما يُبنى عليه تقدير حاجة الأسرة */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['country_id', 'city_id', 'address', 'members_count', 'monthly_income', 'housing_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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

    /** @return BelongsTo<Guardian, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<Orphan, $this> */
    public function orphans(): HasMany
    {
        return $this->hasMany(Orphan::class);
    }
}

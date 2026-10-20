<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CareArrangement;
use App\Enums\HousingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * الأسرة — الطبقة التي تجعل «كفالة الأسرة» ممكنة،
 * وتربط الإخوة تلقائياً بلا جدول self-join.
 *
 * وعليها ترتيب الرعاية: مع من يعيش أطفالها، ومن أقرّ بذلك ومتى. والجمعية
 * الشريكة التي تعرفها وتزورها.
 *
 * @property int $id
 * @property string $family_code
 * @property int $guardian_id
 * @property int $country_id
 * @property int|null $association_id
 * @property int|null $city_id
 * @property string|null $address
 * @property int $members_count
 * @property string|null $monthly_income
 * @property HousingStatus $housing_status
 * @property CareArrangement|null $care_arrangement
 * @property Carbon|null $care_attested_at
 * @property Carbon|null $care_review_due_at
 * @property-read Guardian|null $guardian
 * @property-read Country|null $country
 * @property-read City|null $city
 * @property-read Association|null $association
 */
class Family extends Model
{
    use HasFactory;
    use LogsActivity;

    /** المدة التي يبقى فيها إقرار الرعاية صالحاً قبل أن يُراجَع بزيارة. */
    public const CARE_REVIEW_MONTHS = 12;

    /** المكان والسكن والدخل والرعاية: ما يُبنى عليه تقدير حاجة الأسرة ونشرها */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['country_id', 'association_id', 'city_id', 'address', 'members_count', 'monthly_income', 'housing_status', 'care_arrangement', 'care_attested_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'family_code', 'guardian_id', 'country_id', 'association_id', 'city_id', 'address',
        'members_count', 'monthly_income', 'housing_status',
        'care_arrangement', 'care_attested_at', 'care_attested_by', 'care_review_due_at',
    ];

    protected function casts(): array
    {
        return [
            'members_count' => 'integer',
            'monthly_income' => 'decimal:2',
            'housing_status' => HousingStatus::class,
            'care_arrangement' => CareArrangement::class,
            'care_attested_at' => 'datetime',
            'care_review_due_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // أسرة جديدة بلا جمعية تأخذ الافتراضية إن كانت واضحة (Association::defaultFor)
        static::creating(function (Family $family): void {
            if ($family->association_id === null && $family->country_id !== null) {
                $family->association_id = Association::defaultFor((int) $family->country_id, auth()->user());
            }
        });
    }

    /**
     * يُسجَّل إقرار الرعاية الآن: من أقرّ، ومتى يُراجَع. تستدعيه شاشة الأسرة
     * والزيارة الميدانية حين يؤكّد المشرف أين يعيش الطفل.
     */
    public function attestCare(CareArrangement $arrangement, ?User $by = null): void
    {
        $this->forceFill([
            'care_arrangement' => $arrangement,
            'care_attested_at' => now(),
            'care_attested_by' => $by?->id,
            'care_review_due_at' => now()->addMonths(self::CARE_REVIEW_MONTHS)->toDateString(),
        ])->save();
    }

    /** إقرارٌ قائم لم تمضِ عليه مدة المراجعة. */
    public function hasCurrentCareAttestation(): bool
    {
        return $this->care_attested_at !== null
            && $this->care_attested_at->greaterThan(now()->subMonths(self::CARE_REVIEW_MONTHS));
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

    /** @return BelongsTo<Association, $this> */
    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<User, $this> */
    public function careAttester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'care_attested_by');
    }

    /** @return HasMany<Orphan, $this> */
    public function orphans(): HasMany
    {
        return $this->hasMany(Orphan::class);
    }
}

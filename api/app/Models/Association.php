<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssociationStatus;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * الجمعية الشريكة: الجهة المحلية الموثّقة التي تعرف الأسرة وتزورها، وإليها
 * يُحوَّل مال الكفالة. والطفل لا يُنشر للكفالة إلا عبر جمعية موثّقة.
 *
 * @property int $id
 * @property string $slug
 * @property string $name_ar
 * @property string|null $name_en
 * @property int $country_id
 * @property AssociationStatus $status
 * @property Carbon|null $verified_at
 * @property Carbon|null $safeguarding_signed_at
 * @property Carbon|null $family_care_attested_at
 * @property-read Country|null $country
 */
class Association extends Model
{
    use HasFactory;
    use LogsActivity;

    /** الحالة والتوثيق والتوقيعات: ما يقرّر ظهور أطفال الجمعية */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name_ar', 'name_en', 'status', 'verified_at', 'suspension_reason', 'safeguarding_signed_at', 'family_care_attested_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'slug', 'name_ar', 'name_en', 'country_id', 'city_id',
        'registration_number', 'registration_authority',
        'status', 'verified_at', 'verified_by', 'suspension_reason',
        'safeguarding_signed_at', 'family_care_attested_at',
        'email', 'phone', 'website', 'about_ar', 'about_en',
        'transfer_details', 'transfer_currency',
    ];

    /** بيانات التحويل البنكي لا تخرج في أي JSON */
    protected $hidden = ['transfer_details'];

    protected function casts(): array
    {
        return [
            'status' => AssociationStatus::class,
            'verified_at' => 'datetime',
            'safeguarding_signed_at' => 'datetime',
            'family_care_attested_at' => 'datetime',
            'transfer_details' => 'encrypted',
        ];
    }

    /** الاسم بلغة الصفحة، والعربي إن لم يُكتب له مقابل. */
    public function displayName(): string
    {
        return Locale::pick($this->name_ar, $this->name_en);
    }

    public function isVerified(): bool
    {
        return $this->status === AssociationStatus::Verified;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', AssociationStatus::Verified);
    }

    /**
     * الجمعية التي تُسند إليها أسرةٌ جديدة حين لا تُحدَّد: جمعية الموظف إن كان
     * لجمعية، وإلا الجمعية الموثّقة الوحيدة في البلد. وإن تعدّدت أو لم توجد
     * فلا تخمين: تبقى بلا جمعية، ولا يُنشر طفلها حتى تُسنَد (OrphanPolicy).
     */
    public static function defaultFor(int $countryId, ?User $user = null): ?int
    {
        $own = $user?->staffProfile?->association_id;
        if ($own !== null) {
            return $own;
        }

        $verified = self::verified()->where('country_id', $countryId)->limit(2)->pluck('id');

        return $verified->count() === 1 ? (int) $verified->first() : null;
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

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasMany<Family, $this> */
    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }

    /** @return HasMany<StaffProfile, $this> */
    public function staff(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }
}

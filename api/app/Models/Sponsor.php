<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SponsorshipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * ملف الكافل — 1:1 مع المستخدم.
 *
 * @property int $id
 * @property int $user_id
 * @property string $sponsor_code
 * @property int|null $country_id
 * @property string|null $city
 * @property string $preferred_currency
 * @property bool $prefers_zakat
 * @property bool $is_anonymous
 * @property array<string, mixed>|null $communication_prefs
 * @property Carbon|null $sponsoring_since
 * @property-read User|null $user
 * @property-read Country|null $country
 */
class Sponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'sponsor_code', 'country_id', 'city', 'preferred_currency',
        'prefers_zakat', 'is_anonymous', 'communication_prefs', 'sponsoring_since',
        'internal_notes',
    ];

    /** ملاحظات الإدارة لا تخرج في أي استجابة يقرأها الكافل نفسه. */
    protected $hidden = ['internal_notes'];

    protected function casts(): array
    {
        return [
            'prefers_zakat' => 'boolean',
            'is_anonymous' => 'boolean',
            'communication_prefs' => 'array',
            'sponsoring_since' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<Sponsorship, $this> */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<ReportView, $this> */
    public function reportViews(): HasMany
    {
        return $this->hasMany(ReportView::class);
    }

    /**
     * الكفلاء الذين لهم كفالة نشطة واحدة على الأقل.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas(
            'sponsorships',
            fn ($q) => $q->where('status', SponsorshipStatus::Active),
        );
    }

    /** إجمالي الالتزام الشهري — يظهر في رأس لوحة الكافل. */
    public function monthlyCommitment(): float
    {
        return (float) $this->sponsorships()
            ->where('status', SponsorshipStatus::Active)
            ->sum('monthly_amount');
    }
}

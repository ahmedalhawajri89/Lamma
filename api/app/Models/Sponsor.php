<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ملف الكافل — 1:1 مع المستخدم.
 */
class Sponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'sponsor_code', 'country_id', 'city', 'preferred_currency',
        'prefers_zakat', 'is_anonymous', 'communication_prefs', 'sponsoring_since',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reportViews(): HasMany
    {
        return $this->hasMany(ReportView::class);
    }

    /** الكفلاء الذين لهم كفالة نشطة واحدة على الأقل. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('sponsorships', fn (Builder $q) => $q->active());
    }

    /** إجمالي الالتزام الشهري — يظهر في رأس لوحة الكافل. */
    public function monthlyCommitment(): float
    {
        return (float) $this->sponsorships()->active()->sum('monthly_amount');
    }
}

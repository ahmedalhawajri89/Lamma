<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Enums\SponsorshipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * اليتيم — قلب النظام.
 *
 * الدولة والمدينة والوصي كلها عبر `family`، لأن يوسف وإخوته الأربعة
 * يشتركون في العنوان نفسه؛ تكرارها على كل يتيم يخلق تناقضاً حتمياً.
 *
 * @property-read int $age
 * @property-read string $public_name
 */
class Orphan extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'reference_code', 'first_name', 'last_name', 'gender', 'date_of_birth',
        'family_id', 'program_id', 'status', 'is_urgent', 'photo_path',
        'grade_level', 'health_status', 'dream', 'story', 'monthly_need',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'status' => OrphanStatus::class,
            'is_urgent' => 'boolean',
            'monthly_need' => 'decimal:2',
            'funded_percent' => 'integer',
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------- العلاقات */

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrphanDocument::class);
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class);
    }

    public function fieldVisits(): HasMany
    {
        return $this->hasMany(FieldVisit::class);
    }

    /** كشف الحساب: كل دفعة وصلت لهذا الطفل عبر أي كفالة. */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Sponsorship::class);
    }

    /** الإخوة: أبناء الأسرة نفسها عدا هذا الطفل. */
    public function siblings(): HasMany
    {
        return $this->hasMany(self::class, 'family_id', 'family_id')
            ->whereKeyNot($this->getKey());
    }

    /* ------------------------------------------------------------------ النطاقات */

    /** ما يجوز عرضه على الموقع العام: موثّق ومنشور. */
    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [OrphanStatus::Active, OrphanStatus::FullyFunded])
            ->whereNotNull('published_at')
            ->whereNotNull('verified_at');
    }

    /** ترتيب شبكة orphans.html: العاجل أولاً، ثم الأقل تمويلاً. */
    public function scopeByNeed(Builder $query): Builder
    {
        return $query->orderByDesc('is_urgent')->orderBy('funded_percent');
    }

    public function scopeUrgentUnfunded(Builder $query): Builder
    {
        return $query->where('is_urgent', true)->where('funded_percent', '<', 100);
    }

    /**
     * بحث شريط orphans.html.
     * الكلمات القصيرة (أقل من أربعة أحرف) تسقط من فهرس FULLTEXT الافتراضي،
     * وأسماء كثيرة بالعربية أقصر من ذلك — فنرجع إلى LIKE عندها.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        if (mb_strlen($term) >= 4) {
            return $query->whereFullText(['first_name', 'last_name', 'story'], $term);
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('first_name', 'like', "{$term}%")
                ->orWhere('last_name', 'like', "{$term}%")
                ->orWhere('reference_code', 'like', "%{$term}%");
        });
    }

    /* ----------------------------------------------------------------- المشتقات */

    /** العمر يُشتق من التاريخ، فلا يحتاج تحديثاً سنوياً لكل صف. */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->date_of_birth?->diffInYears(now()));
    }

    /** الاسم الأول فقط للعرض العام — حمايةً لخصوصية الطفل. */
    protected function publicName(): Attribute
    {
        return Attribute::get(fn (): string => $this->first_name);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /* -------------------------------------------------------------------- سلوك */

    /**
     * إعادة حساب نسبة التمويل من الكفالات الحيّة.
     * يستدعيه SponsorshipObserver لا كود المتحكّم، فلا يمكن نسيانه.
     */
    public function recalculateFunding(): void
    {
        $covered = (float) $this->sponsorships()
            ->whereIn('status', [SponsorshipStatus::Active, SponsorshipStatus::Paused])
            ->sum('monthly_amount');

        $need = (float) $this->monthly_need;
        $percent = $need > 0 ? (int) min(100, round($covered / $need * 100)) : 0;

        $status = $this->status;
        if ($percent >= 100 && $status === OrphanStatus::Active) {
            $status = OrphanStatus::FullyFunded;
        } elseif ($percent < 100 && $status === OrphanStatus::FullyFunded) {
            $status = OrphanStatus::Active;
        }

        // updateQuietly: لا نُطلق أحداثاً تعيد استدعاء هذه الدالة
        $this->updateQuietly(['funded_percent' => $percent, 'status' => $status]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'is_urgent', 'monthly_need', 'program_id', 'verified_at', 'published_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}

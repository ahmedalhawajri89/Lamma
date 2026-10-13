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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
 * @property int $id
 * @property string $reference_code
 * @property string $first_name
 * @property string $last_name
 * @property Gender $gender
 * @property Carbon $date_of_birth
 * @property int $family_id
 * @property int $program_id
 * @property OrphanStatus $status
 * @property bool $is_urgent
 * @property string|null $photo_path
 * @property string|null $grade_level
 * @property string|null $health_status
 * @property string|null $dream
 * @property string|null $story
 * @property string $monthly_need
 * @property int $funded_percent
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property Carbon|null $published_at
 * @property-read int $age
 * @property-read string $full_name
 * @property-read string $public_name
 * @property-read Family|null $family
 * @property-read Program|null $program
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrphanDocument> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Sponsorship> $sponsorships
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Orphan> $familyMembers
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

        /**
         * حقول دورة الحياة — تُسندها الأفعال (VerifyOrphan) لا طلبات المستخدم.
         * وجودها هنا لازم لأن الوضع الصارم يمنع الإسناد الصامت، وغيابها كان
         * سيُسقط التوثيق والنشر عند أول استعمال. وحمايتها من الطلبات مسؤولية
         * FormRequests التي لا تسمح بها أصلاً.
         */
        'funded_percent', 'verified_at', 'verified_by', 'published_at',
    ];

    /** يطابق DEFAULT 0 في المخطّط، فلا يخرج الكائن الجديد بنسبة null. */
    /**
     * القيم الافتراضية على الموديل لا على الجدول وحده.
     *
     * `default('draft')` في الهجرة تخصّ MySQL: تملأ العمود عند الإدراج، ولا
     * تصل النسخة التي في الذاكرة. فيرجع `Orphan::create()` نموذجاً حالته
     * `null`، ويسقط `$this->status->value` في المورد بخطأ ٥٠٠ — عند الإنشاء
     * وحده، وهو المسار الذي لم تكن له واجهة فلم يمرّ به أحد.
     *
     * تكرار القيمة هنا ليس ازدواجاً بل مطابقة: الجدول يحرس البيانات،
     * والموديل يحرس السطر التالي من الكود.
     */
    protected $attributes = [
        'funded_percent' => 0,
        'status' => OrphanStatus::Draft->value,
        'is_urgent' => false,
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

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasMany<OrphanDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(OrphanDocument::class);
    }

    /** @return HasMany<Sponsorship, $this> */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** @return HasMany<Disbursement, $this> */
    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class);
    }

    /** @return HasMany<FieldVisit, $this> */
    public function fieldVisits(): HasMany
    {
        return $this->hasMany(FieldVisit::class);
    }

    /** كشف الحساب: كل دفعة وصلت لهذا الطفل عبر أي كفالة. */
    /** @return HasManyThrough<Payment, Sponsorship, $this> */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Sponsorship::class);
    }

    /**
     * كل أبناء الأسرة — هذا الطفل منهم.
     *
     * لماذا لا نستثنيه هنا بـ `whereKeyNot($this->getKey())`؟
     * لأن Eloquent يبني العلاقة أثناء التحميل المسبق على نسخة فارغة من
     * الموديل، فيكون المفتاح `null` ولا يُستثنى أحد — فيظهر الطفل في قائمة
     * إخوته. الاستثناء يجب أن يقع بعد التحميل، حيث يُعرف المفتاح فعلاً،
     * ولذلك هو في `siblings()` أدناه لا في العلاقة.
     */
    /** @return HasMany<Orphan, $this> */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(self::class, 'family_id', 'family_id');
    }

    /** الإخوة: أبناء الأسرة عدا هذا الطفل. */
    public function siblings(): Collection
    {
        return $this->familyMembers->reject(fn (self $o): bool => $o->is($this))->values();
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
     * البحث — ثلاثة مسارات لأن المستخدم يبحث بثلاث طرق مختلفة:
     *
     *  1. **معرّف** (`KFL-1042`) — أكثر ما يُلصق في مربع البحث، وفهرس
     *     FULLTEXT لا يغطّيه إطلاقاً لأنه على الأسماء والقصة. وجود رقم في
     *     النص يكفي للتمييز: لا اسم عربي يحمل رقماً.
     *  2. **اسم قصير** (أقل من أربعة أحرف) — يسقط من فهرس FULLTEXT لأن
     *     `innerodb_ft_min_token_size` ثلاثة افتراضياً، وأسماء كثيرة
     *     بالعربية أقصر من ذلك: «ليان» و«جنى» و«آية».
     *  3. **نص أطول** — هنا وحده يفيد FULLTEXT.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = fn (string $s): string => addcslashes($s, '%_\\');

        // رمز الطفل أو رمز أسرته
        if (preg_match('/\d/', $term) === 1) {
            return $query->where(fn (Builder $q) => $q
                ->where('reference_code', 'like', '%'.$like($term).'%')
                ->orWhereHas('family', fn (Builder $f) => $f->where('family_code', 'like', '%'.$like($term).'%')));
        }

        /*
         * الاسم: كل كلمة بداية اسمٍ أول أو اسم عائلة، أيّاً كان طولها. كان البحث
         * يتغيّر بعد الحرف الرابع إلى نصّ كامل بكلمات تامة، فـ«محم» تجد محمداً
         * و«محمو» لا تجد شيئاً، ويدخل نصّ القصة فيأتي بما لا علاقة له.
         */
        foreach (preg_split('/\s+/u', $term) as $word) {
            $query->where(fn (Builder $q) => $q
                ->where('first_name', 'like', $like($word).'%')
                ->orWhere('last_name', 'like', $like($word).'%'));
        }

        return $query;
    }

    /* ----------------------------------------------------------------- المشتقات */

    /** العمر يُشتق من التاريخ، فلا يحتاج تحديثاً سنوياً لكل صف. */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->date_of_birth->diffInYears(now()));
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
            ->whereIn('status', array_filter(
                SponsorshipStatus::cases(),
                fn (SponsorshipStatus $s): bool => $s->countsTowardFunding(),
            ))
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

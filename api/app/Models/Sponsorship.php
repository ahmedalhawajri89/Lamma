<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SponsorshipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * الكفالة — العلاقة المحورية بين كافل ويتيم.
 *
 * ليست belongsToMany بسيطة: لها مبلغ منسوخ، وحالة، ودورة فوترة،
 * وتاريخ بداية ونهاية وسبب إلغاء. لذا هي جدول محوري مُثرى.
 *
 * @property int $id
 * @property string $reference_code
 * @property int $sponsor_id
 * @property int $orphan_id
 * @property int $program_id
 * @property string $monthly_amount
 * @property string $currency
 * @property int $share_percent
 * @property bool $is_zakat
 * @property SponsorshipStatus $status
 * @property int $billing_day
 * @property Carbon|null $started_at
 * @property Carbon|null $next_due_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $ended_at
 * @property string|null $cancellation_reason
 * @property-read Orphan|null $orphan
 * @property-read Sponsor|null $sponsor
 * @property-read Program|null $program
 */
class Sponsorship extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'reference_code', 'sponsor_id', 'orphan_id', 'program_id',
        'monthly_amount', 'currency', 'share_percent', 'is_zakat',
        'status', 'billing_day', 'interval_months', 'payment_method', 'started_at', 'next_due_at',

        // تُسندها CancelSponsorship و SponsorshipController لا طلب المستخدم
        'paused_at', 'ended_at', 'cancellation_reason',
    ];

    /**
     * عمود مُولَّد في قاعدة البيانات — يُقرأ ولا يُكتب أبداً.
     * محاولة الكتابة عليه خطأ SQL، فنمنعها هنا.
     */
    protected $guarded = ['active_key'];

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'share_percent' => 'integer',
            'billing_day' => 'integer',
            'interval_months' => 'integer',
            'is_zakat' => 'boolean',
            'status' => SponsorshipStatus::class,
            'started_at' => 'date',
            'next_due_at' => 'date',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------- العلاقات */

    /** @return BelongsTo<Sponsor, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /** @return BelongsTo<Orphan, $this> */
    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /* ------------------------------------------------------------------ النطاقات */

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SponsorshipStatus::Pending,
            SponsorshipStatus::Active,
            SponsorshipStatus::Paused,
        ]);
    }

    /**
     * كفالة دُفع لها: نشطة أو موقوفة مؤقتاً. منها يُفتح للكافل ما يخصّ الطفل
     * (التقارير والرسائل). الحجز المعلّق بانتظار أول دفعة ليس منها: كان يفتح
     * تقارير الطفل الصحية والنفسية لمن سجّل وحجز ولم يدفع شيئاً.
     */
    public function scopeFunded(Builder $query): Builder
    {
        return $query->whereIn('status', [SponsorshipStatus::Active, SponsorshipStatus::Paused]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SponsorshipStatus::Active);
    }

    /** ما يلتقطه أمر الفوترة اليومي — يستعمل idx_sponsorships_due. */
    public function scopeDueBy(Builder $query, ?string $date = null): Builder
    {
        return $query
            ->where('status', SponsorshipStatus::Active)
            ->whereDate('next_due_at', '<=', $date ?? now()->toDateString());
    }

    /* -------------------------------------------------------------------- سلوك */

    /**
     * أقرب يوم فوترة قادم (بعد اليوم). يُستعمل عند الاستئناف وتغيير اليوم:
     * موعدٌ قديم كان يُخصم فوراً ويُبقي الجدول كله متأخراً.
     */
    public function upcomingBillingDate(?int $day = null): string
    {
        $day ??= $this->billing_day;
        $candidate = now()->startOfDay()->setDay(min($day, now()->daysInMonth));

        return ($candidate->isPast() ? now()->addMonthNoOverflow()->setDay($day) : $candidate)->toDateString();
    }

    /**
     * يقدّم موعد القسط بعدد من الأشهر، على يوم الفوترة. الافتراضي دورة الكفالة
     * نفسها (شهر، أو 3، أو 12)، فدفعة سنوية لا تجعل الكفالة متأخرة بعد شهر.
     */
    public function advanceDueDate(?int $months = null): void
    {
        $months ??= max(1, (int) ($this->interval_months ?? 1));
        $from = ($this->next_due_at ?? now())->copy()->startOfMonth()->addMonthsNoOverflow($months);

        $this->next_due_at = $from->setDay(min($this->billing_day, $from->daysInMonth));
    }

    /** ما يُخصم في الدورة الواحدة: القسط الشهري × أشهر الدورة */
    public function cycleAmount(): float
    {
        return round((float) $this->monthly_amount * max(1, (int) ($this->interval_months ?? 1)), 2);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'monthly_amount', 'share_percent', 'next_due_at', 'cancellation_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}

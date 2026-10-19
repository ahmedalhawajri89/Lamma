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

    /**
     * مرجع البطاقة المحفوظة عند البوابة: يُخصم به ولا يُعرض. لا يُسند من طلب
     * (ليس في fillable)، تكتبه صفحة الكفالة بعد خصمٍ ناجح بـ forceFill.
     */
    protected $hidden = ['gateway_payment_method'];

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
            'payment_issue_since' => 'date',
            'payment_failures' => 'integer',
            'next_retry_at' => 'date',
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
            ->whereDate('next_due_at', '<=', $date ?? now()->toDateString())
            // المتعثّرة تُعاد في مواعيدها لا كل يوم، ومن استنفد محاولاته ينتظر بطاقة جديدة
            ->where(fn ($q) => $q->whereNull('next_retry_at')->orWhereDate('next_retry_at', '<=', $date ?? now()->toDateString()))
            ->where('payment_failures', '<', self::maxPaymentAttempts());
    }

    /* ---------------------------------------------------------- القسط المتعثّر */

    /**
     * مفتاح القسط المستحق: من شهر الاستحقاق لا من شهر التشغيل. قسط سبتمبر
     * المتعثّر إن حُصّل في أكتوبر كان يأخذ مفتاح أكتوبر، فيُحسب قسط أكتوبر
     * نفسه «مسجّلاً من قبل» ولا يُخصم.
     */
    public function dueKey(): string
    {
        return hash('sha256', "{$this->id}-".($this->next_due_at ?? now())->format('Y-m'));
    }

    /** محاولات الخصم في التعثّر الواحد: الأولى، ثم بعد كل فجوة في retry_after_days. */
    public static function maxPaymentAttempts(): int
    {
        return count((array) config('kafala.billing.retry_after_days', [3, 7])) + 1;
    }

    public function hasPaymentIssue(): bool
    {
        return $this->payment_issue_since !== null;
    }

    /**
     * محاولة خصمٍ فشلت: يبدأ التعثّر (أو يستمر) وتُحدَّد المحاولة التالية
     * من يوم بدايته. يُرجع true إن كانت هذه أول محاولة فاشلة، فيُبلَّغ الكافل.
     */
    public function recordPaymentFailure(): bool
    {
        $first = $this->payment_issue_since === null;
        $this->payment_issue_since ??= now()->startOfDay();
        $this->payment_failures = (int) $this->payment_failures + 1;

        $gap = ((array) config('kafala.billing.retry_after_days', [3, 7]))[$this->payment_failures - 1] ?? null;
        $this->next_retry_at = $gap === null ? null : $this->payment_issue_since->copy()->addDays((int) $gap);
        $this->save();

        return $first;
    }

    /** دفعةٌ وصلت (من البطاقة أو بيد المحاسب): انتهى التعثّر. */
    public function clearPaymentIssue(): void
    {
        $this->payment_issue_since = null;
        $this->payment_failures = 0;
        $this->next_retry_at = null;
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

    /** عكس advanceDueDate: يعيد الموعد أشهراً، على يوم الفوترة نفسه. */
    public function rewindDueDate(int $months): void
    {
        if ($this->next_due_at === null) {
            return;
        }

        $from = $this->next_due_at->copy()->startOfMonth()->subMonthsNoOverflow(max(1, $months));
        $this->next_due_at = $from->setDay(min($this->billing_day, $from->daysInMonth));
    }

    /**
     * كم شهراً يغطي مبلغٌ ما من هذه الكفالة: دفعة سنوية 12، ويدوية بستة
     * أقساط 6. القاعدة واحدة للتسجيل (يقدّم بها الموعد) وللاسترداد (يعيده بها).
     */
    public function monthsCoveredBy(float $amount): int
    {
        return max(1, (int) floor(($amount + 0.005) / max(0.01, (float) $this->monthly_amount)));
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

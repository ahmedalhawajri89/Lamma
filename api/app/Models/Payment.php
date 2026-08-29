<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * الدفعة — المال الداخل.
 *
 * لا بيانات بطاقة حساسة هنا إطلاقاً: العلامة التجارية وآخر أربعة أرقام فقط.
 * و`idempotency_key` الفريد يجعل الخصم المزدوج مستحيلاً على مستوى المحرّك،
 * لا على مستوى انضباط الكود.
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_code', 'sponsorship_id', 'sponsor_id',
        'amount', 'currency', 'fx_rate', 'amount_base',
        'method', 'gateway', 'gateway_reference', 'status',
        'card_brand', 'card_last4', 'paid_at', 'failure_reason',
        'receipt_number', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_base' => 'decimal:2',
            'fx_rate' => 'decimal:6',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /** ما يدخل في التقارير المالية وحده. */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Succeeded);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('paid_at', [$from, $to]);
    }

    /** «فيزا ••42» كما تعرضها لوحة الكافل. */
    public function maskedCard(): ?string
    {
        return $this->card_last4 ? trim("{$this->card_brand} ••{$this->card_last4}") : null;
    }
}

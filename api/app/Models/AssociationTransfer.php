<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * التحويل الشهري لجمعية شريكة: ما دفعه كفلاء أطفالها في الشهر، ناقص نسبة
 * التشغيل. وتفصيله لكل طفل في `lines`: منه يُبنى سجلّ الطفل للكافل.
 *
 * @property int $id
 * @property string $reference_code
 * @property int $association_id
 * @property \Illuminate\Support\Carbon $period
 * @property TransferStatus $status
 * @property string $received_base
 * @property string $fee_base
 * @property string $amount_base
 */
class AssociationTransfer extends Model
{
    use LogsActivity;

    /** المال الخارج: كل تغيّر في حالته أو مبلغه يُسجَّل */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount_base', 'bank_reference', 'sent_at', 'confirmed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'reference_code', 'association_id', 'period', 'currency',
        'received_base', 'fee_base', 'fund_base', 'amount_base',
        'status', 'bank_reference', 'sent_at', 'sent_by', 'confirmed_at', 'confirmed_by', 'note',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'status' => TransferStatus::class,
            'received_base' => 'decimal:2',
            'fee_base' => 'decimal:2',
            'fund_base' => 'decimal:2',
            'amount_base' => 'decimal:2',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Association, $this> */
    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    /** @return HasMany<AssociationTransferLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(AssociationTransferLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}

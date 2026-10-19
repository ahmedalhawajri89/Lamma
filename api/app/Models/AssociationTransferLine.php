<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** حصة طفلٍ واحد في تحويل الجمعية الشهري. */
class AssociationTransferLine extends Model
{
    protected $fillable = [
        'association_transfer_id', 'orphan_id', 'payments_count',
        'received_base', 'fee_base', 'amount_base',
    ];

    protected function casts(): array
    {
        return [
            'payments_count' => 'integer',
            'received_base' => 'decimal:2',
            'fee_base' => 'decimal:2',
            'amount_base' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<AssociationTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(AssociationTransfer::class, 'association_transfer_id');
    }

    /** @return BelongsTo<Orphan, $this> */
    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }
}

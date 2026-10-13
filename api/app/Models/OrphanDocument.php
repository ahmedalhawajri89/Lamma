<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * مستند تحقق.
 * الملفات تُخزَّن خارج public/ وتُقدَّم عبر مسار محمي بسياسة — لا برابط مباشر.
 *
 * @property int $id
 * @property int $orphan_id
 * @property DocumentType $type
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size_bytes
 * @property Carbon|null $verified_at
 * @property-read Orphan|null $orphan
 */
class OrphanDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'orphan_id', 'type', 'path', 'original_name',
        'mime', 'size_bytes', 'uploaded_by',

        // يُسندها DocumentController عند التحقق
        'verified_at', 'verified_by',

        // يُسندها DocumentController::reject
        'rejected_at', 'rejected_by', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'size_bytes' => 'integer',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Orphan, $this> */
    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }
}

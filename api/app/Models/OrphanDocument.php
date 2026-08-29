<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مستند تحقق.
 * الملفات تُخزَّن خارج public/ وتُقدَّم عبر مسار محمي بسياسة — لا برابط مباشر.
 */
class OrphanDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'orphan_id', 'type', 'path', 'original_name',
        'mime', 'size_bytes', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'size_bytes' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function orphan(): BelongsTo
    {
        return $this->belongsTo(Orphan::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }
}

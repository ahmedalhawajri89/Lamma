<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رسالة بين الكافل والطفل.
 *
 * `pending_review` هي القيمة الافتراضية في قاعدة البيانات نفسها،
 * فلا يمكن لمسارٍ في الكود أن يتجاوز المراجعة سهواً.
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = ['sponsorship_id', 'sender_type', 'body', 'audio_path'];

    protected function casts(): array
    {
        return ['status' => MessageStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** طابور المراجعة في لوحة الإدارة. */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::PendingReview)->oldest();
    }
}

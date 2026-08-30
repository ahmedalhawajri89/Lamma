<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * رسالة بين الكافل والطفل.
 *
 * `pending_review` هي القيمة الافتراضية في قاعدة البيانات نفسها،
 * فلا يمكن لمسارٍ في الكود أن يتجاوز المراجعة سهواً.
 *
 * @property int $id
 * @property int $sponsorship_id
 * @property string $sender_type
 * @property string|null $body
 * @property string|null $audio_path
 * @property MessageStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $reviewed_at
 */
class Message extends Model
{
    use HasFactory;

    /**
     * لاحظ ما ليس هنا: `status`.
     *
     * غيابها مقصود ومهم — لا يستطيع أي طلب أن يمرّر حالة الرسالة، فتبقى
     * القيمة الافتراضية في قاعدة البيانات (`pending_review`) هي الوحيدة
     * الممكنة عند الإنشاء. المراجعة تغيّرها عبر update صريح لا عبر fill.
     */
    protected $fillable = ['sponsorship_id', 'sender_type', 'body', 'audio_path'];

    protected $guarded = ['status'];

    /**
     * القيمة الافتراضية هنا تطابق القيمة الافتراضية في المخطّط.
     *
     * بلا ذلك يخرج الكائن من `create()` وحالته `null` في الذاكرة — بينما
     * الصف في قاعدة البيانات صحيح — فتنكسر أي قراءة للحالة قبل `refresh()`.
     */
    protected $attributes = ['status' => 'pending_review'];

    protected function casts(): array
    {
        return ['status' => MessageStatus::class, 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Sponsorship, $this> */
    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class);
    }

    /** @return BelongsTo<User, $this> */
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

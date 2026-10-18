<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property string|null $body
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property int $read_minutes
 * @property int $views_count
 * @property bool $is_featured
 */
class Post extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'slug', 'title', 'excerpt', 'body', 'category_id', 'cover_path',
        'author_id', 'read_minutes', 'status', 'published_at', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'read_minutes' => 'integer',
            'views_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<PostCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** المنشور فعلاً: الحالة منشور، والموعد قد حلّ. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', now())
            ->latest('published_at');
    }

    /**
     * الحالة كما يراها المحرّر.
     *
     * في القاعدة حالتان فقط: مسوّدة ومنشور. و«مجدول» منشورٌ بموعدٍ لم يحلّ،
     * يظهر على الموقع وحده يوم يحلّ (scopePublished). كانت «مجدول» حالةً
     * تُحفظ، ولا شيء يحوّلها إلى منشور، فيبقى المقال المجدول مخفياً إلى الأبد.
     */
    public function displayStatus(): PostStatus
    {
        return $this->status === PostStatus::Published && $this->published_at?->isFuture()
            ? PostStatus::Scheduled
            : $this->status;
    }

    /** تصفية القائمة بالحالة كما تُعرض (المجدول منشورٌ بموعدٍ قادم) */
    public function scopeDisplayStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            PostStatus::Scheduled->value => $query->where('status', PostStatus::Published)->where('published_at', '>', now()),
            PostStatus::Published->value => $query->where('status', PostStatus::Published)->where('published_at', '<=', now()),
            default => $query->where('status', $status),
        };
    }
}

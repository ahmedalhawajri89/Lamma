<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * وثيقة شفافية منشورة على الموقع العام.
 *
 * @property int $id
 * @property string $kind
 * @property int|null $year
 * @property string $title
 * @property string $file_path
 * @property int $size_bytes
 * @property Carbon $published_at
 */
class TransparencyDocument extends Model
{
    /** الأنواع بأسمائها: القائمة البيضاء للرفع، والعنوان الافتراضي، وهل لها سنة */
    public const KINDS = [
        'audited_budget' => ['label' => 'الميزانية المدقّقة', 'yearly' => true],
        'annual_report' => ['label' => 'التقرير السنوي', 'yearly' => true],
        'zakat_report' => ['label' => 'تقرير الزكاة المستقل', 'yearly' => true],
        'licenses' => ['label' => 'التراخيص والاعتمادات', 'yearly' => false],
    ];

    protected $fillable = ['kind', 'year', 'title', 'file_path', 'size_bytes', 'uploaded_by', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'year' => 'integer', 'size_bytes' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return asset('storage/'.$this->file_path);
    }

    /** @return array<string, mixed> */
    public function toPublic(): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'year' => $this->year,
            'url' => $this->url(),
            'size_bytes' => $this->size_bytes,
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }
}

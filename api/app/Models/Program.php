<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * برنامج الكفالة — المصدر الوحيد للسعر.
 *
 * الكفالة تنسخ `monthly_price` لحظة الإنشاء، فرفع السعر لاحقاً
 * لا يمسّ التزام كافل قائم (snapshot pricing).
 *
 * @property int $id
 * @property string $slug
 * @property string $name_ar
 * @property string|null $name_en
 * @property string|null $icon
 * @property string|null $accent
 * @property string $monthly_price
 * @property string $currency
 * @property bool $is_featured
 * @property bool $is_active
 * @property int $sort_order
 */
class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name_ar', 'name_en', 'icon', 'accent', 'monthly_price',
        'currency', 'summary_ar', 'description_ar', 'is_featured', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<ProgramFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(ProgramFeature::class)->orderBy('sort_order');
    }

    /** @return HasMany<Orphan, $this> */
    public function orphans(): HasMany
    {
        return $this->hasMany(Orphan::class);
    }

    /** @return HasMany<Sponsorship, $this> */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * بند صرف — جدول لا ENUM، لأن المحاسب يضيف بنداً بلا مبرمج.
 *
 * @property int $id
 * @property string $slug
 * @property string $name_ar
 * @property string|null $name_en
 * @property string|null $color
 * @property bool $is_overhead
 * @property int $sort_order
 * @property bool $is_active
 */
class DisbursementCategory extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name_ar', 'color', 'is_overhead', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_overhead' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return HasMany<Disbursement, $this> */
    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class, 'category_id');
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

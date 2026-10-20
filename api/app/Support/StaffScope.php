<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Family;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * ما يراه الموظف من بيانات الأسر وما يتفرّع عنها (الأطفال، والزيارات،
 * والتقارير، والمستندات، والكفالات): مصدرٌ واحد بدل شرطٍ مكرّر في عشرين
 * موضعاً.
 *
 * ثلاثة نطاقات:
 *  · الكل: المدير، ومن لا قيد عليه من موظفي المنصة (المحاسب).
 *  · بلد: المشرف الميداني للمنصة، ببلده المُسنَد (User::assignedCountryId،
 *    وهي مغلقة عند الفشل: مشرفٌ بلا بلد يأخذ 0 فلا يرى شيئاً).
 *  · جمعية: موظف الجمعية الشريكة، لا يرى إلا أسر جمعيته (وبلدها).
 *
 * الحماية في الاستعلام نفسه، فلا يتسرّب عددٌ ولا صفٌّ من خارج النطاق.
 */
final class StaffScope
{
    private function __construct(
        public readonly ?int $countryId,
        public readonly ?int $associationId,
    ) {}

    public static function for(User $user): self
    {
        if ($user->isAdmin()) {
            return new self(null, null);
        }

        $association = $user->staffProfile?->association;
        if ($association !== null) {
            return new self((int) $association->country_id, (int) $association->id);
        }

        return new self($user->assignedCountryId(), null);
    }

    public function isUnrestricted(): bool
    {
        return $this->countryId === null && $this->associationId === null;
    }

    /**
     * يقيّد استعلاماً على جدول الأسر نفسه.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function families(Builder $query): Builder
    {
        return $query
            ->when($this->countryId !== null, fn ($q) => $q->where($q->qualifyColumn('country_id'), $this->countryId))
            ->when($this->associationId !== null, fn ($q) => $q->where($q->qualifyColumn('association_id'), $this->associationId));
    }

    /**
     * يقيّد استعلاماً يصل إلى الأسرة عبر علاقة: 'family' للطفل،
     * 'orphan.family' للزيارة والتقرير والمستند والكفالة.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function through(Builder $query, string $path): Builder
    {
        return $this->isUnrestricted()
            ? $query
            : $query->whereHas($path, fn (Builder $f) => $this->families($f));
    }

    /** هل تقع أسرةٌ قائمة في النطاق؟ بلدها، وجمعيتها لموظف الجمعية. */
    public function allowsFamily(?Family $family): bool
    {
        if ($family === null || ! $this->allowsCountry((int) $family->country_id)) {
            return false;
        }

        return $this->associationId === null || $family->association_id === $this->associationId;
    }

    /**
     * هل يُنشئ الموظف أسرةً في هذا البلد أو ينقلها إليه؟ وموظف الجمعية تُسند
     * أسرته الجديدة إلى جمعيته تلقائياً (Family::booted)، فيكفي البلد هنا.
     */
    public function allowsCountry(int $countryId): bool
    {
        return $this->countryId === null || $countryId === $this->countryId;
    }
}

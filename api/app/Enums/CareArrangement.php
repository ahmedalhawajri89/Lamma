<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * مع من يعيش الطفل. وعد «لمّة» الأول: الطفل في أسرته. فدار الرعاية حالةٌ
 * تُسجَّل ولا تُنشر للكفالة (OrphanPolicy::publish)، والبقية رعايةٌ أسرية.
 */
enum CareArrangement: string
{
    use HasLabel;

    case WithMother = 'with_mother';
    case WithFather = 'with_father';
    case WithGrandparents = 'with_grandparents';
    case WithRelatives = 'with_relatives';
    case WithOlderSibling = 'with_older_sibling';
    case FosterFamily = 'foster_family';
    case ResidentialCare = 'residential_care';

    public function label(): string
    {
        return match ($this) {
            self::WithMother => __('مع الأم'),
            self::WithFather => __('مع الأب'),
            self::WithGrandparents => __('مع الأجداد'),
            self::WithRelatives => __('مع الأقارب'),
            self::WithOlderSibling => __('مع أخٍ أكبر'),
            self::FosterFamily => __('أسرة حاضنة'),
            self::ResidentialCare => __('دار رعاية'),
        };
    }

    /** جملةٌ للموقع العام بجنس الطفل: «تعيش مع أمها» و«يعيش مع أمه». */
    public function phrase(bool $her): string
    {
        return match ($this) {
            self::WithMother => $her ? __('تعيش مع أمها') : __('يعيش مع أمه'),
            self::WithFather => $her ? __('تعيش مع أبيها') : __('يعيش مع أبيه'),
            self::WithGrandparents => $her ? __('تعيش مع جدّيها') : __('يعيش مع جدّيه'),
            self::WithRelatives => $her ? __('تعيش مع أقاربها') : __('يعيش مع أقاربه'),
            self::WithOlderSibling => $her ? __('تعيش مع أخيها الأكبر') : __('يعيش مع أخيه الأكبر'),
            self::FosterFamily => $her ? __('تعيش مع أسرة حاضنة') : __('يعيش مع أسرة حاضنة'),
            self::ResidentialCare => $her ? __('تعيش في دار رعاية') : __('يعيش في دار رعاية'),
        };
    }

    /** رعايةٌ في أسرة، لا في مؤسسة: شرط النشر للكفالة. */
    public function isFamilyBased(): bool
    {
        return $this !== self::ResidentialCare;
    }

    /**
     * أول تقدير من صلة الوصي، للأسر المسجّلة قبل هذا الحقل. تقديرٌ لا إقرار:
     * الإقرار (care_attested_at) يبقى فارغاً حتى تؤكّده زيارة ميدانية.
     */
    public static function fromGuardianRelation(GuardianRelation|string|null $relation): ?self
    {
        $relation = is_string($relation) ? GuardianRelation::tryFrom($relation) : $relation;

        return match ($relation) {
            GuardianRelation::Mother => self::WithMother,
            GuardianRelation::Father => self::WithFather,
            GuardianRelation::Grandmother, GuardianRelation::Grandfather => self::WithGrandparents,
            GuardianRelation::Uncle, GuardianRelation::Aunt, GuardianRelation::Other => self::WithRelatives,
            GuardianRelation::Sibling => self::WithOlderSibling,
            default => null,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * مسار الجمعية الشريكة: تتقدّم، فتُراجَع، فتُوثَّق. والموقوفة يختفي أطفالها
 * من الموقع العام وتبقى كفالاتهم القائمة كما هي.
 */
enum AssociationStatus: string
{
    use HasLabel;

    case Applied = 'applied';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Applied => __('متقدّمة'),
            self::UnderReview => __('قيد المراجعة'),
            self::Verified => __('موثّقة'),
            self::Suspended => __('موقوفة'),
        };
    }
}

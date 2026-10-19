<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssociationStatus;
use App\Models\Association;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * جمعية شريكة. موثّقةٌ افتراضياً: أغلب الاختبارات تحتاج طفلاً قابلاً للنشر،
 * وما يختبر غير ذلك يطلب `pending()` أو `suspended()` صراحةً.
 *
 * @extends Factory<Association>
 */
class AssociationFactory extends Factory
{
    protected $model = Association::class;

    public function definition(): array
    {
        return [
            'slug' => 'assoc-'.fake()->unique()->numerify('####'),
            'name_ar' => 'جمعية '.fake()->unique()->numerify('####'),
            'name_en' => 'Association '.fake()->unique()->numerify('####'),
            // الدول من ReferenceSeeder — الاختبارات تبذرها قبل أن تنشئ شيئاً
            'country_id' => fn () => Country::query()->value('id'),
            'registration_number' => fake()->numerify('REG-#####'),
            'status' => AssociationStatus::Verified,
            'verified_at' => now()->subMonths(2),
            'safeguarding_signed_at' => now()->subMonths(3),
            'family_care_attested_at' => now()->subMonths(3),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => AssociationStatus::UnderReview, 'verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => AssociationStatus::Suspended, 'suspension_reason' => 'اختبار']);
    }

    public function inCountry(int $countryId): static
    {
        return $this->state(fn () => ['country_id' => $countryId]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CareArrangement;
use App\Enums\HousingStatus;
use App\Models\Association;
use App\Models\Country;
use App\Models\Family;
use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Family>
 */
class FamilyFactory extends Factory
{
    protected $model = Family::class;

    public function definition(): array
    {
        return [
            'family_code' => 'FAM-'.fake()->unique()->numerify('####'),
            'guardian_id' => Guardian::factory(),

            // الدول من ReferenceSeeder — الاختبارات تبذرها قبل أن تنشئ شيئاً
            'country_id' => fn () => Country::query()->value('id'),

            'city_id' => null,
            'address' => 'حي النصر',
            'members_count' => 4,
            'monthly_income' => 150,
            'housing_status' => HousingStatus::Rented,

            // أسرةٌ قابلة لنشر أطفالها: جمعية موثّقة في بلدها، ورعاية أسرية مُقَرّة
            'association_id' => fn (array $attrs) => Association::verified()->where('country_id', $attrs['country_id'])->value('id')
                ?? Association::factory()->inCountry((int) $attrs['country_id'])->create()->id,
            'care_arrangement' => CareArrangement::WithMother,
            'care_attested_at' => now()->subMonth(),
            'care_review_due_at' => now()->addMonths(11)->toDateString(),
        ];
    }

    /** بلا جمعية ولا إقرار رعاية: ما سُجّل قبل المرحلة 2. */
    public function unattested(): static
    {
        return $this->state(fn () => ['association_id' => null, 'care_attested_at' => null, 'care_review_due_at' => null]);
    }

    public function inCountry(int $countryId): static
    {
        return $this->state(fn () => ['country_id' => $countryId]);
    }
}

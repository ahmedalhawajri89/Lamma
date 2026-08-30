<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HousingStatus;
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
        ];
    }

    public function inCountry(int $countryId): static
    {
        return $this->state(fn () => ['country_id' => $countryId]);
    }
}

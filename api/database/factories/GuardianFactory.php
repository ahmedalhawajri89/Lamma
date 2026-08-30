<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GuardianRelation;
use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name('female'),
            'relation' => GuardianRelation::Mother,
            'phone' => '+9705'.fake()->numerify('########'),
            'occupation' => 'ربة منزل',
            'monthly_income' => fake()->numberBetween(0, 200),
        ];
    }
}

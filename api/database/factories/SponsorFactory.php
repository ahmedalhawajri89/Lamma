<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsor>
 */
class SponsorFactory extends Factory
{
    protected $model = Sponsor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->afterCreating(
                fn (User $u) => $u->assignRole(UserRole::Sponsor->value),
            ),
            'sponsor_code' => 'SPN-'.fake()->unique()->numerify('#####'),
            'preferred_currency' => 'USD',
            'prefers_zakat' => false,
            'is_anonymous' => false,
            'sponsoring_since' => now()->subMonths(6)->toDateString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SponsorshipStatus;
use App\Models\Orphan;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsorship>
 */
class SponsorshipFactory extends Factory
{
    protected $model = Sponsorship::class;

    public function definition(): array
    {
        $orphan = Orphan::factory();

        return [
            'reference_code' => 'KFS-2026-'.fake()->unique()->numerify('#####'),
            'sponsor_id' => Sponsor::factory(),
            'orphan_id' => $orphan,
            'program_id' => fn (array $attrs) => Orphan::find($attrs['orphan_id'])?->program_id,
            'monthly_amount' => 65,
            'currency' => 'USD',
            'share_percent' => 100,
            'is_zakat' => false,
            'status' => SponsorshipStatus::Active,
            'billing_day' => 1,
            'started_at' => now()->subMonths(3)->toDateString(),
            'next_due_at' => now()->addMonth()->toDateString(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => SponsorshipStatus::Cancelled,
            'ended_at' => now(),
            'next_due_at' => null,
        ]);
    }
}

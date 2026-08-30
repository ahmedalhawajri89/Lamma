<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    private static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),

            // التجزئة مرة واحدة لكل التشغيلة: bcrypt متعمَّد البطء،
            // وحسابه لكل مستخدم في الاختبارات يضاعف زمنها بلا فائدة
            'password' => self::$password ??= bcrypt('password'),

            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(UserRole::Admin->value));
    }

    public function accountant(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(UserRole::Accountant->value));
    }

    /** مشرف ميداني مقيَّد ببلد — ما تُختبَر عليه OrphanPolicy. */
    public function supervisor(?int $countryId = null): static
    {
        return $this->afterCreating(function (User $user) use ($countryId): void {
            $user->assignRole(UserRole::FieldSupervisor->value);

            StaffProfile::create([
                'user_id' => $user->id,
                'employee_code' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
                'title' => 'مشرف ميداني',
                'assigned_country_id' => $countryId,
                'hired_at' => now()->subYear(),
            ]);
        });
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\OrphanStatus;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Orphan>
 */
class OrphanFactory extends Factory
{
    protected $model = Orphan::class;

    public function definition(): array
    {
        return [
            'reference_code' => 'KFL-'.fake()->unique()->numberBetween(5000, 99999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => Gender::Male,
            'date_of_birth' => now()->subYears(9)->toDateString(),
            'family_id' => Family::factory(),
            'program_id' => fn () => Program::query()->value('id'),
            'status' => OrphanStatus::Active,
            'is_urgent' => false,
            'monthly_need' => 65,
            'verified_at' => now(),
            'published_at' => now(),
        ];
    }

    /** ملف كامل الشروط للنشر: موثّق ومنشور. */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => OrphanStatus::Active,
            'verified_at' => now(),
            'published_at' => now(),
        ]);
    }

    /** ملف جديد لم يُوثَّق بعد — لا يظهر على الموقع العام. */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => OrphanStatus::PendingVerification,
            'verified_at' => null,
            'published_at' => null,
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn () => ['is_urgent' => true]);
    }

    /** يرفق المستندات الثلاثة التي يشترطها التوثيق، مُتحقَّقاً منها. */
    public function withVerifiedDocuments(): static
    {
        return $this->afterCreating(function (Orphan $orphan): void {
            foreach ([
                DocumentType::BirthCertificate,
                DocumentType::DeathCertificate,
                DocumentType::GuardianshipProof,
            ] as $type) {
                $orphan->documents()->create([
                    'type' => $type,
                    'path' => "documents/{$orphan->id}/{$type->value}.pdf",
                    'original_name' => $type->value.'.pdf',
                    'mime' => 'application/pdf',
                    'size_bytes' => 1024,
                    'verified_at' => now(),
                ]);
            }
        });
    }
}

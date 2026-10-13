<?php

declare(strict_types=1);

use App\Enums\HousingStatus;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\User;

/**
 * شاشة الأسر: تبويبات السكن، ودخل الفرد، والبحث باسم اليتيم، وسجلّ الأسرة.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('يعدّ كل سكن تحت الفلاتر الأخرى، والأسر بلا أيتام', function (): void {
    Family::factory()->count(2)->create(['housing_status' => HousingStatus::Displaced]);
    $owned = Family::factory()->create(['housing_status' => HousingStatus::Owned]);
    Orphan::factory()->create(['family_id' => $owned->id]);

    $meta = $this->getJson('/api/v1/admin/families?housing_status=owned')->assertOk()->json('meta');

    expect($meta['total'])->toBe(1)
        ->and($meta['housing_counts']['displaced'])->toBe(2)
        ->and($meta['all_count'])->toBe(3)
        ->and($meta['without_orphans_count'])->toBe(2);
});

it('يرتّب بدخل الفرد، والدخل المجهول آخراً', function (): void {
    $poor = Family::factory()->create(['monthly_income' => 60, 'members_count' => 6]);   // 10
    $rich = Family::factory()->create(['monthly_income' => 100, 'members_count' => 2]);  // 50
    $unknown = Family::factory()->create(['monthly_income' => null, 'members_count' => 3]);

    $ids = collect($this->getJson('/api/v1/admin/families?sort=per_capita&dir=asc')->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$poor->id, $rich->id, $unknown->id]);
    expect($this->getJson("/api/v1/admin/families?sort=per_capita&dir=asc")->json('data.0.per_capita_income'))->toEqual(10);
});

it('يجد الأسرة باسم أحد أيتامها', function (): void {
    $family = Family::factory()->create();
    Orphan::factory()->create(['family_id' => $family->id, 'first_name' => 'قمرية']);
    Family::factory()->create();

    $data = $this->getJson('/api/v1/admin/families?q=قمري')->json('data');
    expect($data)->toHaveCount(1)->and($data[0]['id'])->toBe($family->id);
});

it('تعديل الأسرة ووصيّها يُسجَّل، وهاتف الوصي بلا قيمته', function (): void {
    $family = Family::factory()->create(['members_count' => 4]);

    $this->patchJson("/api/v1/admin/families/{$family->id}", [
        'members_count' => 5, 'guardian' => ['phone' => '+970599000111', 'occupation' => 'خيّاطة'],
    ])->assertOk();

    $entries = $this->getJson("/api/v1/admin/activity?subject_type=Family&subject_id={$family->id}")->assertOk()->json('data');
    $changes = collect($entries)->where('event', 'updated')->flatMap(fn ($e) => $e['changes'])->keyBy('field');

    expect($changes['members_count']['after'])->toBe('5')
        ->and($changes['guardian.occupation']['after'])->toBe('خيّاطة')
        ->and($changes['guardian.phone']['after'])->toBe('•••');
});

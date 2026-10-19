<?php

declare(strict_types=1);

use App\Enums\CareArrangement;
use App\Enums\OrphanStatus;
use App\Models\Association;
use App\Models\Family;
use App\Models\Orphan;

/**
 * المرحلة 2 على الموقع العام: مع من يعيش الطفل، والجمعية التي تتابعه،
 * وصفحة لكل جمعية موثّقة.
 */
function publishedIn(Association $association, array $family = [], array $orphan = []): Orphan
{
    return Orphan::factory()->create([
        'status' => OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now(), ...$orphan,
        'family_id' => Family::factory()->state(['country_id' => $association->country_id, 'association_id' => $association->id, ...$family]),
    ]);
}

it('تقول بطاقة الطفل مع من يعيش، بجنسه، وتسمّي جمعيته الموثّقة', function () {
    $assoc = Association::factory()->create(['name_ar' => 'جمعية النور']);
    $girl = publishedIn($assoc, ['care_arrangement' => CareArrangement::WithGrandparents], ['gender' => 'female']);

    $card = collect($this->getJson('/api/v1/public/orphans')->assertOk()->json('data'))->firstWhere('id', $girl->reference_code);

    expect($card['care'])->toBe('تعيش مع جدّيها')
        ->and($card['association'])->toMatchArray(['name' => 'جمعية النور', 'slug' => $assoc->slug]);

    $en = collect($this->getJson('/api/v1/public/orphans', ['X-Locale' => 'en'])->json('data'))->firstWhere('id', $girl->reference_code);
    expect($en['care'])->toBe('Lives with her grandparents');
});

it('صفحة الطفل تقول مع من يعيش وتربط بصفحة جمعيته', function () {
    $assoc = Association::factory()->create(['name_ar' => 'جمعية النور']);
    $boy = publishedIn($assoc, ['care_arrangement' => CareArrangement::WithMother], ['gender' => 'male']);

    $this->get("/orphan?id={$boy->reference_code}")->assertOk()
        ->assertSee('يعيش مع أمه')
        ->assertSee('href="/partners/'.$assoc->slug.'"', false)
        ->assertSee('جمعية النور');
});

it('للجمعية الموثّقة صفحة بأرقامها، والمراجَعة والموقوفة لا صفحة لها', function () {
    $assoc = Association::factory()->create(['name_ar' => 'جمعية النور', 'registration_number' => 'REG-777']);
    publishedIn($assoc);
    publishedIn($assoc);

    $html = $this->get('/partners/'.$assoc->slug)->assertOk()->getContent();
    expect($html)->toContain('جمعية النور')->toContain('REG-777')->toContain('data-association="'.$assoc->slug.'"');

    $this->get('/en/partners/'.$assoc->slug)->assertOk()->assertSee('Verified partner association');

    $this->get('/partners/'.Association::factory()->pending()->create()->slug)->assertNotFound();
    $this->get('/partners/'.Association::factory()->suspended()->create()->slug)->assertNotFound();
});

it('يفلتر الـ API أطفال جمعيةٍ بعينها، الموثّقة وحدها', function () {
    $a = Association::factory()->create();
    $b = Association::factory()->create();
    $mine = publishedIn($a);
    publishedIn($b);

    $ids = collect($this->getJson('/api/v1/public/orphans?association='.$a->slug)->assertOk()->json('data'))->pluck('id');

    expect($ids->all())->toBe([$mine->reference_code]);
});

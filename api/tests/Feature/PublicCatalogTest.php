<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Models\Orphan;

/**
 * فهرس الأيتام العام: يُصفّح ويُفلتر على الخادم، وبجانب كل خيار عدده.
 * كانت الصفحة تطلب أول مئة طفل، فمن بعد المئة لا يظهر لأحد.
 */
function publicChild(array $attrs = []): Orphan
{
    return Orphan::factory()->create([
        'status' => OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now(), ...$attrs,
    ]);
}

it('يصفّح كل الأطفال العامّين، ويفلتر بالعمر والجنس، ويعدّ كل خيار تحت الفلاتر الأخرى', function () {
    publicChild(['date_of_birth' => now()->subYears(4), 'gender' => 'female', 'is_urgent' => true]);
    publicChild(['date_of_birth' => now()->subYears(8), 'gender' => 'female']);
    publicChild(['date_of_birth' => now()->subYears(8), 'gender' => 'male']);
    publicChild(['date_of_birth' => now()->subYears(13), 'gender' => 'male']);
    Orphan::factory()->create(['status' => OrphanStatus::Draft]); // ليس عاماً

    $page = $this->getJson('/api/v1/public/orphans?per_page=3&page=2&facets=1')->assertOk();
    expect($page->json('meta.total'))->toBe(4)
        ->and($page->json('meta.last_page'))->toBe(2)
        ->and($page->json('data'))->toHaveCount(1)
        ->and($page->json('meta.facets.age'))->toBe(['0-5' => 1, '6-10' => 2, '11-18' => 1])
        ->and($page->json('meta.facets.all'))->toBe(4);

    // عدد الجنس تحت فلتر العمر، لا تحت الجنس نفسه
    $res = $this->getJson('/api/v1/public/orphans?age=6-10&gender=female&facets=1')->assertOk();
    expect($res->json('meta.total'))->toBe(1)
        ->and($res->json('meta.facets.gender'))->toEqual(['female' => 1, 'male' => 1])
        ->and($res->json('meta.facets.age.6-10'))->toBe(1);

    $this->getJson('/api/v1/public/orphans?urgent=1')->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/public/orphans?age=99')->assertUnprocessable();
    $this->getJson('/api/v1/public/orphans?sort=monthly_desc')->assertOk();
});

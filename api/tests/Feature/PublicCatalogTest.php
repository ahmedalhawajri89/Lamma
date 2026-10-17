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

it('لا تخرج صورة الطفل الحقيقية إلى الموقع العام: رسمٌ ثابت بحسب جنسه بدلها', function () {
    $girl = publicChild(['gender' => 'female', 'photo_path' => 'orphans/real-girl.jpg']);
    $boy = publicChild(['gender' => 'male', 'photo_path' => 'orphans/real-boy.jpg']);

    $list = collect($this->getJson('/api/v1/public/orphans?per_page=10')->assertOk()->json('data'))->keyBy('id');
    $one = $this->getJson('/api/v1/public/orphans/'.$girl->reference_code)->assertOk()->json('data.photo');

    expect($list[$girl->reference_code]['photo'])->toMatch('#/assets/img/kids/girl-\d{2}\.svg$#')
        ->and($list[$boy->reference_code]['photo'])->toMatch('#/assets/img/kids/boy-\d{2}\.svg$#')
        // ثابت بين الطلبات، وصفحة الطفل الواحد تعطي الرسم نفسه
        ->and($one)->toBe($list[$girl->reference_code]['photo'])
        ->and(json_encode($list))->not->toContain('real-girl')->not->toContain('storage/orphans');
});

it('لا يعرض في الفهرس من اكتملت كفالته، وتبقى صفحته تقول ذلك', function () {
    $open = publicChild(['monthly_need' => 50]);
    $full = publicChild(['monthly_need' => 50]);

    // كافلان يتشاركان الأول ولم يكملاه، وكفالةٌ ملغاة لا تُعدّ
    App\Models\Sponsorship::factory()->for($open)->create(['monthly_amount' => 20]);
    App\Models\Sponsorship::factory()->for($open)->create(['monthly_amount' => 10]);
    App\Models\Sponsorship::factory()->for($open)->cancelled()->create(['monthly_amount' => 15]);
    App\Models\Sponsorship::factory()->for($full)->create(['monthly_amount' => 50]);

    expect($full->fresh()->status)->toBe(OrphanStatus::FullyFunded);

    $list = $this->getJson('/api/v1/public/orphans?facets=1')->assertOk();
    expect($list->json('meta.total'))->toBe(1)
        ->and($list->json('meta.facets.all'))->toBe(1)
        ->and($list->json('data.0.id'))->toBe($open->reference_code)
        ->and($list->json('data.0.funded'))->toBe(60)
        ->and($list->json('data.0.sponsors'))->toBe(2);

    // الرابط القديم يفتح الصفحة: مكتملة، ولا تقبل كفالة
    $this->getJson("/api/v1/public/orphans/{$full->reference_code}")
        ->assertOk()
        ->assertJsonPath('data.funded', 100)
        ->assertJsonPath('data.sponsors', 1)
        ->assertJsonPath('data.accepting', false);
});

<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\Sponsor;
use App\Models\User;

it('يمنع غير الداخل من لوحة الإدارة', function () {
    $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
});

it('يمنع الكافل من لوحة الإدارة', function () {
    $sponsor = Sponsor::factory()->create();

    $this->actingAs($sponsor->user)
        ->getJson('/api/v1/admin/dashboard')
        ->assertForbidden();
});

it('يمنع المشرف الميداني من المدفوعات', function () {
    // الصلاحية فعلٌ لا شاشة: المشرف لا يملك payments.view أصلاً
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->getJson('/api/v1/admin/payments')
        ->assertForbidden();
});

it('يقصر المشرف الميداني على أيتام بلده', function () {
    $palestine = Country::where('iso2', 'PS')->first();
    $yemen = Country::where('iso2', 'YE')->first();

    Orphan::factory()->create([
        'family_id' => Family::factory()->inCountry($palestine->id),
        'first_name' => 'من فلسطين',
    ]);
    Orphan::factory()->create([
        'family_id' => Family::factory()->inCountry($yemen->id),
        'first_name' => 'من اليمن',
    ]);

    $supervisor = User::factory()->supervisor($palestine->id)->create();

    $response = $this->actingAs($supervisor)->getJson('/api/v1/admin/orphans');

    // التقييد على مستوى الاستعلام لا بعد جلبه: العدد الكلي نفسه لا يتسرّب
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.first_name'))->toBe('من فلسطين');
});

it('يمنع الحساب الموقوف من الدخول', function () {
    $user = User::factory()->suspended()->create(['email' => 'stopped@kafala.org']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(422);

    expect(auth()->check())->toBeFalse();
});

it('يعطي رسالة واحدة للبريد الخاطئ ولكلمة المرور الخاطئة', function () {
    // وإلا صار النموذج أداةً لكشف البريد المسجَّل من غيره
    User::factory()->create(['email' => 'real@kafala.org']);

    $wrongPassword = $this->postJson('/api/v1/auth/login', [
        'email' => 'real@kafala.org', 'password' => 'nope-nope-nope',
    ]);
    $wrongEmail = $this->postJson('/api/v1/auth/login', [
        'email' => 'ghost@kafala.org', 'password' => 'nope-nope-nope',
    ]);

    expect($wrongPassword->json('errors.email'))->toBe($wrongEmail->json('errors.email'));
});

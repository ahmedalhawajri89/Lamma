<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * شاشة فريق العمل: الموظفون وحدهم، بأعداد تبويباتهم، ودرجٌ يشرح ما يقدر
 * عليه كل موظف من الصلاحيات الفعلية لا من نصٍّ مكتوب في الواجهة.
 */
it('تعرض الموظفين وحدهم بأعداد الأدوار والحالات', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->accountant()->create();
    User::factory()->supervisor()->create(['status' => UserStatus::Pending]);
    User::factory()->create()->assignRole('sponsor');

    $res = $this->actingAs($admin)->getJson('/api/v1/admin/users')->assertOk();

    expect(collect($res->json('data'))->pluck('roles')->flatten()->all())->not->toContain('sponsor')
        ->and($res->json('meta.role_counts.accountant'))->toBe(1)
        ->and($res->json('meta.status_counts.pending'))->toBe(1)
        // من ينتظر التفعيل أولاً: عليه إجراء
        ->and($res->json('data.0.status'))->toBe('pending');

    // عدد الأدوار يتبع فلتر الحالة، وعدد الحالات يتبع فلتر الدور
    $this->actingAs($admin)->getJson('/api/v1/admin/users?status=pending')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.role_counts.field_supervisor', 1)
        ->assertJsonMissingPath('meta.role_counts.accountant');

    $this->actingAs($admin)->getJson('/api/v1/admin/users?status=ghost')->assertUnprocessable();
    $this->actingAs($admin)->getJson('/api/v1/admin/users?role=sponsor')->assertUnprocessable();
});

it('درج الموظف يشرح صلاحياته الفعلية ويعدّ عمله، ولا يُفتح لحساب كافل', function () {
    $admin = User::factory()->admin()->create();
    $accountant = User::factory()->accountant()->create();
    $sponsor = User::factory()->create();
    $sponsor->assignRole('sponsor');

    $res = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$accountant->id}")->assertOk();
    $abilities = collect($res->json('data.abilities'))->flatMap(fn ($g) => $g['items'])->keyBy('name');

    expect($abilities['payments.refund']['granted'])->toBeTrue()
        ->and($abilities['orphans.update']['granted'])->toBeFalse()
        ->and($res->json('data.is_self'))->toBeFalse()
        ->and($res->json('data.stats'))->toHaveKey('actions_30d');

    $supervisor = User::factory()->supervisor()->create();
    $this->actingAs($admin)->getJson("/api/v1/admin/users/{$supervisor->id}")
        ->assertOk()->assertJsonStructure(['data' => ['stats' => ['orphans_in_country', 'visits_90d', 'reports_open']]]);

    $this->actingAs($admin)->getJson("/api/v1/admin/users/{$sponsor->id}")->assertNotFound();
    $this->actingAs($accountant)->getJson("/api/v1/admin/users/{$admin->id}")->assertForbidden();
});

it('إعادة إرسال الدعوة تُسجَّل ويظهر وقتها في الدرج', function () {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->accountant()->create(['status' => UserStatus::Pending]);

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$pending->id}/invite")->assertOk();

    expect(Activity::where('subject_id', $pending->id)->where('event', 'invited')->exists())->toBeTrue();
    $this->actingAs($admin)->getJson("/api/v1/admin/users/{$pending->id}")
        ->assertJsonPath('data.status', 'pending')
        ->assertJson(fn ($json) => $json->whereType('data.invited_at', 'string')->etc());
});

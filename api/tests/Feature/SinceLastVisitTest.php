<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\ReportStatus;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * «منذ آخر دخول»: يقارن بالدخول السابق لا الحالي، ويعرض لكل موظف ما يخصّ
 * صلاحيته وحدها.
 */
it('الدخول يحفظ السابق قبل أن يكتب الحالي', function () {
    $admin = User::factory()->admin()->create(['last_login_at' => Carbon::parse('2026-10-01 09:00')]);

    $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();

    $admin->refresh();
    expect($admin->previous_login_at->toDateTimeString())->toBe('2026-10-01 09:00:00')
        ->and($admin->last_login_at->isToday())->toBeTrue();
});

it('يعرض ما جرى بعد الدخول السابق، بحسب الصلاحية', function () {
    $admin = User::factory()->admin()->create(['previous_login_at' => now()->subDays(2)]);
    $accountant = User::factory()->accountant()->create(['previous_login_at' => now()->subDays(2)]);
    $supervisor = User::factory()->supervisor()->create(['previous_login_at' => now()->subDays(2)]);

    // دفعة فشلت بعد الدخول السابق، وأخرى قبله لا تُعدّ
    $s = Sponsorship::factory()->create();
    $failed = fn (string $key, string $reason) => Payment::create([
        'reference_code' => 'PAY-SL'.$key, 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id,
        'amount' => 30, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => 30, 'method' => 'card',
        'status' => PaymentStatus::Failed, 'failure_reason' => $reason, 'idempotency_key' => 'sl'.$key,
    ]);
    $failed('1', 'رصيد غير كافٍ');
    $old = $failed('2', 'قديمة');
    Payment::whereKey($old->id)->update(['created_at' => now()->subDays(5)]);

    Report::create([
        'orphan_id' => Orphan::factory()->create()->id, 'period_year' => 2026, 'period_quarter' => 3,
        'title' => 'تقرير', 'summary' => 'ملخص', 'supervisor_id' => $supervisor->id,
        'status' => ReportStatus::Draft, 'returned_at' => now(), 'review_note' => 'ينقصه ملخص الصحة',
    ]);

    $keys = fn (User $u) => collect($this->actingAs($u)->getJson('/api/v1/admin/since')->assertOk()->json('data.items'))->keyBy('key');

    $forAccountant = $keys($accountant);
    expect($forAccountant['payments_failed']['count'])->toBe(1)
        ->and($forAccountant['payments_failed']['samples'][0])->toContain('رصيد غير كافٍ')
        ->and($forAccountant)->not->toHaveKey('reports_returned')
        ->and($forAccountant)->not->toHaveKey('activity_sensitive');

    // المشرف: لا مال، وتقاريره المُرجعة
    $forSupervisor = $keys($supervisor);
    expect($forSupervisor)->not->toHaveKey('payments_failed')
        ->and($forSupervisor['reports_returned']['samples'][0])->toBe('ينقصه ملخص الصحة');

    expect($keys($admin))->toHaveKey('payments_failed');
});

it('أول دخول: الأسبوع الماضي', function () {
    $admin = User::factory()->admin()->create(['previous_login_at' => null]);

    $this->actingAs($admin)->getJson('/api/v1/admin/since')
        ->assertOk()
        ->assertJsonPath('data.first_visit', true);
});

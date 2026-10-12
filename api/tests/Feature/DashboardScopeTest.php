<?php

declare(strict_types=1);

use App\Models\Country;
use App\Models\Disbursement;
use App\Models\DisbursementCategory;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\User;

/**
 * النظرة العامة بحسب صاحبها: كانت تُرسل الأرقام المالية لكل موظف،
 * والمشرف الميداني لا يملك صلاحية المدفوعات.
 */
it('لا يرسل للمشرف الميداني أي رقم مالي ولا تحليلات', function () {
    $palestine = Country::where('iso2', 'PS')->first();
    $supervisor = User::factory()->supervisor($palestine->id)->create();

    $data = $this->actingAs($supervisor)->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

    expect($data['kpis'])
        ->not->toHaveKeys(['monthly_recurring', 'revenue_month', 'disbursed_month', 'failed_payments_month'])
        ->and($data['monthly'])->toBe([])
        ->and($data['spending'])->toBe([])
        ->and($data['scope']['role'])->toBe('field_supervisor')
        ->and($data['scope']['country'])->toBe($palestine->name_ar);
});

it('يقصر أرقام المشرف الميداني على أيتام بلده', function () {
    $palestine = Country::where('iso2', 'PS')->first();
    $yemen = Country::where('iso2', 'YE')->first();

    Orphan::factory()->count(2)->create(['family_id' => Family::factory()->inCountry($palestine->id)]);
    Orphan::factory()->count(3)->create(['family_id' => Family::factory()->inCountry($yemen->id)]);

    $supervisor = User::factory()->supervisor($palestine->id)->create();

    $kpis = $this->actingAs($supervisor)->getJson('/api/v1/admin/dashboard')->json('data.kpis');

    expect($kpis['orphans_total'])->toBe(2);
});

it('يرسل الأرقام المالية والتحليلات للمحاسب', function () {
    $accountant = User::factory()->accountant()->create();

    $data = $this->actingAs($accountant)->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

    expect($data['kpis'])->toHaveKeys(['monthly_recurring', 'revenue_month'])
        ->and($data['scope']['role'])->toBe('accountant');
});

it('لا يعدّ على المحاسب صرفاً سجّله بنفسه في «بانتظارك»', function () {
    $accountant = User::factory()->accountant()->create();
    $colleague = User::factory()->accountant()->create();
    $category = DisbursementCategory::query()->value('id');

    $base = [
        'category_id' => $category, 'amount' => 10, 'currency' => 'USD', 'amount_base' => 10,
        'spent_on' => now()->toDateString(), 'description' => 'صرف تجريبي',
    ];
    Disbursement::create([...$base, 'recorded_by' => $accountant->id]);
    Disbursement::create([...$base, 'recorded_by' => $colleague->id]);

    $tasks = collect($this->actingAs($accountant)->getJson('/api/v1/admin/queue')->assertOk()->json('data'));

    expect($tasks->firstWhere('key', 'disbursements_approve')['count'])->toBe(1);
});

it('يبني «بانتظارك» من الصلاحيات: المشرف لا يعتمد التقارير', function () {
    $supervisor = User::factory()->supervisor(Country::where('iso2', 'PS')->value('id'))->create();

    $keys = collect($this->actingAs($supervisor)->getJson('/api/v1/admin/queue')->json('data'))->pluck('key');

    expect($keys)->not->toContain('reports_approve')
        ->and($keys)->not->toContain('disbursements_approve')
        ->and($keys)->not->toContain('payments_failed');
});

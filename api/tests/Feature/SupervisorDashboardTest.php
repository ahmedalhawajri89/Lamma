<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Enums\ReportStatus;
use App\Models\Country;
use App\Models\Family;
use App\Models\FieldVisit;
use App\Models\Orphan;
use App\Models\Report;
use App\Models\User;

/**
 * لوحة المشرف: زيارات بلده وتقاريره وملفاته، لا بلدٍ غيره ولا مال.
 */
it('تعرض عمل بلد المشرف وحده: من يُزار، وتقارير الربع، وما رصدته الزيارات', function () {
    $ps = Country::where('iso2', 'PS')->value('id');
    $sy = Country::where('iso2', 'SY')->value('id');
    $supervisor = User::factory()->supervisor($ps)->create();

    $never = Orphan::factory()->create(['status' => OrphanStatus::Active, 'family_id' => Family::factory()->inCountry($ps)]);
    $late = Orphan::factory()->create(['status' => OrphanStatus::Active, 'family_id' => Family::factory()->inCountry($ps)]);
    Orphan::factory()->create(['status' => OrphanStatus::Active, 'family_id' => Family::factory()->inCountry($sy)]);

    FieldVisit::create([
        'orphan_id' => $late->id, 'supervisor_id' => $supervisor->id, 'visited_at' => now()->subDays(40),
        'findings' => 'زيارة سابقة', 'needs_flagged' => [], 'next_visit_due' => now()->subDays(5)->toDateString(),
    ]);
    FieldVisit::create([
        'orphan_id' => $late->id, 'supervisor_id' => $supervisor->id, 'visited_at' => now()->subDays(10),
        'findings' => 'تحتاج ملابس', 'needs_flagged' => ['ملابس شتوية'], 'next_visit_due' => now()->subDay()->toDateString(),
    ]);

    $q = (int) ceil(now()->month / 3);
    Report::create([
        'orphan_id' => $late->id, 'period_year' => now()->year, 'period_quarter' => $q, 'title' => 'تقرير', 'summary' => 'ملخص',
        'supervisor_id' => $supervisor->id, 'status' => ReportStatus::Draft, 'returned_at' => now(), 'review_note' => 'ينقصه ملخص الصحة',
    ]);

    $f = $this->actingAs($supervisor)->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.field');

    expect($f['children'])->toBe(2)
        ->and($f['visits']['never'])->toBe(1)
        ->and($f['visits']['overdue'])->toBe(1)
        // من لم يُزر قط أولاً
        ->and($f['visits']['next'][0]['id'])->toBe($never->id)
        ->and($f['reports']['expected'])->toBe(2)
        ->and($f['reports']['drafts'])->toBe(1)
        ->and($f['reports']['missing'])->toBe(1)
        ->and($f['reports']['returned'][0]['note'])->toBe('ينقصه ملخص الصحة')
        ->and($f['flagged']['by_need'][0])->toBe(['need' => 'ملابس شتوية', 'count' => 1]);

    // لا مال في لوحته
    $this->actingAs($supervisor)->getJson('/api/v1/admin/dashboard')->assertJsonPath('data.finance', null);
});

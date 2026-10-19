<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Models\Association;
use App\Models\Country;
use App\Models\Family;
use App\Models\FieldVisit;
use App\Models\Orphan;
use App\Models\Report;
use App\Models\User;

/**
 * نطاق موظف الجمعية الشريكة: أسر جمعيته وحدها، وإن شاركتها جمعيةٌ أخرى
 * البلد نفسه. ومشرف المنصة يبقى على بلده كله.
 */
beforeEach(function () {
    $this->ps = Country::where('iso2', 'PS')->value('id');
    $this->a = Association::factory()->inCountry($this->ps)->create();
    $this->b = Association::factory()->inCountry($this->ps)->create();

    $this->mine = Orphan::factory()->count(2)->create(['family_id' => Family::factory()->state(['country_id' => $this->ps, 'association_id' => $this->a->id])]);
    $this->theirs = Orphan::factory()->count(3)->create(['family_id' => Family::factory()->state(['country_id' => $this->ps, 'association_id' => $this->b->id])]);
});

it('يرى موظف الجمعية أطفال جمعيته وأسرها وحدها، لا جمعية أخرى في البلد نفسه', function () {
    $staff = User::factory()->associationSupervisor($this->a->id)->create();

    $orphans = collect($this->actingAs($staff)->getJson('/api/v1/admin/orphans?per_page=50')->assertOk()->json('data'))->pluck('id');
    $families = collect($this->actingAs($staff)->getJson('/api/v1/admin/families?per_page=50')->assertOk()->json('data'))->pluck('id');

    expect($orphans->sort()->values()->all())->toBe($this->mine->pluck('id')->sort()->values()->all())
        ->and($families->sort()->values()->all())->toBe($this->mine->pluck('family_id')->unique()->sort()->values()->all());

    // ولا يفتح طفلاً ولا أسرةً من الجمعية الأخرى برابطها المباشر
    $this->actingAs($staff)->getJson('/api/v1/admin/orphans/'.$this->theirs->first()->id)->assertForbidden();
    $this->actingAs($staff)->getJson('/api/v1/admin/families/'.$this->theirs->first()->family_id)->assertNotFound();
});

it('مشرف الجمعية بلا بلد مُسنَد يرى جمعيته، لا شيئاً (نطاقه من جمعيته)', function () {
    $staff = User::factory()->associationSupervisor($this->a->id)->create();

    $kpis = $this->actingAs($staff)->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

    expect($kpis['kpis']['orphans_total'])->toBe(2)
        ->and($kpis['scope']['association'])->toBe($this->a->name_ar)
        ->and($kpis['field'])->not->toBeNull();
});

it('يبقى مشرف المنصة على بلده كله، بجمعياته جميعاً', function () {
    $supervisor = User::factory()->supervisor($this->ps)->create();

    $kpis = $this->actingAs($supervisor)->getJson('/api/v1/admin/dashboard')->json('data.kpis');

    expect($kpis['orphans_total'])->toBe(5);
});

it('الزيارات والتقارير تتبع نطاق الجمعية أيضاً', function () {
    $staff = User::factory()->associationSupervisor($this->a->id)->create();
    $visit = fn (Orphan $o) => FieldVisit::create([
        'orphan_id' => $o->id, 'supervisor_id' => $staff->id, 'visited_at' => now()->subDays(3),
        'findings' => 'زيارة', 'needs_flagged' => [], 'next_visit_due' => now()->addMonth()->toDateString(),
    ]);
    $report = fn (Orphan $o) => Report::create([
        'orphan_id' => $o->id, 'period_year' => now()->year, 'period_quarter' => (int) ceil(now()->month / 3),
        'title' => 'تقرير', 'summary' => 'ملخص', 'supervisor_id' => $staff->id, 'status' => ReportStatus::Submitted,
    ]);

    $visit($this->mine->first());
    $visit($this->theirs->first());
    $report($this->mine->first());
    $foreign = $report($this->theirs->first());

    $visits = collect($this->actingAs($staff)->getJson('/api/v1/admin/visits')->assertOk()->json('data'))->pluck('orphan.id')->filter();
    expect($visits->all())->toBe([$this->mine->first()->id]);

    $this->actingAs($staff)->getJson('/api/v1/admin/reports/'.$foreign->id)->assertForbidden();
});

it('لا يُنشئ موظف الجمعية أسرةً في بلدٍ آخر، وأسرته الجديدة لجمعيته', function () {
    $staff = User::factory()->associationSupervisor($this->a->id)->create();
    $yemen = Country::where('iso2', 'YE')->value('id');
    $payload = fn ($country) => [
        'guardian' => ['full_name' => 'وصية اختبار', 'relation' => 'mother'],
        'country_id' => $country, 'members_count' => 3, 'housing_status' => 'rented',
    ];

    $this->actingAs($staff)->postJson('/api/v1/admin/families', $payload($yemen))->assertUnprocessable()->assertJsonValidationErrors('country_id');
    $id = $this->actingAs($staff)->postJson('/api/v1/admin/families', $payload($this->ps))->assertCreated()->json('data.id');

    expect(Family::find($id)->association_id)->toBe($this->a->id);
});

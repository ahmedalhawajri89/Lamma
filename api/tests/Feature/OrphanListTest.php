<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Enums\SponsorshipStatus;
use App\Models\FieldVisit;
use App\Models\Orphan;
use App\Models\Sponsorship;
use App\Models\User;

/**
 * رأس شاشة الأيتام وتصديرها: أعداد كل حالة، وفجوة التمويل، وملف CSV.
 */
it('يُرجع عدد كل حالة تحت الفلاتر الأخرى، وفجوة التمويل الشهرية للنشطين', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $a = Orphan::factory()->create(['status' => OrphanStatus::Active, 'monthly_need' => 100, 'is_urgent' => true]);
    Orphan::factory()->create(['status' => OrphanStatus::Active, 'monthly_need' => 50]);
    Orphan::factory()->create(['status' => OrphanStatus::Draft, 'monthly_need' => 70]);
    Sponsorship::factory()->for($a)->create(['status' => SponsorshipStatus::Active, 'monthly_amount' => 40]);
    // الملغاة لا تُغطّي شيئاً
    Sponsorship::factory()->for($a)->create(['status' => SponsorshipStatus::Cancelled, 'monthly_amount' => 30]);

    $meta = $this->getJson('/api/v1/admin/orphans?status=draft')->assertOk()->json('meta');

    expect($meta['total'])->toBe(1)
        ->and($meta['status_counts']['active'])->toBe(2)
        ->and($meta['status_counts']['draft'])->toBe(1)
        ->and($meta['all_count'])->toBe(3)
        ->and($meta['urgent_count'])->toBe(1)
        ->and((float) $meta['funding_gap'])->toEqual(110.0);
});

it('يصدّر القائمة بفلاترها CSV، ويسجّل التصدير', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Orphan::factory()->create(['status' => OrphanStatus::Active, 'first_name' => 'سليم']);
    Orphan::factory()->create(['status' => OrphanStatus::Draft, 'first_name' => 'نادر']);

    $res = $this->get('/api/v1/admin/orphans/export?status=active')->assertOk();
    $csv = $res->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('سليم')->not->toContain('نادر');
    $this->assertDatabaseHas('activity_log', ['description' => 'صدّر قائمة الأيتام', 'causer_id' => $admin->id]);
});

it('لا يصدّر من لا يرى الأيتام', function (): void {
    $this->actingAs(User::factory()->create());
    $this->get('/api/v1/admin/orphans/export')->assertForbidden();
});

it('ملف طفلٍ له زيارات يُفتح، و«متأخرة» محسوبة فيه كما في شاشة الزيارات', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $orphan = Orphan::factory()->create(['status' => OrphanStatus::Active]);
    FieldVisit::create([
        'orphan_id' => $orphan->id, 'supervisor_id' => User::factory()->create()->id,
        'visited_at' => now()->subMonths(5), 'next_visit_due' => now()->subMonths(2), 'findings' => 'زيارة ميدانية للمتابعة.',
    ]);

    $visits = $this->getJson("/api/v1/admin/orphans/{$orphan->id}")->assertOk()->json('data.field_visits');
    expect($visits)->toHaveCount(1)->and($visits[0]['is_overdue'])->toBeTrue();
});

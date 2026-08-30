<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Models\Orphan;
use App\Models\Report;
use App\Models\Sponsor;
use App\Models\Sponsorship;

it('يعرض للكافل كفالاته هو لا كفالات غيره', function () {
    $mine = Sponsor::factory()->create();
    $other = Sponsor::factory()->create();

    Sponsorship::factory()->for($mine)->create();
    Sponsorship::factory()->for($other)->create();

    $response = $this->actingAs($mine->user)->getJson('/api/v1/portal/overview');

    expect($response->json('data.stats.orphans_sponsored'))->toBe(1);
});

it('يمنع الكافل من رؤية تقرير طفل لا يكفله', function () {
    $sponsor = Sponsor::factory()->create();
    $stranger = Orphan::factory()->create();

    $report = Report::create([
        'orphan_id' => $stranger->id,
        'period_year' => 2026,
        'period_quarter' => 1,
        'title' => 'تقرير',
        'status' => ReportStatus::Published,
        'published_at' => now(),
    ]);

    $this->actingAs($sponsor->user)
        ->getJson("/api/v1/portal/reports/{$report->id}")
        ->assertForbidden();
});

it('يمنع الكافل من رؤية تقرير لم يُنشر لطفل يكفله', function () {
    $sponsorship = Sponsorship::factory()->create();

    $report = Report::create([
        'orphan_id' => $sponsorship->orphan_id,
        'period_year' => 2026,
        'period_quarter' => 1,
        'title' => 'مسوّدة',
        'status' => ReportStatus::Draft,
    ]);

    $this->actingAs($sponsorship->sponsor->user)
        ->getJson("/api/v1/portal/reports/{$report->id}")
        ->assertForbidden();
});

it('يسجّل قراءة التقرير مرة واحدة مهما تكرّر الفتح', function () {
    // نقيس أول وصول للتقرير إلى الكافل لا آخره
    $sponsorship = Sponsorship::factory()->create();

    $report = Report::create([
        'orphan_id' => $sponsorship->orphan_id,
        'period_year' => 2026,
        'period_quarter' => 1,
        'title' => 'تقرير منشور',
        'status' => ReportStatus::Published,
        'published_at' => now(),
    ]);

    $user = $sponsorship->sponsor->user;

    $this->actingAs($user)->getJson("/api/v1/portal/reports/{$report->id}")->assertOk();
    $this->actingAs($user)->getJson("/api/v1/portal/reports/{$report->id}")->assertOk();

    $this->assertDatabaseCount('report_views', 1);
});

it('يجعل رسالة الكافل بانتظار المراجعة إجبارياً', function () {
    // سياسة حماية الطفل مطبَّقة في المخطّط لا في النيّة
    $sponsorship = Sponsorship::factory()->create();

    $this->actingAs($sponsorship->sponsor->user)
        ->postJson("/api/v1/portal/sponsorships/{$sponsorship->id}/messages", [
            'body' => 'رسالة إلى الطفل.',
            'status' => 'delivered',          // محاولة تجاوز المراجعة
        ])
        ->assertCreated();

    $this->assertDatabaseHas('messages', [
        'sponsorship_id' => $sponsorship->id,
        'status' => 'pending_review',
    ]);
});

it('يمنع الكافل من إلغاء كفالة ليست له', function () {
    $sponsorship = Sponsorship::factory()->create();
    $stranger = Sponsor::factory()->create();

    $this->actingAs($stranger->user)
        ->deleteJson("/api/v1/portal/sponsorships/{$sponsorship->id}")
        ->assertForbidden();
});

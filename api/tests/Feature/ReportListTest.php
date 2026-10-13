<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Enums\ReportStatus;
use App\Models\Orphan;
use App\Models\Report;
use App\Models\User;

/**
 * شاشة التقارير: أعداد الحالات، والطابور الأقدم أولاً، وتغطية الربع.
 */
function makeReport(Orphan $orphan, ReportStatus $status, int $year = 2026, int $quarter = 3, array $extra = []): Report
{
    return Report::create([
        'orphan_id' => $orphan->id, 'period_year' => $year, 'period_quarter' => $quarter,
        'title' => "تقرير {$quarter}", 'status' => $status, 'supervisor_id' => User::factory()->create()->id,
        'published_at' => $status === ReportStatus::Published ? now() : null, ...$extra,
    ]);
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('يعدّ كل حالة، ويغطّي الربع: منشور ومعلّق وبلا تقرير', function (): void {
    $a = Orphan::factory()->create(['status' => OrphanStatus::Active]);
    $b = Orphan::factory()->create(['status' => OrphanStatus::Active]);
    Orphan::factory()->create(['status' => OrphanStatus::Active]);              // بلا تقرير
    Orphan::factory()->create(['status' => OrphanStatus::Draft]);               // ليس مكفولاً بعد: خارج التغطية
    makeReport($a, ReportStatus::Published);
    makeReport($b, ReportStatus::Submitted);

    $meta = $this->getJson('/api/v1/admin/reports?year=2026&quarter=3&status=published')->assertOk()->json('meta');

    expect($meta['total'])->toBe(1)
        ->and($meta['status_counts']['submitted'])->toBe(1)
        ->and($meta['all_count'])->toBe(2)
        ->and($meta['coverage'])->toMatchArray(['orphans' => 3, 'published' => 1, 'submitted' => 1, 'missing' => 1]);

    // ورابط «بلا تقرير» في شاشة الأيتام يجد الثالث وحده
    expect($this->getJson('/api/v1/admin/orphans?missing_report=2026-3')->json('meta.total'))->toBe(1);
});

it('طابور المراجعة: الأقدم انتظاراً أولاً، والبحث باسم الطفل', function (): void {
    $old = makeReport(Orphan::factory()->create(['first_name' => 'سلمان']), ReportStatus::Submitted);
    $new = makeReport(Orphan::factory()->create(['first_name' => 'نوال']), ReportStatus::Submitted);
    Report::whereKey($old->id)->update(['updated_at' => now()->subDays(5)]);

    $ids = collect($this->getJson('/api/v1/admin/reports?status=submitted')->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$old->id, $new->id]);

    expect($this->getJson('/api/v1/admin/reports?q=نوا')->json('data.0.id'))->toBe($new->id);
});

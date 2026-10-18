<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Models\FieldVisit;
use App\Models\Orphan;
use App\Models\User;

/**
 * خطة الزيارات: من فات موعده، ومن لم يُزر قط، ومن يستحق هذا الأسبوع.
 * وسجل الزيارات: الاحتياجات المرصودة تُعدّ أطفالاً لا زيارات.
 */
function visit(Orphan $orphan, string $visitedAgo, ?string $nextDue, array $needs = []): FieldVisit
{
    return FieldVisit::create([
        'orphan_id' => $orphan->id, 'supervisor_id' => User::factory()->create()->id,
        'visited_at' => now()->sub(\DateInterval::createFromDateString($visitedAgo)), 'next_visit_due' => $nextDue,
        'findings' => 'زيارة ميدانية للمتابعة الدورية.', 'needs_flagged' => $needs,
    ]);
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('يقسم الأطفال المفتوحة ملفاتهم بموعد زيارتهم، ومن لم يُزر قط أولاً', function (): void {
    $overdue = Orphan::factory()->create(['status' => OrphanStatus::Active]);
    $never = Orphan::factory()->create(['status' => OrphanStatus::FullyFunded]);
    $soon = Orphan::factory()->create(['status' => OrphanStatus::Active]);
    $closed = Orphan::factory()->create(['status' => OrphanStatus::Graduated]);

    visit($overdue, '4 months', now()->subDays(10)->toDateString());
    // زيارة أقدم فات موعدها ثم تلتها أحدث: العبرة بالأحدث
    visit($soon, '5 months', now()->subMonth()->toDateString());
    visit($soon, '1 day', now()->addDays(3)->toDateString());
    visit($closed, '6 months', now()->subMonths(3)->toDateString());

    $res = $this->getJson('/api/v1/admin/visits/schedule')->assertOk();

    expect($res->json('meta.bucket_counts'))->toMatchArray(['overdue' => 1, 'never' => 1, 'week' => 1])
        ->and($res->json('meta.all_count'))->toBe(3)
        ->and($res->json('data.0.orphan.id'))->toBe($never->id)
        ->and($res->json('data.1.orphan.id'))->toBe($overdue->id);

    expect(collect($this->getJson('/api/v1/admin/visits/schedule?bucket=week')->json('data'))->pluck('orphan.id')->all())
        ->toBe([$soon->id]);
});

it('السجل يعدّ الاحتياجات أطفالاً، ويُفلتر بها', function (): void {
    $a = Orphan::factory()->create();
    $b = Orphan::factory()->create();
    visit($a, '2 months', null, ['دعم نفسي']);
    visit($a, '1 day', null, ['دعم نفسي', 'ملابس شتوية']);
    visit($b, '1 day', null, ['دعم نفسي']);

    $res = $this->getJson('/api/v1/admin/visits')->assertOk();
    expect($res->json('meta.needs'))->toMatchArray(['دعم نفسي' => 2, 'ملابس شتوية' => 1]);

    expect($this->getJson('/api/v1/admin/visits?need='.urlencode('ملابس شتوية'))->json('meta.total'))->toBe(1);
});

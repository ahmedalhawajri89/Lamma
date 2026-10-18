<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Models\Orphan;
use App\Models\Report;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * سجل النشاط: أقسامٌ بأعدادها، و«الحساسة» لما يستحق نظرة ثانية، ونظرة عامة
 * بإيقاع الأيام ومن يعمل، وتصديرٌ يُسجَّل هو نفسه.
 */
function logAs(User $by, ?object $subject, string $description, ?string $event = null): void
{
    $log = activity()->causedBy($by);
    if ($subject) {
        $log->performedOn($subject);
    }
    if ($event) {
        $log->event($event);
    }
    $log->log($description);
}

it('يقسّم السجل ويعدّ كل قسم، ويعلّم الحساس', function () {
    $admin = User::factory()->admin()->create();
    $accountant = User::factory()->accountant()->create();
    $orphan = Orphan::factory()->create();
    // إنشاء الملف والأسرة في الإعداد يُسجَّل آلياً: يبدأ الاختبار من سجل فارغ
    Activity::query()->delete();

    logAs($admin, $orphan, 'تحقّق من مستند', 'document_verified');
    logAs($admin, $orphan, 'غيّر مسار الملف', 'status_changed');
    logAs($accountant, null, 'صدّر المدفوعات');
    logAs($admin, $accountant, 'عدّل حساب موظف', 'updated');

    $res = $this->actingAs($admin)->getJson('/api/v1/admin/activity')->assertOk();

    expect($res->json('meta.group_counts.children'))->toBe(2)
        ->and($res->json('meta.group_counts.system'))->toBe(1)
        // تغيير المسار والتصدير وحساب الموظف، لا التحقق من مستند
        ->and($res->json('meta.sensitive_count'))->toBe(3)
        ->and($res->json('meta.daily'))->toHaveCount(30)
        ->and($res->json('meta.today_count'))->toBe(4)
        ->and($res->json('meta.top_actors.0.id'))->toBe($admin->id);

    $this->actingAs($admin)->getJson('/api/v1/admin/activity?group=children')->assertJsonPath('meta.total', 2);
    $sensitive = $this->actingAs($admin)->getJson('/api/v1/admin/activity?sensitive=1')->assertJsonPath('meta.total', 3);
    expect(collect($sensitive->json('data'))->every(fn ($a) => $a['sensitive']))->toBeTrue();

    // عدد القسم لا يتبع القسم المختار، والأيام لا تتبع الفترة
    $this->actingAs($admin)->getJson('/api/v1/admin/activity?group=children&from=2000-01-01&to=2000-01-02')
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('meta.group_counts.system', 0)
        ->assertJsonPath('meta.today_count', 2);

    $this->actingAs($admin)->getJson('/api/v1/admin/activity?group=ghost')->assertUnprocessable();
    $this->actingAs($accountant)->getJson('/api/v1/admin/activity')->assertForbidden();
});

it('التصدير CSV بالفلاتر نفسها، ويُسجَّل في السجل', function () {
    $admin = User::factory()->admin()->create(['name' => 'مديرة السجل']);
    logAs($admin, Orphan::factory()->create(), 'تحقّق من مستند', 'document_verified');

    $res = $this->actingAs($admin)->get('/api/v1/admin/activity/export?group=children')->assertOk();
    $csv = $res->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('تحقّق من مستند')
        ->and($csv)->toContain('مديرة السجل')
        ->and(Activity::where('description', 'صدّر سجل النشاط')->where('causer_id', $admin->id)->exists())->toBeTrue();
});

it('اعتماد التقرير ونشره للكفلاء يُسجَّلان', function () {
    $admin = User::factory()->admin()->create();
    $report = Report::create([
        'orphan_id' => Orphan::factory()->create()->id, 'period_year' => 2026, 'period_quarter' => 3,
        'title' => 'تقرير', 'summary' => 'ملخص', 'status' => ReportStatus::Submitted,
    ]);

    $this->actingAs($admin)->postJson("/api/v1/admin/reports/{$report->id}/approve")->assertOk();
    $this->actingAs($admin)->postJson("/api/v1/admin/reports/{$report->id}/publish")->assertOk();

    expect(Activity::where('subject_id', $report->id)->where('subject_type', Report::class)->pluck('event')->all())
        ->toContain('approved', 'published');
});

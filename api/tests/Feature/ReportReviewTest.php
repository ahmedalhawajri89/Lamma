<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Enums\ReportStatus;
use App\Models\Country;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\OrphanDocument;
use App\Models\Report;
use App\Models\User;

/**
 * المراجعة: يقرأ المراجع التقرير ثم يعتمده أو يُرجعه بملاحظة،
 * والمشرف لا يرى ولا يُراجع إلا ما في بلده.
 */
function reportFor(Orphan $orphan, ?User $author = null, ReportStatus $status = ReportStatus::Submitted): Report
{
    return Report::create([
        'orphan_id' => $orphan->id,
        'period_year' => 2026,
        'period_quarter' => 3,
        'title' => 'تقرير الربع الثالث',
        'summary' => 'ملخص',
        'supervisor_id' => $author?->id,
        'status' => $status,
    ]);
}

function orphanIn(string $iso2): Orphan
{
    $country = Country::where('iso2', $iso2)->first();

    return Orphan::factory()->create(['family_id' => Family::factory()->inCountry($country->id)]);
}

it('يُرجع المراجع التقرير لكاتبه مسوّدةً مع ملاحظته', function () {
    $supervisor = User::factory()->supervisor(Country::where('iso2', 'PS')->value('id'))->create();
    $report = reportFor(orphanIn('PS'), $supervisor);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/v1/admin/reports/{$report->id}/return", ['note' => 'أضف ملاحظة الصحة والتعليم.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.review_note', 'أضف ملاحظة الصحة والتعليم.');

    // ويراه الكاتب في «بانتظارك»، لا بين المسوّدات العادية
    $keys = collect($this->actingAs($supervisor)->getJson('/api/v1/admin/queue')->json('data'))->pluck('key');
    expect($keys)->toContain('reports_returned')->not->toContain('reports_drafts');

    // وإعادة الإرسال تُزيل علامة الإرجاع وتُبقي الملاحظة للمراجع التالي
    $this->actingAs($supervisor)->postJson("/api/v1/admin/reports/{$report->id}/submit")->assertOk();
    expect($report->fresh())
        ->returned_at->toBeNull()
        ->review_note->toBe('أضف ملاحظة الصحة والتعليم.');
});

it('يرفض الإرجاع بلا ملاحظة، ولغير تقرير مُرسل', function () {
    $admin = User::factory()->admin()->create();
    $report = reportFor(orphanIn('PS'));

    $this->actingAs($admin)->postJson("/api/v1/admin/reports/{$report->id}/return", ['note' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors('note');

    $draft = reportFor(orphanIn('PS'), status: ReportStatus::Draft);
    $this->actingAs($admin)->postJson("/api/v1/admin/reports/{$draft->id}/return", ['note' => 'ملاحظة كافية'])
        ->assertUnprocessable();
});

it('لا يُرجع التقريرَ من لا يملك اعتماده', function () {
    $supervisor = User::factory()->supervisor(Country::where('iso2', 'PS')->value('id'))->create();
    $report = reportFor(orphanIn('PS'));

    $this->actingAs($supervisor)
        ->postJson("/api/v1/admin/reports/{$report->id}/return", ['note' => 'ملاحظة كافية'])
        ->assertForbidden();
});

it('يقصر تقارير المشرف على بلده في القائمة والعرض', function () {
    $supervisor = User::factory()->supervisor(Country::where('iso2', 'PS')->value('id'))->create();
    $mine = reportFor(orphanIn('PS'));
    $other = reportFor(orphanIn('YE'));

    $ids = collect($this->actingAs($supervisor)->getJson('/api/v1/admin/reports?per_page=500')->json('data'))->pluck('id');
    expect($ids)->toContain($mine->id)->not->toContain($other->id);

    $this->actingAs($supervisor)->getJson("/api/v1/admin/reports/{$mine->id}")->assertOk();
    $this->actingAs($supervisor)->getJson("/api/v1/admin/reports/{$other->id}")->assertForbidden();
});

it('لا تصل ملاحظة المراجعة إلى بوابة الكافل', function () {
    $report = reportFor(orphanIn('PS'));
    $report->update(['review_note' => 'داخلي']);

    $data = (new \App\Http\Resources\ReportResource($report))->resolve(request());

    expect($data)->not->toHaveKey('review_note');
});

it('طابور المستندات لمن يوثّق: يعرض غير المُتحقَّق منها، والمشرف لا يدخله', function () {
    $supervisor = User::factory()->supervisor(Country::where('iso2', 'PS')->value('id'))->create();

    $doc = fn (Orphan $o, bool $verified = false) => OrphanDocument::create([
        'orphan_id' => $o->id, 'type' => DocumentType::cases()[0], 'path' => 'documents/x.pdf',
        'original_name' => 'x.pdf', 'mime' => 'application/pdf', 'size_bytes' => 10,
        'verified_at' => $verified ? now() : null,
    ]);

    $doc(orphanIn('PS'));
    $doc(orphanIn('PS'), verified: true);
    $foreign = $doc(orphanIn('YE'));

    // التحقق جزء من التوثيق، والمشرف يرفع ولا يوثّق
    $this->actingAs($supervisor)->getJson('/api/v1/admin/documents')->assertForbidden();
    $this->actingAs($supervisor)->postJson("/api/v1/admin/documents/{$foreign->id}/verify")->assertForbidden();

    // والمدير يراها كلها، ومعها مهمة في «بانتظارك»
    $admin = User::factory()->admin()->create();
    expect($this->actingAs($admin)->getJson('/api/v1/admin/documents')->json('meta.total'))->toBe(2);
    expect(collect($this->actingAs($admin)->getJson('/api/v1/admin/queue')->json('data'))->firstWhere('key', 'documents_verify')['count'])->toBe(2);
});

/**
 * كانت المراجعة تردّ 500: الحالة محميّة من التعبئة الجماعية، و`update()`
 * يرميها. فلم تُعتمد رسالة ولم تُرفض من اللوحة قط.
 */
it('يعتمد المراجع الرسالة ويرفضها بسبب، والإنشاء لا يمرّر حالة', function () {
    $admin = User::factory()->admin()->create();
    $sponsorship = \App\Models\Sponsorship::factory()->create();

    // الإنشاء لا يمرّر حالة: المحاولة تُرفض صراحةً
    expect(fn () => \App\Models\Message::create([
        'sponsorship_id' => $sponsorship->id, 'sender_type' => 'sponsor', 'body' => 'رسالة', 'status' => 'approved',
    ]))->toThrow(\Illuminate\Database\Eloquent\MassAssignmentException::class);

    $message = \App\Models\Message::create(['sponsorship_id' => $sponsorship->id, 'sender_type' => 'sponsor', 'body' => 'رسالة'])->fresh();
    expect($message->status)->toBe(\App\Enums\MessageStatus::PendingReview);

    $this->actingAs($admin)->postJson("/api/v1/admin/messages/{$message->id}/review", ['decision' => 'approve'])
        ->assertOk()->assertJsonPath('data.status', 'approved');

    $other = \App\Models\Message::create(['sponsorship_id' => $sponsorship->id, 'sender_type' => 'sponsor', 'body' => 'أخرى']);
    $this->actingAs($admin)->postJson("/api/v1/admin/messages/{$other->id}/review", ['decision' => 'reject', 'reason' => 'معلومات شخصية'])
        ->assertOk();
    expect($other->fresh())
        ->status->toBe(\App\Enums\MessageStatus::Rejected)
        ->rejection_reason->toBe('معلومات شخصية')
        ->reviewed_by->toBe($admin->id);
});

<?php

declare(strict_types=1);

use App\Models\DisbursementCategory;
use App\Models\Orphan;
use App\Models\User;

/**
 * تسجيل الصرف واعتماده.
 *
 * كل رقم على صفحة الشفافية العامة مجموعُ صفوفٍ تبدأ من هنا، ولم يكن لهذا
 * المسار واجهة — فالأرقام المنشورة لم يكن لها سبيل تحديث من اللوحة.
 */
function disbursementPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => DisbursementCategory::query()->where('is_overhead', false)->value('id'),
        'amount' => 125.5,
        'currency' => 'USD',
        'spent_on' => now()->toDateString(),
        'description' => 'رسوم فصل دراسي',
    ], $overrides);
}

it('يسجّل الصرف غير معتمَد', function (): void {
    $this->actingAs(User::factory()->accountant()->create());

    $this->postJson('/api/v1/admin/disbursements', disbursementPayload())
        ->assertCreated()
        ->assertJsonPath('data.is_approved', false);
});

/**
 * أهمّ اختبار في هذا الملف: مبدأ العينين.
 *
 * من سجّل الصرف لا يعتمده. هذا هو الحارس الوحيد بين خطأٍ في الإدخال —
 * أو عبثٍ متعمَّد — ورقمٍ منشور على صفحة تقول للناس «تحقّقوا منّا».
 */
it('يمنع من سجّل الصرف أن يعتمده', function (): void {
    $accountant = User::factory()->accountant()->create();
    $this->actingAs($accountant);

    $id = $this->postJson('/api/v1/admin/disbursements', disbursementPayload())
        ->json('data.id');

    $this->postJson("/api/v1/admin/disbursements/{$id}/approve")->assertForbidden();

    // وشخصٌ آخر يعتمده
    $this->actingAs(User::factory()->admin()->create());
    $this->postJson("/api/v1/admin/disbursements/{$id}/approve")
        ->assertOk()
        ->assertJsonPath('data.is_approved', true);
});

/** الواجهة تحتاج المعرّف لتعرف أن الزرّ أمام صاحبه فلا تعرضه. */
it('يُرجع معرّف من سجّل الصرف', function (): void {
    $accountant = User::factory()->accountant()->create();
    $this->actingAs($accountant);

    $this->postJson('/api/v1/admin/disbursements', disbursementPayload())
        ->assertJsonPath('data.recorded_by', $accountant->id);
});

it('يرفض صرفاً بتاريخ مستقبلي', function (): void {
    $this->actingAs(User::factory()->accountant()->create());

    $this->postJson('/api/v1/admin/disbursements', disbursementPayload([
        'spent_on' => now()->addDay()->toDateString(),
    ]))->assertStatus(422)->assertJsonValidationErrors('spent_on');
});

it('يقبل صرفاً عاماً بلا طفل', function (): void {
    $this->actingAs(User::factory()->accountant()->create());

    $this->postJson('/api/v1/admin/disbursements', disbursementPayload([
        'category_id' => DisbursementCategory::query()->where('is_overhead', true)->value('id'),
        'orphan_id' => null,
    ]))->assertCreated();
});

it('يربط الصرف بطفل حين يُذكر', function (): void {
    $this->actingAs(User::factory()->accountant()->create());
    $orphan = Orphan::factory()->create();

    $this->postJson('/api/v1/admin/disbursements', disbursementPayload(['orphan_id' => $orphan->id]))
        ->assertCreated()
        ->assertJsonPath('data.orphan.id', $orphan->id);
});

/**
 * المشرف الميداني لا يسجّل صرفاً.
 *
 * دوره ميداني بحت: الملفات والتقارير والزيارات، ولا شيء مالي.
 */
it('يمنع المشرف الميداني من تسجيل الصرف', function (): void {
    $this->actingAs(User::factory()->supervisor()->create());

    $this->postJson('/api/v1/admin/disbursements', disbursementPayload())->assertForbidden();
});

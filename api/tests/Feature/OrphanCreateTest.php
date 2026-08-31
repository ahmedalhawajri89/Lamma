<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Models\Country;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\Program;
use App\Models\User;

/**
 * إنشاء ملف يتيم من اللوحة.
 *
 * لم يكن لهذا المسار واجهة، فلم يمرّ به أحد — وكان يسقط بخطأ ٥٠٠: القيمة
 * الافتراضية `draft` تعيش في الهجرة (‏MySQL) لا في الموديل، فيرجع
 * `Orphan::create()` نموذجاً حالته `null` ويسقط `$this->status->value`
 * في المورد. هذه الاختبارات تحرس أن يبقى الموديل مطابقاً للجدول.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

function orphanPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'اختبار',
        'last_name' => 'الحواجري',
        'gender' => 'male',
        'date_of_birth' => now()->subYears(9)->toDateString(),
        'family_id' => Family::factory()->create()->id,
        'program_id' => Program::query()->firstOrFail()->id,
        'monthly_need' => 65,
    ], $overrides);
}

it('ينشئ ملفاً مسوّدةً ويعيده كاملاً', function (): void {
    $response = $this->postJson('/api/v1/admin/orphans', orphanPayload())
        ->assertCreated();

    // الحقلان المشتقّان من قيم افتراضية — وهما ما كان يسقط
    $response->assertJsonPath('data.status', OrphanStatus::Draft->value)
        ->assertJsonPath('data.is_urgent', false)
        ->assertJsonPath('data.is_public', false);

    expect($response->json('data.reference_code'))->toStartWith('KFL-');
});

/**
 * لا يُنشأ ملف منشور مباشرةً.
 *
 * النشر يمرّ بالتوثيق، والتوثيق يشترط مستندات مُتحقَّقاً منها. قبول
 * `status: active` من الطلب يفتح باباً يلتفّ على ذلك كلّه.
 */
it('يرفض إنشاء ملف بحالة منشورة', function (): void {
    $this->postJson('/api/v1/admin/orphans', orphanPayload(['status' => OrphanStatus::Active->value]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('يرفض تاريخ ميلاد يتجاوز سنّ البرنامج', function (): void {
    $this->postJson('/api/v1/admin/orphans', orphanPayload([
        'date_of_birth' => now()->subYears(19)->toDateString(),
    ]))->assertStatus(422)->assertJsonValidationErrors('date_of_birth');
});

it('يحرّر الملف ويُبقي ما لم يُرسل', function (): void {
    $orphan = Orphan::factory()->create(['dream' => 'طبيبة', 'grade_level' => 'السادس']);

    $this->patchJson("/api/v1/admin/orphans/{$orphan->id}", ['grade_level' => 'السابع'])
        ->assertOk()
        ->assertJsonPath('data.grade_level', 'السابع')
        ->assertJsonPath('data.dream', 'طبيبة');
});

/**
 * المحاسب لا يُنشئ ملفات.
 *
 * حراسة المسار `role:admin|accountant|field_supervisor` تمرّره إلى المتحكّم،
 * وهو لا يملك `orphans.create`. وكانت السياسة معرَّفة ولا تُستدعى إطلاقاً —
 * بابٌ مفتوح خلف حارسٍ مكتوب لا يعمل.
 */
it('يمنع المحاسب من إنشاء ملف', function (): void {
    $this->actingAs(User::factory()->accountant()->create());

    $this->postJson('/api/v1/admin/orphans', orphanPayload())->assertForbidden();
});

/**
 * المشرف الميداني ينشئ في بلده وحده.
 *
 * بلا هذا الفحص يُنشئ ملفاً تحت أسرة في بلد آخر ثم لا يراه: `view` تحجبه
 * عنه. سجلٌّ يُنشئه من لا يملك رؤيته ليس ثغرة وحدها بل بيانات يتيمة.
 */
it('يمنع المشرف من الإنشاء خارج بلده', function (): void {
    // البلدان من البذرة المرجعية لا من مصنع — هي بيانات ثابتة لا عيّنات
    [$mine, $other] = Country::query()->orderBy('id')->take(2)->pluck('id')->all();

    $this->actingAs(User::factory()->supervisor($mine)->create());

    $this->postJson('/api/v1/admin/orphans', orphanPayload([
        'family_id' => Family::factory()->inCountry($other)->create()->id,
    ]))->assertForbidden();

    $this->postJson('/api/v1/admin/orphans', orphanPayload([
        'family_id' => Family::factory()->inCountry($mine)->create()->id,
    ]))->assertCreated();
});

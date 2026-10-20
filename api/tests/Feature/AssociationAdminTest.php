<?php

declare(strict_types=1);

use App\Enums\CareArrangement;
use App\Enums\OrphanStatus;
use App\Models\Association;
use App\Models\Country;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\User;

/**
 * المرحلة 2 في اللوحة: إدارة الجمعيات وتوثيقها، والجمعية وترتيب الرعاية
 * في نموذج الأسرة، وموظف الجمعية، وأسباب تعذّر النشر.
 */
it('يسجّل المدير جمعية قيد المراجعة، ولا يوثّقها قبل توقيعاتها', function () {
    $admin = User::factory()->admin()->create();
    $ps = Country::where('iso2', 'PS')->value('id');

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/associations', [
        'name_ar' => 'جمعية الأمل', 'name_en' => 'Hope Association', 'country_id' => $ps,
        'transfer_details' => 'IBAN PS12 3456', 'transfer_currency' => 'USD',
    ])->assertCreated()->assertJsonPath('data.status', 'under_review')
        ->assertJsonPath('data.slug', 'hope-association')
        ->assertJsonPath('data.has_transfer_details', true)
        ->assertJsonMissingPath('data.transfer_details')
        ->json('data.id');

    $this->actingAs($admin)->postJson("/api/v1/admin/associations/{$id}/verify")
        ->assertUnprocessable()->assertJsonFragment(['لم توقّع الجمعية سياسة حماية الطفل.']);

    $this->actingAs($admin)->postJson("/api/v1/admin/associations/{$id}/verify", ['safeguarding_signed' => true, 'family_care_attested' => true])
        ->assertOk()->assertJsonPath('data.status', 'verified');

    expect(Association::find($id)->verified_by)->toBe($admin->id);
});

it('الإيقاف يحتاج سبباً، والجمعيات للمدير وحده', function () {
    $assoc = Association::factory()->create();

    $this->actingAs(User::factory()->supervisor()->create())->getJson('/api/v1/admin/associations')->assertForbidden();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->postJson("/api/v1/admin/associations/{$assoc->id}/suspend")->assertUnprocessable();
    $this->actingAs($admin)->postJson("/api/v1/admin/associations/{$assoc->id}/suspend", ['reason' => 'أوراق التسجيل منتهية'])
        ->assertOk()->assertJsonPath('data.status', 'suspended');

    $list = $this->actingAs($admin)->getJson('/api/v1/admin/associations')->assertOk();
    expect($list->json('meta.status_counts.suspended'))->toBe(1);
});

it('نموذج الأسرة: الجمعية من بلدها، و«أؤكّد الآن» يسجّل الإقرار، وتغيير الترتيب بلا تأكيد يُسقطه', function () {
    $admin = User::factory()->admin()->create();
    $family = Family::factory()->create();
    $foreign = Association::factory()->inCountry(Country::where('id', '!=', $family->country_id)->value('id'))->create();

    $this->actingAs($admin)->patchJson("/api/v1/admin/families/{$family->id}", ['association_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors('association_id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/families/{$family->id}", ['care_arrangement' => 'with_grandparents'])
        ->assertOk()->assertJsonPath('data.care_arrangement', 'with_grandparents')->assertJsonPath('data.care_current', false);

    $this->actingAs($admin)->patchJson("/api/v1/admin/families/{$family->id}", ['care_attest' => true])
        ->assertOk()->assertJsonPath('data.care_current', true);

    expect($family->fresh()->care_attested_by)->toBe($admin->id)
        ->and($family->fresh()->care_arrangement)->toBe(CareArrangement::WithGrandparents);
});

it('موظف الجمعية يُنشأ بجمعيته بلا بلد، وبلده بلدها', function () {
    $admin = User::factory()->admin()->create();
    $assoc = Association::factory()->create();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'name' => 'مشرفة الجمعية', 'email' => 'assoc-staff@example.org', 'role' => 'field_supervisor',
        'association_id' => $assoc->id,
    ])->assertCreated()->json('data.id');

    $profile = User::find($id)->staffProfile;
    expect($profile->association_id)->toBe($assoc->id)
        ->and($profile->assigned_country_id)->toBe($assoc->country_id);

    // وبلا بلد ولا جمعية يُرفض كما كان
    $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'name' => 'مشرف بلا نطاق', 'email' => 'nobody@example.org', 'role' => 'field_supervisor',
    ])->assertUnprocessable()->assertJsonValidationErrors('assigned_country_id');
});

it('تقول صفحة الطفل في اللوحة لماذا لا يُنشر بعد', function () {
    $orphan = Orphan::factory()->create([
        'status' => OrphanStatus::Draft, 'verified_at' => now(), 'published_at' => null,
        'family_id' => Family::factory()->state(['care_arrangement' => CareArrangement::ResidentialCare]),
    ]);

    $blockers = $this->actingAs(User::factory()->admin()->create())
        ->getJson("/api/v1/admin/orphans/{$orphan->id}")->assertOk()->json('data.publish_blockers');

    expect($blockers)->toBe(['الطفل في دار رعاية. «لمّة» تكفل الأطفال في أسرهم وحدهم.']);
});

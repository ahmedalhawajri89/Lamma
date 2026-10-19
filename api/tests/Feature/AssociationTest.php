<?php

declare(strict_types=1);

use App\Enums\AssociationStatus;
use App\Enums\CareArrangement;
use App\Enums\GuardianRelation;
use App\Models\Association;
use App\Models\Country;
use App\Models\Family;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * المرحلة 2: الجمعيات الشريكة وترتيب الرعاية في نموذج البيانات.
 */
it('يقدّر ترتيب الرعاية من صلة الوصي، ودار الرعاية وحدها ليست رعاية أسرية', function () {
    expect(CareArrangement::fromGuardianRelation(GuardianRelation::Mother))->toBe(CareArrangement::WithMother)
        ->and(CareArrangement::fromGuardianRelation('grandfather'))->toBe(CareArrangement::WithGrandparents)
        ->and(CareArrangement::fromGuardianRelation('aunt'))->toBe(CareArrangement::WithRelatives)
        ->and(CareArrangement::fromGuardianRelation('sibling'))->toBe(CareArrangement::WithOlderSibling)
        ->and(CareArrangement::fromGuardianRelation(null))->toBeNull()
        ->and(collect(CareArrangement::cases())->reject->isFamilyBased()->values()->all())->toBe([CareArrangement::ResidentialCare]);
});

it('تأخذ الأسرة الجديدة الجمعية الموثّقة الوحيدة في بلدها، ولا تخمين حين تتعدّد', function () {
    [$one, $two] = Country::query()->limit(2)->pluck('id')->all();
    $only = Association::factory()->inCountry($one)->create();
    Association::factory()->inCountry($two)->create();
    Association::factory()->inCountry($two)->create();
    Association::factory()->inCountry($one)->pending()->create();   // غير الموثّقة لا تُحسب

    $a = Family::factory()->unattested()->inCountry($one)->create();
    $b = Family::factory()->unattested()->inCountry($two)->create();

    expect($a->association_id)->toBe($only->id)
        ->and($b->association_id)->toBeNull();
});

it('موظف الجمعية تُسند إليه أسره هو', function () {
    $country = Country::query()->value('id');
    Association::factory()->inCountry($country)->create();
    $mine = Association::factory()->inCountry($country)->create();

    $staff = User::factory()->create();
    $staff->staffProfile()->create(['employee_code' => 'EMP-9001', 'association_id' => $mine->id]);

    $this->actingAs($staff);
    $family = Family::factory()->unattested()->inCountry($country)->create();

    expect($family->association_id)->toBe($mine->id);
});

it('إقرار الرعاية يُحفظ بمن أقرّ ومتى يُراجَع، ويسقط بعد اثني عشر شهراً', function () {
    $family = Family::factory()->unattested()->create();
    $admin = User::factory()->admin()->create();

    $family->attestCare(CareArrangement::WithGrandparents, $admin);
    $family->refresh();

    expect($family->care_arrangement)->toBe(CareArrangement::WithGrandparents)
        ->and($family->care_attested_by)->toBe($admin->id)
        ->and($family->care_review_due_at->toDateString())->toBe(now()->addMonths(12)->toDateString())
        ->and($family->hasCurrentCareAttestation())->toBeTrue();

    $this->travel(13)->months();
    expect($family->fresh()->hasCurrentCareAttestation())->toBeFalse();
});

it('بيانات تحويل الجمعية مشفّرة في القاعدة ولا تخرج في JSON', function () {
    $association = Association::factory()->create(['transfer_details' => 'IBAN PS00 1234 5678']);

    $raw = DB::table('associations')->where('id', $association->id)->value('transfer_details');

    expect($raw)->not->toContain('1234')
        ->and($association->fresh()->transfer_details)->toBe('IBAN PS00 1234 5678')
        ->and($association->toArray())->not->toHaveKey('transfer_details')
        ->and($association->status)->toBe(AssociationStatus::Verified);
});

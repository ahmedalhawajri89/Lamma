<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Family;
use App\Models\Guardian;
use App\Models\User;

/**
 * إنشاء أسرة من اللوحة.
 *
 * الأسرة أسبق من اليتيم: لا يُنشأ ملف بلا واحدة. ولم يكن لهذا المسار واجهة،
 * فلم يرسل أحد `national_id` قطّ — وكان إرساله يُسقط الطلب بـ٥٠٠: الحقل
 * مستثنى من `$fillable` بقصد (‏`setNationalId` وحدها تكتب البصمة)،
 * والمتحكّم كان يمرّره في مُنشئ الموديل.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

function familyPayload(array $overrides = []): array
{
    return array_merge([
        'guardian' => [
            'full_name' => 'أم خالد',
            'relation' => 'mother',
            'phone' => '+970590000000',
            'occupation' => 'خياطة',
            'monthly_income' => 40,
        ],
        // المدينة من الدولة نفسها: الخادم يرفض مدينةً من دولة أخرى
        'country_id' => ($city = City::query()->first())->country_id,
        'city_id' => $city->id,
        'address' => 'حيّ النصر',
        'members_count' => 6,
        'monthly_income' => 40,
        'housing_status' => 'rented',
    ], $overrides);
}

it('ينشئ أسرة برمز متسلسل', function (): void {
    $response = $this->postJson('/api/v1/admin/families', familyPayload())->assertCreated();

    expect($response->json('data.family_code'))->toStartWith('FAM-')
        ->and($response->json('data.guardian.full_name'))->toBe('أم خالد');
});

/**
 * الرقم يُقبل ولا يُخزَّن.
 *
 * البصمة المُملَّحة تكفي لكشف التكرار ولا تكفي لإعادة بناء الرقم لو تسرّبت
 * القاعدة. وهذا الاختبار يحرس الأمرين معاً: أن الطلب لا يسقط، وأن الرقم
 * الخام لا يظهر في أي عمود.
 */
it('يقبل رقم الهوية ويخزّنه بصمةً لا رقماً', function (): void {
    $this->postJson('/api/v1/admin/families', familyPayload([
        'guardian' => [...familyPayload()['guardian'], 'national_id' => '900112233'],
    ]))->assertCreated();

    $guardian = Guardian::query()->latest('id')->firstOrFail();

    expect($guardian->getAttributes())
        ->not->toContain('900112233')
        ->and($guardian->national_id_hash)->not->toBeNull();
});

it('يرفض أسرة بلا وصيّ أو بلا دولة', function (): void {
    $payload = familyPayload();
    unset($payload['guardian']['full_name'], $payload['country_id']);

    $this->postJson('/api/v1/admin/families', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['guardian.full_name', 'country_id']);
});

it('يحرّر الأسرة ووصيَّها معاً', function (): void {
    $family = Family::factory()->create(['members_count' => 4]);

    $this->patchJson("/api/v1/admin/families/{$family->id}", [
        'members_count' => 7,
        'guardian' => ['occupation' => 'معلّمة'],
    ])->assertOk()
        ->assertJsonPath('data.members_count', 7)
        ->assertJsonPath('data.guardian.occupation', 'معلّمة');
});

it('يمنع المحاسب من إنشاء أسرة', function (): void {
    $this->actingAs(User::factory()->accountant()->create());

    $this->postJson('/api/v1/admin/families', familyPayload())->assertForbidden();
});

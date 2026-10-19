<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Models\Orphan;
use App\Models\Sponsorship;

/**
 * المرحلة 3: الإفصاح قبل الكفالة. لا كفالة بلا موافقةٍ على كيف يُصرف
 * المبلغ، ويُحفظ أيّ نصٍّ وافق عليه ومتى.
 */
function disclosureOrphan(): Orphan
{
    return Orphan::factory()->create(['status' => OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now(), 'monthly_need' => 40]);
}

it('يرفض الكفالة بلا موافقة على الإفصاح', function () {
    $this->postJson('/api/v1/public/checkout', [
        'orphan_reference' => disclosureOrphan()->reference_code, 'interval_months' => 1, 'method' => 'bank_transfer',
        'name' => 'زائر الإفصاح', 'email' => 'disclose@example.org',
    ])->assertUnprocessable()->assertJsonValidationErrors(['disclosure' => 'اقرأ كيف يُصرف مبلغك ووافق عليه للمتابعة.']);

    expect(Sponsorship::count())->toBe(0);
});

it('يحفظ نسخة الإفصاح ووقت الموافقة مع الكفالة', function () {
    $this->postJson('/api/v1/public/checkout', [
        'disclosure' => true, 'orphan_reference' => disclosureOrphan()->reference_code, 'interval_months' => 1,
        'method' => 'bank_transfer', 'name' => 'زائر الإفصاح', 'email' => 'disclose2@example.org',
    ])->assertSuccessful();

    $s = Sponsorship::sole();
    expect($s->disclosure_version)->toBe(config('kafala.disclosure_version'))
        ->and($s->disclosure_accepted_at)->not->toBeNull();
});

it('تعلن صفحة الكفالة نسبة التشغيل وجمعية الطفل قبل الدفع', function () {
    $orphan = disclosureOrphan();

    $this->get("/checkout?id={$orphan->reference_code}")->assertOk()
        ->assertSee('كيف يُصرف مبلغك')
        ->assertSee('يُقتطع 7٪ من كل دفعة', false)
        ->assertSee($orphan->family->association->name_ar)
        ->assertSee('name="disclosure"', false);

    expect($this->getJson('/api/v1/public/checkout/config')->json('data.fee_percent'))->toEqual(7);
});

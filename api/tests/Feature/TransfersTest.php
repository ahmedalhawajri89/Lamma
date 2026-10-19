<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransferStatus;
use App\Models\Association;
use App\Models\AssociationTransfer;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Sponsorship;
use App\Services\TransferPreparer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * المرحلة 3: نسبة التشغيل لقطةٌ على الدفعة، والتحويل الشهري للجمعية
 * بتفصيله لكل طفل.
 */
function paid(Sponsorship $s, float $amount, string $at, PaymentStatus $status = PaymentStatus::Succeeded, ?float $fee = null): Payment
{
    return Payment::create([
        'reference_code' => 'PAY-'.Str::upper(Str::random(8)), 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id,
        'amount' => $amount, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => $amount,
        'method' => PaymentMethod::Card, 'status' => $status, 'paid_at' => Carbon::parse($at),
        'idempotency_key' => Str::uuid()->toString(), 'platform_fee_percent' => $fee,
    ]);
}

function childOf(Association $a): Orphan
{
    return Orphan::factory()->create(['family_id' => Family::factory()->state(['country_id' => $a->country_id, 'association_id' => $a->id])]);
}

it('تُحفظ نسبة التشغيل على الدفعة لحظة إنشائها، وتغييرها لا يمسّ ما مضى', function () {
    $s = Sponsorship::factory()->create();
    $old = paid($s, 100, '2026-08-05');

    Setting::where('group', 'finance')->where('key', 'platform_fee_percent')->update(['value' => '10']);
    cache()->flush();
    $new = paid($s, 100, '2026-09-05');

    expect((float) $old->fresh()->platform_fee_percent)->toBe(7.0)
        ->and((float) $new->fresh()->platform_fee_percent)->toBe(10.0)
        ->and($new->fresh()->feeBase())->toBe(10.0);
});

it('يجمع تحويل الشهر لكل جمعية ما دفعه كفلاء أطفالها، ناقص تشغيل كل دفعة بنسبتها', function () {
    $a = Association::factory()->create();
    $b = Association::factory()->create();
    $amna = childOf($a);
    $omar = childOf($a);
    $other = childOf($b);

    $s1 = Sponsorship::factory()->create(['orphan_id' => $amna->id]);
    $s2 = Sponsorship::factory()->create(['orphan_id' => $amna->id]);
    $s3 = Sponsorship::factory()->create(['orphan_id' => $omar->id]);
    $s4 = Sponsorship::factory()->create(['orphan_id' => $other->id]);

    paid($s1, 100, '2026-09-03');                                 // 7٪
    paid($s2, 50, '2026-09-20', fee: 10);                         // نسبة أخرى محفوظة عليها
    paid($s3, 40, '2026-09-28');
    paid($s3, 40, '2026-09-29', PaymentStatus::Refunded);         // المستردّة لا تُحوَّل
    paid($s3, 40, '2026-10-01');                                  // شهر آخر
    paid($s4, 200, '2026-09-10');

    $r = app(TransferPreparer::class)->prepare(Carbon::parse('2026-09-15'));

    $ta = AssociationTransfer::where('association_id', $a->id)->with('lines')->first();
    $amnaLine = $ta->lines->firstWhere('orphan_id', $amna->id);

    expect($r['created'])->toBe(2)
        ->and((float) $ta->received_base)->toBe(190.0)
        ->and((float) $ta->fee_base)->toBe(14.8)                  // 7 + 5 + 2.80
        ->and((float) $ta->amount_base)->toBe(175.2)
        ->and($ta->reference_code)->toBe(sprintf('TRF-2026-09-A%04d', $a->id))
        ->and($amnaLine->payments_count)->toBe(2)
        ->and((float) $amnaLine->amount_base)->toBe(138.0)        // 150 - 12
        ->and($ta->status)->toBe(TransferStatus::Draft);
});

it('يُعاد بناء المسوّدة بما استجدّ، ولا يُمسّ ما أُرسل', function () {
    $a = Association::factory()->create();
    $s = Sponsorship::factory()->create(['orphan_id' => childOf($a)->id]);
    $prep = fn () => app(TransferPreparer::class)->prepare(Carbon::parse('2026-09-01'));

    paid($s, 100, '2026-09-03');
    $prep();
    paid($s, 100, '2026-09-25');
    $r = $prep();

    $t = AssociationTransfer::where('association_id', $a->id)->first();
    expect($r['rebuilt'])->toBe(1)->and((float) $t->received_base)->toBe(200.0)->and($t->lines()->count())->toBe(1);

    $t->update(['status' => TransferStatus::Sent, 'sent_at' => now()]);
    paid($s, 100, '2026-09-26');
    $r = $prep();

    expect($r['locked'])->toBe(1)->and((float) $t->fresh()->received_base)->toBe(200.0);
});

it('يقبل الأمر الشهر بصيغته وحدها', function () {
    $this->artisan('kafala:transfers-prepare', ['month' => '2026-13'])->assertExitCode(2);
    $this->artisan('kafala:transfers-prepare', ['month' => '2026-09'])->assertSuccessful();
});

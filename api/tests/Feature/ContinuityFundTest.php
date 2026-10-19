<?php

declare(strict_types=1);

use App\Enums\FundEntryKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Mail\PaymentIssueMail;
use App\Models\Association;
use App\Models\AssociationTransfer;
use App\Models\ContinuityFundEntry;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Services\ChildLedgerService;
use App\Services\ContinuityFund;
use App\Services\TransferPreparer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * صندوق الاستمرارية: القسط الذي لم يُحلّ يغطّيه الصندوق فلا ينقطع ما يصل
 * الطفل، ويُحوَّل مع تحويل جمعيته، حتى ثلاثة أشهر ثم تنتهي الكفالة.
 */
function fundDonation(float $amount): ContinuityFundEntry
{
    return ContinuityFundEntry::create(['kind' => FundEntryKind::Donation, 'amount_base' => $amount, 'period' => now()->startOfMonth()]);
}

/** كفالةٌ في جمعية، بطاقتها مرفوضة، واستحقاقها قبل $daysAgo يوماً واستنفدت محاولاتها. */
function stuckSponsorship(int $daysAgo = 15, array $extra = []): Sponsorship
{
    $association = Association::factory()->create();
    $orphan = Orphan::factory()->create(['family_id' => Family::factory()->state(['country_id' => $association->country_id, 'association_id' => $association->id])]);

    return Sponsorship::factory()->create([
        'orphan_id' => $orphan->id,
        'status' => SponsorshipStatus::Active,
        'monthly_amount' => 40,
        'billing_day' => 1,
        'next_due_at' => today()->subDays($daysAgo),
        'payment_method' => PaymentMethod::Card->value,
        'gateway_payment_method' => 'pm_demo_decline',
        'payment_issue_since' => today()->subDays($daysAgo),
        'payment_failures' => Sponsorship::maxPaymentAttempts(),
        ...$extra,
    ]);
}

it('حصة الصندوق من تشغيل الشهر: 1٪ مما وصل، وإعادة الحساب تحدّثها ولا تكرّرها', function () {
    $s = Sponsorship::factory()->create();
    $pay = fn (float $amount) => Payment::create([
        'reference_code' => 'PAY-'.Str::upper(Str::random(8)), 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id,
        'amount' => $amount, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => $amount, 'method' => PaymentMethod::Card,
        'status' => PaymentStatus::Succeeded, 'paid_at' => '2026-08-10', 'idempotency_key' => Str::uuid()->toString(),
    ]);
    $pay(600);
    $fund = app(ContinuityFund::class);

    expect((float) $fund->accrueShare(now()->setDate(2026, 8, 1))->amount_base)->toBe(6.0);

    $pay(400);
    $fund->accrueShare(now()->setDate(2026, 8, 1));
    expect(ContinuityFundEntry::where('kind', FundEntryKind::Share)->sole()->amount_base)->toBe('10.00')
        ->and(ContinuityFundEntry::balance())->toBe(10.0);
});

it('القسط المتعثّر بعد مهلته يغطّيه الصندوق شهراً: يتقدّم الموعد وينتهي التعثّر ويُبلَّغ الكافل', function () {
    Mail::fake();
    fundDonation(500);
    $s = stuckSponsorship();
    $due = $s->next_due_at->copy();

    $this->artisan('kafala:continuity-cover')->assertSuccessful();

    $grant = ContinuityFundEntry::where('kind', FundEntryKind::Grant)->sole();
    $s->refresh();
    expect((float) $grant->amount_base)->toBe(40.0)
        ->and($grant->orphan_id)->toBe($s->orphan_id)
        ->and($grant->association_id)->not->toBeNull()
        ->and($grant->period->format('Y-m'))->toBe($due->format('Y-m'))
        ->and($s->next_due_at->format('Y-m'))->toBe($due->copy()->addMonthNoOverflow()->format('Y-m'))
        ->and($s->hasPaymentIssue())->toBeFalse()
        ->and(ContinuityFundEntry::balance())->toBe(460.0);
    Mail::assertSent(PaymentIssueMail::class, fn ($m) => $m->stage === 'covered' && $m->monthsLeft === 2);

    // التشغيل ثانيةً في اليوم نفسه لا يغطّي مرتين
    $this->artisan('kafala:continuity-cover');
    expect(ContinuityFundEntry::where('kind', FundEntryKind::Grant)->count())->toBe(1);
});

it('لا تغطية قبل مهلة الاستعادة، ولا قبل أن تُستنفد المحاولات', function () {
    fundDonation(500);
    stuckSponsorship(10);
    stuckSponsorship(20, ['payment_failures' => 1]);

    $this->artisan('kafala:continuity-cover');

    expect(ContinuityFundEntry::where('kind', FundEntryKind::Grant)->count())->toBe(0);
});

it('ما غطّاه الصندوق يُحوَّل مع تحويل الجمعية في سطر الطفل، ويراه الكافل في سجلّه', function () {
    Mail::fake();
    fundDonation(500);
    $s = stuckSponsorship();
    $this->artisan('kafala:continuity-cover');

    app(TransferPreparer::class)->prepare(now());

    $transfer = AssociationTransfer::with('lines')->sole();
    expect((float) $transfer->fund_base)->toBe(40.0)
        ->and((float) $transfer->amount_base)->toBe(40.0)
        ->and((float) $transfer->lines->sole()->fund_base)->toBe(40.0)
        ->and($transfer->lines->sole()->orphan_id)->toBe($s->orphan_id);

    $ledger = app(ChildLedgerService::class)->forSponsorship($s->fresh(), 3);
    expect($ledger['totals']['fund'])->toBe(40.0)
        ->and($ledger['months'][0]['fund'])->toBe(40.0);
});

it('بعد ثلاثة أشهر بلا دفعة من الكافل تنتهي الكفالة ويعود الطفل إلى الموقع', function () {
    Mail::fake();
    fundDonation(500);
    $s = stuckSponsorship();
    foreach (range(1, 3) as $m) {
        ContinuityFundEntry::create([
            'kind' => FundEntryKind::Grant, 'amount_base' => 40, 'period' => now()->subMonths($m)->startOfMonth(),
            'sponsorship_id' => $s->id, 'orphan_id' => $s->orphan_id,
        ]);
    }

    $this->artisan('kafala:continuity-cover');

    $s->refresh();
    expect($s->status)->toBe(SponsorshipStatus::Cancelled)
        ->and($s->cancellation_reason)->toContain('3 أشهر')
        ->and($s->next_due_at)->toBeNull()
        ->and(ContinuityFundEntry::where('kind', FundEntryKind::Grant)->count())->toBe(3);
    Mail::assertSent(PaymentIssueMail::class, fn ($m) => $m->stage === 'ended' && $m->monthsLeft === 3);
});

it('دفعةٌ واحدة من الكافل تعيد عدّ أشهر التغطية', function () {
    $s = stuckSponsorship();
    ContinuityFundEntry::create(['kind' => FundEntryKind::Grant, 'amount_base' => 40, 'period' => now()->subMonths(2)->startOfMonth(), 'sponsorship_id' => $s->id, 'orphan_id' => $s->orphan_id]);
    $this->travel(1)->minutes();
    Payment::create([
        'reference_code' => 'PAY-X1', 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id, 'amount' => 40, 'currency' => 'USD',
        'fx_rate' => 1, 'amount_base' => 40, 'method' => PaymentMethod::Card, 'status' => PaymentStatus::Succeeded, 'paid_at' => now(),
        'idempotency_key' => Str::uuid()->toString(),
    ]);

    expect(app(ContinuityFund::class)->monthsCovered($s))->toBe(0);
});

it('رصيدٌ لا يكفي لا يُغطّي: تنتهي الكفالة ولا يدّعي البريد تغطيةً لم تكن', function () {
    Mail::fake();
    fundDonation(10);
    $s = stuckSponsorship();

    $this->artisan('kafala:continuity-cover');

    expect($s->fresh()->status)->toBe(SponsorshipStatus::Cancelled)
        ->and(ContinuityFundEntry::balance())->toBe(10.0);
    Mail::assertSent(PaymentIssueMail::class, fn ($m) => $m->stage === 'ended' && $m->monthsLeft === 0);
});

it('من أول رفض إلى نهاية الكفالة بالأيام: محاولات، ثم ثلاثة أشهر يغطّيها الصندوق، ثم النهاية', function () {
    Mail::fake();
    fundDonation(1000);
    $this->travelTo(now()->startOfMonth()->setDay(1)->setTime(3, 0));
    $s = stuckSponsorship(0, ['payment_issue_since' => null, 'payment_failures' => 0]);

    for ($day = 0; $day < 130 && $s->fresh()->status === SponsorshipStatus::Active; $day++) {
        $this->artisan('billing:run');
        $this->artisan('kafala:continuity-cover');
        $this->travel(1)->days();
    }

    expect($s->fresh()->status)->toBe(SponsorshipStatus::Cancelled)
        ->and(ContinuityFundEntry::where('kind', FundEntryKind::Grant)->where('sponsorship_id', $s->id)->count())->toBe(3)
        // كل شهر: 3 محاولات لا أكثر، وأربعة أشهر من الرفض
        ->and(Payment::where('sponsorship_id', $s->id)->where('status', PaymentStatus::Failed)->count())->toBe(12)
        ->and($day)->toBeLessThan(130);
    Mail::assertSent(PaymentIssueMail::class, fn ($m) => $m->stage === 'covered');
    Mail::assertSent(PaymentIssueMail::class, fn ($m) => $m->stage === 'ended');
});

it('من غطّى الصندوق شهر طفله يحدّث بطاقته فيدفع القسط القادم مقدّماً ويعود العدّ', function () {
    Mail::fake();
    fundDonation(500);
    $s = stuckSponsorship();
    $this->artisan('kafala:continuity-cover');

    $res = $this->actingAs($s->sponsor->user)->getJson('/api/v1/portal/sponsorships')->assertOk();
    expect($res->json('data.0.fund_cover'))->toBe(['months' => 1, 'max' => 3]);

    $this->actingAs($s->sponsor->user)->putJson("/api/v1/portal/sponsorships/{$s->id}/card", ['card_token' => 'tok_demo_ok', 'card_last4' => '4242'])
        ->assertOk()->assertJsonPath('data.fund_cover', null);

    expect(app(ContinuityFund::class)->monthsCovered($s->fresh()))->toBe(0)
        ->and($s->fresh()->gateway_payment_method)->not->toBe('pm_demo_decline');
});

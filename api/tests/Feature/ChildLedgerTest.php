<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransferStatus;
use App\Models\Association;
use App\Models\AssociationTransfer;
use App\Models\Disbursement;
use App\Models\DisbursementCategory;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\User;
use App\Services\TransferPreparer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * «أين ذهب مالك»: سجلّ الطفل كما يقرؤه كافله، كل رقمٍ فيه من صفوف المال.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-15 12:00'));

    $this->association = Association::factory()->create(['name_ar' => 'جمعية النور']);
    $this->child = Orphan::factory()->create(['family_id' => Family::factory()->state([
        'country_id' => $this->association->country_id, 'association_id' => $this->association->id,
    ])]);

    $this->me = User::factory()->create();
    $this->me->assignRole('sponsor');
    $mySponsor = Sponsor::factory()->create(['user_id' => $this->me->id]);

    $this->mine = Sponsorship::factory()->create(['orphan_id' => $this->child->id, 'sponsor_id' => $mySponsor->id, 'started_at' => '2026-08-01']);
    $this->theirs = Sponsorship::factory()->create(['orphan_id' => $this->child->id, 'started_at' => '2026-08-01']);

    $pay = fn (Sponsorship $s, float $amount, string $at) => Payment::create([
        'reference_code' => 'PAY-'.Str::upper(Str::random(8)), 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id,
        'amount' => $amount, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => $amount,
        'method' => PaymentMethod::Card, 'status' => PaymentStatus::Succeeded, 'paid_at' => Carbon::parse($at),
        'idempotency_key' => Str::uuid()->toString(),
    ]);
    $pay($this->mine, 60, '2026-09-02');
    $pay($this->theirs, 40, '2026-09-05');

    $spend = fn (string $slug, float $amount, ?string $receipt = null) => Disbursement::create([
        'orphan_id' => $this->child->id, 'category_id' => DisbursementCategory::where('slug', $slug)->value('id'),
        'amount' => $amount, 'currency' => 'USD', 'amount_base' => $amount, 'spent_on' => '2026-09-20',
        'receipt_path' => $receipt, 'approved_at' => now(),
    ]);
    $spend('education', 50, 'receipts/a.pdf');
    $spend('health', 20);
    Disbursement::create([   // غير معتمَد: لا يظهر
        'orphan_id' => $this->child->id, 'category_id' => DisbursementCategory::where('slug', 'living')->value('id'),
        'amount' => 999, 'currency' => 'USD', 'amount_base' => 999, 'spent_on' => '2026-09-21',
    ]);

    app(TransferPreparer::class)->prepare(Carbon::parse('2026-09-01'));
});

it('يرى الكافل ما وصل لطفله وحصته منه، والتشغيل، وما صُرف بنداً بنداً', function () {
    $d = $this->actingAs($this->me)->getJson("/api/v1/portal/sponsorships/{$this->mine->id}/ledger")->assertOk()->json('data');
    $sep = collect($d['months'])->firstWhere('period', '2026-09');

    expect($sep['received'])->toEqual(100)
        ->and($sep['mine'])->toEqual(60)
        ->and($sep['fee'])->toEqual(7)
        ->and($sep['spent_total'])->toEqual(70)
        ->and(collect($sep['spent'])->pluck('amount', 'slug')->all())->toEqual(['education' => 50, 'health' => 20])
        ->and($d['totals']['my_share_percent'])->toBe(60)
        ->and($d['totals']['receipts'])->toBe(1)
        ->and($d['association']['name'])->toBe('جمعية النور')
        ->and($d['from'])->toBe('2026-08');
});

it('لا يظهر التحويل للكافل قبل أن يُرسل، ثم يظهر بمرجعه ويحسب الرصيد', function () {
    $url = "/api/v1/portal/sponsorships/{$this->mine->id}/ledger";

    $draft = collect($this->actingAs($this->me)->getJson($url)->json('data.months'))->firstWhere('period', '2026-09');
    expect($draft['transfer'])->toBeNull();

    AssociationTransfer::query()->update(['status' => TransferStatus::Sent, 'sent_at' => '2026-10-03']);

    $d = $this->actingAs($this->me)->getJson($url)->json('data');
    $sep = collect($d['months'])->firstWhere('period', '2026-09');

    expect($sep['transfer']['amount'])->toEqual(93)
        ->and($sep['transfer']['status'])->toBe('sent')
        ->and($d['totals']['transferred'])->toEqual(93)
        ->and($d['totals']['balance'])->toEqual(23);         // 93 حُوِّل − 70 صُرف
});

it('لا يقرأ سجلّ طفلٍ عبر كفالة غيره', function () {
    $this->actingAs($this->me)->getJson("/api/v1/portal/sponsorships/{$this->theirs->id}/ledger")->assertForbidden();
});

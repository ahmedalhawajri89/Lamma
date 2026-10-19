<?php

declare(strict_types=1);

use App\Enums\FundEntryKind;
use App\Enums\PaymentMethod;
use App\Enums\SponsorshipStatus;
use App\Models\ContinuityFundEntry;
use App\Models\Sponsorship;
use App\Models\User;

/**
 * صندوق الاستمرارية في اللوحة: الرصيد والقيود، والمتعثّر الآن، والتبرّع.
 */
it('يرى المحاسب رصيد الصندوق وقيوده، ويسجّل تبرّعاً له', function () {
    $accountant = User::factory()->accountant()->create();
    ContinuityFundEntry::create(['kind' => FundEntryKind::Share, 'amount_base' => 50, 'period' => now()->startOfMonth()]);

    $this->actingAs($accountant)->postJson('/api/v1/admin/fund/donations', ['amount' => 200, 'received_on' => today()->toDateString(), 'note' => 'تبرّع شركة النور'])
        ->assertCreated()->assertJsonPath('data.balance', 250);

    $res = $this->actingAs($accountant)->getJson('/api/v1/admin/fund')->assertOk();
    expect($res->json('meta.summary.balance'))->toEqual(250)
        ->and($res->json('meta.summary.percent'))->toEqual(1)
        ->and($res->json('meta.kind_counts'))->toEqual(['share' => 1, 'donation' => 1])
        ->and($res->json('data.0.kind'))->toBe('donation')
        ->and($res->json('data.0.recorded_by'))->toBe($accountant->name);

    $this->actingAs($accountant)->getJson('/api/v1/admin/fund?kind=share')->assertOk()->assertJsonCount(1, 'data');
});

it('التبرّع يحتاج مصدره، ولا يُسجَّل بتاريخٍ قادم، ولا يسجّله من لا يعتمد الصرف', function () {
    $accountant = User::factory()->accountant()->create();

    $this->actingAs($accountant)->postJson('/api/v1/admin/fund/donations', ['amount' => 10, 'received_on' => today()->addDay()->toDateString()])
        ->assertUnprocessable()->assertJsonValidationErrors(['note', 'received_on']);

    $sponsor = \App\Models\Sponsor::factory()->create();
    $this->actingAs($sponsor->user)->postJson('/api/v1/admin/fund/donations', ['amount' => 10, 'received_on' => today()->toDateString(), 'note' => 'xxx'])
        ->assertForbidden();
    expect(ContinuityFundEntry::count())->toBe(0);
});

it('قائمة المتعثّر: ما تُعاد محاولاته، وما يغطّيه الصندوق، ومتى يغطّيه', function () {
    $accountant = User::factory()->accountant()->create();
    $failing = Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Active, 'next_due_at' => today()->subDays(4), 'payment_method' => PaymentMethod::Card->value,
        'payment_issue_since' => today()->subDays(4), 'payment_failures' => 2, 'next_retry_at' => today()->addDays(3),
    ]);
    $covered = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'next_due_at' => today()->addDays(10)]);
    ContinuityFundEntry::create(['kind' => FundEntryKind::Grant, 'amount_base' => 30, 'period' => now()->startOfMonth(), 'sponsorship_id' => $covered->id, 'orphan_id' => $covered->orphan_id]);
    Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);

    $rows = collect($this->actingAs($accountant)->getJson('/api/v1/admin/fund/at-risk')->assertOk()->json('data'))->keyBy('id');

    expect($rows)->toHaveCount(2)
        ->and($rows[$failing->id]['state'])->toBe('failing')
        ->and($rows[$failing->id]['covers_on'])->toBe(today()->subDays(4)->addDays(14)->toDateString())
        ->and($rows[$covered->id]['state'])->toBe('covered')
        ->and($rows[$covered->id]['months_covered'])->toBe(1);
});

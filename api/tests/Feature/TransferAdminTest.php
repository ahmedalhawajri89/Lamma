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
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * التحويلات في اللوحة: يجهّزها المحاسب، ويرسلها بمرجع الحوالة، ثم يؤكّد
 * وصولها. والمشرف الميداني لا يراها.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-10 12:00'));
    $a = Association::factory()->create(['name_ar' => 'جمعية الحوالة']);
    $child = Orphan::factory()->create(['family_id' => Family::factory()->state(['country_id' => $a->country_id, 'association_id' => $a->id])]);
    $s = Sponsorship::factory()->create(['orphan_id' => $child->id]);
    Payment::create([
        'reference_code' => 'PAY-'.Str::upper(Str::random(8)), 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id,
        'amount' => 100, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => 100,
        'method' => PaymentMethod::Card, 'status' => PaymentStatus::Succeeded, 'paid_at' => Carbon::parse('2026-09-12'),
        'idempotency_key' => Str::uuid()->toString(),
    ]);
    $this->accountant = User::factory()->accountant()->create();
});

it('يجهّز المحاسب تحويلات شهرٍ مكتمل، ولا يجهّز الشهر الجاري', function () {
    $this->actingAs($this->accountant)->postJson('/api/v1/admin/transfers/prepare', ['period' => '2026-10'])
        ->assertUnprocessable()->assertJsonValidationErrors('period');

    $this->actingAs($this->accountant)->postJson('/api/v1/admin/transfers/prepare', ['period' => '2026-09'])
        ->assertOk()->assertJsonPath('data.created', 1);

    $list = $this->actingAs($this->accountant)->getJson('/api/v1/admin/transfers')->assertOk();
    expect($list->json('data.0.amount'))->toEqual(93)
        ->and($list->json('data.0.association'))->toBe('جمعية الحوالة')
        ->and($list->json('meta.status_counts.draft'))->toBe(1);
});

it('يُرسل بمرجع الحوالة ثم يُؤكَّد وصوله، بالترتيب وحده', function () {
    $this->actingAs($this->accountant)->postJson('/api/v1/admin/transfers/prepare', ['period' => '2026-09']);
    $t = AssociationTransfer::sole();

    $this->actingAs($this->accountant)->postJson("/api/v1/admin/transfers/{$t->id}/confirm")->assertStatus(422);
    $this->actingAs($this->accountant)->postJson("/api/v1/admin/transfers/{$t->id}/send")->assertUnprocessable()->assertJsonValidationErrors('bank_reference');
    $this->actingAs($this->accountant)->postJson("/api/v1/admin/transfers/{$t->id}/send", ['bank_reference' => 'SWIFT-77'])->assertOk()->assertJsonPath('data.status', 'sent');
    $this->actingAs($this->accountant)->postJson("/api/v1/admin/transfers/{$t->id}/send", ['bank_reference' => 'SWIFT-78'])->assertStatus(422);
    $this->actingAs($this->accountant)->postJson("/api/v1/admin/transfers/{$t->id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');

    $fresh = $t->fresh();
    expect($fresh->status)->toBe(TransferStatus::Confirmed)
        ->and($fresh->bank_reference)->toBe('SWIFT-77')
        ->and($fresh->sent_by)->toBe($this->accountant->id);

    $lines = $this->actingAs($this->accountant)->getJson("/api/v1/admin/transfers/{$t->id}")->assertOk()->json('data.lines');
    expect($lines)->toHaveCount(1)->and($lines[0]['amount'])->toEqual(93);
});

it('لا يرى المشرف الميداني التحويلات', function () {
    $this->actingAs(User::factory()->supervisor()->create())->getJson('/api/v1/admin/transfers')->assertForbidden();
});

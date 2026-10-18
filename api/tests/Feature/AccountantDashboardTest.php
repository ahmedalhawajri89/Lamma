<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Models\User;

/**
 * لوحة المحاسب: مال الشهر وما يُتابَع منه، له وحده.
 */
function accPay(Sponsorship $s, string $key, PaymentStatus $status, float $amount, ?string $at = null): Payment
{
    $p = Payment::create([
        'reference_code' => 'PAY-AC'.$key, 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id,
        'amount' => $amount, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => $amount, 'method' => 'card',
        'status' => $status, 'paid_at' => $status === PaymentStatus::Succeeded ? ($at ?? now()) : null,
        'failure_reason' => $status === PaymentStatus::Failed ? 'رصيد غير كافٍ' : null, 'idempotency_key' => 'ac'.$key,
    ]);
    if ($at) {
        Payment::whereKey($p->id)->update(['created_at' => $at]);
    }

    return $p;
}

it('للمحاسب وحده: التحصيل من المتوقع، وما ينتظر استدراكاً', function () {
    $accountant = User::factory()->accountant()->create();
    $s = Sponsorship::factory()->create(['status' => 'active', 'next_due_at' => now()->addMonth()]);
    $other = Sponsorship::factory()->create(['status' => 'active', 'next_due_at' => now()->addMonth()]);

    accPay($s, '1', PaymentStatus::Succeeded, 40);
    accPay($other, '2', PaymentStatus::Failed, 25);
    // فشلت ثم استُدركت بدفعة بعدها: لا تُعدّ
    accPay($s, '3', PaymentStatus::Failed, 40, now()->subMinutes(30)->toDateTimeString());

    $f = $this->actingAs($accountant)->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.finance');

    expect($f['month']['collected'])->toEqual(40)
        ->and($f['month']['unrecovered'])->toEqual(25)
        ->and($f['to_recover_total']['count'])->toBe(1)
        ->and($f['to_recover'][0]['reason'])->toBe('رصيد غير كافٍ')
        ->and($f['upcoming_days'])->toHaveCount(30)
        ->and($f['overhead'])->toHaveKeys(['actual', 'target']);

    // المدير والمشرف: لوحتاهما كما هما
    $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/admin/dashboard')->assertJsonPath('data.finance', null);
    $this->actingAs(User::factory()->supervisor()->create())->getJson('/api/v1/admin/dashboard')->assertJsonPath('data.finance', null);
});

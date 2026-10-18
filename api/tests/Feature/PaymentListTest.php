<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Models\User;

/**
 * شاشة المدفوعات: ملخّص الفترة، و«فشلت ولم تُستدرك»، والتصدير، والبطاقة
 * لما دُفع ببطاقة وحده.
 */
function pay(Sponsorship $s, PaymentStatus $status, float $amount, string $when, string $method = 'card'): Payment
{
    static $i = 0;
    $i++;
    $at = now()->sub(\DateInterval::createFromDateString($when));

    $p = Payment::create([
        'reference_code' => 'PAY-95'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id,
        'amount' => $amount, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => $amount,
        'method' => $method, 'status' => $status, 'paid_at' => $status === PaymentStatus::Failed ? null : $at,
        'card_brand' => 'فيزا', 'card_last4' => '4242', 'idempotency_key' => 'pl'.$i,
    ]);
    Payment::whereKey($p->id)->update(['created_at' => $at]);

    return $p;
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('يلخّص الفترة، ويعرف ما فشل ولم يُستدرك', function (): void {
    $a = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);
    $b = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);

    pay($a, PaymentStatus::Failed, 30, '10 days');      // استُدركت بالتالية
    pay($a, PaymentStatus::Succeeded, 30, '5 days');
    pay($b, PaymentStatus::Failed, 20, '3 days');       // لم تُستدرك
    pay($b, PaymentStatus::Refunded, 20, '40 days');

    $meta = $this->getJson('/api/v1/admin/payments')->assertOk()->json('meta');

    expect($meta)
        ->settled_count->toBe(1)
        ->failed_count->toBe(2)
        ->refunded_count->toBe(1)
        ->unrecovered_count->toBe(1)
        ->success_rate->toEqual(50)
        ->and((float) $meta['settled_amount'])->toEqual(30.0);

    $res = $this->getJson('/api/v1/admin/payments?unrecovered=1')->json();
    expect($res['meta']['total'])->toBe(1)->and($res['data'][0]['sponsorship']['id'])->toBe($b->id);

    // الفترة بتاريخ المحاولة للفاشلة: آخر أسبوع فيه الفاشلة الثانية والناجحة
    expect($this->getJson('/api/v1/admin/payments?from='.now()->subDays(7)->toDateString())->json('meta.total'))->toBe(2);
});

it('البطاقة لا تظهر على تحويل بنكي', function (): void {
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);
    pay($s, PaymentStatus::Succeeded, 30, '1 day', 'bank_transfer');

    expect($this->getJson('/api/v1/admin/payments')->json('data.0.card'))->toBeNull();
});

it('يصدّر المدفوعات بفلاترها ويسجّل التصدير', function (): void {
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);
    pay($s, PaymentStatus::Succeeded, 30, '1 day');
    pay($s, PaymentStatus::Failed, 30, '2 days');

    $csv = $this->get('/api/v1/admin/payments/export?status=succeeded')->assertOk()->streamedContent();
    expect(substr_count($csv, "\n"))->toBe(2);   // الترويسة وسطر واحد
    $this->assertDatabaseHas('activity_log', ['description' => 'صدّر المدفوعات']);
});

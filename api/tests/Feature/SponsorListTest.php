<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Payment;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\User;

/**
 * شاشة الكفلاء: كلٌّ في مرحلة واحدة، والالتزام الشهري، ومن تأخّر قسطه.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('يقسم الكفلاء بمراحلهم، كلٌّ في واحدة', function (): void {
    $active = Sponsor::factory()->create();
    Sponsorship::factory()->for($active)->create(['status' => SponsorshipStatus::Active, 'monthly_amount' => 40]);
    // نشطة وموقوفة معاً: يُعدّ نشطاً لا الاثنين
    Sponsorship::factory()->for($active)->create(['status' => SponsorshipStatus::Paused, 'monthly_amount' => 20]);

    $paused = Sponsor::factory()->create();
    Sponsorship::factory()->for($paused)->create(['status' => SponsorshipStatus::Paused]);

    $pending = Sponsor::factory()->create();
    Sponsorship::factory()->for($pending)->create(['status' => SponsorshipStatus::Pending]);

    $past = Sponsor::factory()->create();
    Sponsorship::factory()->for($past)->create(['status' => SponsorshipStatus::Cancelled]);

    Sponsor::factory()->create();   // لم يكفل بعد

    $meta = $this->getJson('/api/v1/admin/sponsors?segment=past')->assertOk()->json('meta');

    expect($meta['total'])->toBe(1)
        ->and($meta['segment_counts'])->toMatchArray(['active' => 1, 'paused' => 1, 'pending' => 1, 'past' => 1, 'never' => 1])
        ->and($meta['all_count'])->toBe(5)
        ->and((float) $meta['monthly_commitment'])->toEqual(40.0);
});

it('يعرض الالتزام وآخر دفعة والتأخر لكل كافل، ويرتّب بما قدّمه', function (): void {
    $late = Sponsor::factory()->create();
    $s = Sponsorship::factory()->for($late)->create(['status' => SponsorshipStatus::Active, 'monthly_amount' => 30, 'next_due_at' => now()->subDays(5)]);
    Payment::create([
        'reference_code' => 'PAY-910001', 'sponsorship_id' => $s->id, 'sponsor_id' => $late->id,
        'amount' => 300, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => 300,
        'method' => 'card', 'status' => PaymentStatus::Succeeded, 'paid_at' => now()->subMonth(), 'idempotency_key' => 'sl1',
    ]);
    Sponsor::factory()->create();

    $res = $this->getJson('/api/v1/admin/sponsors?sort=total_given&dir=desc')->assertOk();
    expect($res->json('data.0'))
        ->id->toBe($late->id)
        ->monthly_commitment->toEqual(30)
        ->overdue_sponsorships_count->toBe(1)
        ->last_payment_at->not->toBeNull()
        ->and($res->json('meta.overdue_count'))->toBe(1);

    expect($this->getJson('/api/v1/admin/sponsors?overdue=1')->json('meta.total'))->toBe(1);
});

it('صفحة الكافل: آخر دفعاته بحالاتها، والتزامه، وعدد دفعاته المكتملة', function (): void {
    $sponsor = Sponsor::factory()->create();
    $s = Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Active, 'monthly_amount' => 25]);
    foreach ([PaymentStatus::Succeeded, PaymentStatus::Failed] as $i => $status) {
        Payment::create([
            'reference_code' => 'PAY-92000'.$i, 'sponsorship_id' => $s->id, 'sponsor_id' => $sponsor->id,
            'amount' => 25, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => 25,
            'method' => 'card', 'status' => $status, 'paid_at' => $status === PaymentStatus::Succeeded ? now() : null, 'idempotency_key' => 'sp'.$i,
        ]);
    }

    $data = $this->getJson("/api/v1/admin/sponsors/{$sponsor->id}")->assertOk()->json('data');
    expect($data['recent_payments'])->toHaveCount(2)
        ->and($data['payments_count'])->toBe(1)
        ->and((float) $data['monthly_commitment'])->toEqual(25.0);
});

it('رمز الكافل يجد كفالاته ودفعاته في شاشتيهما', function (): void {
    $sponsor = Sponsor::factory()->create();
    $s = Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Active]);
    Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);
    Payment::create([
        'reference_code' => 'PAY-930001', 'sponsorship_id' => $s->id, 'sponsor_id' => $sponsor->id,
        'amount' => 10, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => 10,
        'method' => 'card', 'status' => PaymentStatus::Succeeded, 'paid_at' => now(), 'idempotency_key' => 'sc1',
    ]);

    expect($this->getJson('/api/v1/admin/sponsorships?q='.$sponsor->sponsor_code)->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/v1/admin/payments?q='.$sponsor->sponsor_code)->json('meta.total'))->toBe(1);
});

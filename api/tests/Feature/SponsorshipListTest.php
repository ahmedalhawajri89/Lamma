<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Models\User;

/**
 * شاشة الكفالات: أعداد الحالات، و«منتهية» تجمع الملغاة والمكتملة، وما يُنتظر
 * تحصيله هذا الأسبوع بالقسط الكامل، وتفاصيل الكفالة بدفعاتها.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('يعدّ الحالات، و«منتهية» تجمع الملغاة والمكتملة بلا كسر بقية الفلاتر', function (): void {
    Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'is_zakat' => true]);
    Sponsorship::factory()->create(['status' => SponsorshipStatus::Cancelled, 'is_zakat' => true]);
    Sponsorship::factory()->create(['status' => SponsorshipStatus::Completed, 'is_zakat' => false]);

    $meta = $this->getJson('/api/v1/admin/sponsorships?status=ended')->assertOk()->json('meta');
    expect($meta['total'])->toBe(2)
        ->and($meta['status_counts'])->toMatchArray(['active' => 1, 'ended' => 2]);

    // «منتهية» مع «من الزكاة»: الشرطان معاً لا أحدهما
    expect($this->getJson('/api/v1/admin/sponsorships?status=ended&zakat_only=1')->json('meta.total'))->toBe(1);
});

it('ما يُنتظر هذا الأسبوع بالقسط الكامل، والمتأخرة تُعدّ', function (): void {
    Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'monthly_amount' => 20, 'interval_months' => 3, 'next_due_at' => now()->addDays(2)]);
    Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'monthly_amount' => 30, 'interval_months' => 1, 'next_due_at' => now()->subDays(3)]);

    $meta = $this->getJson('/api/v1/admin/sponsorships')->json('meta');
    expect($meta['due_week_count'])->toBe(1)
        ->and((float) $meta['due_week_amount'])->toEqual(60.0)
        ->and($meta['overdue_count'])->toBe(1)
        ->and((float) $meta['monthly_commitment'])->toEqual(50.0);
});

it('تفاصيل الكفالة تحمل دفعاتها', function (): void {
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);
    Payment::create([
        'reference_code' => 'PAY-940001', 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id,
        'amount' => 30, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => 30,
        'method' => 'card', 'status' => PaymentStatus::Succeeded, 'paid_at' => now(), 'idempotency_key' => 'shd1',
    ]);

    expect($this->getJson("/api/v1/admin/sponsorships/{$s->id}")->assertOk()->json('data.payments'))->toHaveCount(1);
});

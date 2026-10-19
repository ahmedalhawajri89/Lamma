<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Mail\PaymentIssueMail;
use App\Models\Payment;
use App\Models\Sponsorship;
use Illuminate\Support\Facades\Mail;

/**
 * القسط المرفوض: يُعاد في مواعيد لا كل يوم، ويُبلَّغ الكافل مرة واحدة،
 * ويدفعه ببطاقة جديدة من بوابته فينتهي التعثّر.
 */
function decliningSponsorship(array $extra = []): Sponsorship
{
    return Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Active,
        'monthly_amount' => 30,
        'billing_day' => 1,
        'next_due_at' => now()->subDay(),
        'payment_method' => PaymentMethod::Card->value,
        'gateway_payment_method' => 'pm_demo_decline',
        'card_brand' => 'فيزا',
        'card_last4' => '0002',
        ...$extra,
    ]);
}

it('الرفض الأول يبدأ التعثّر ويحدّد المحاولة بعد 3 أيام، ويُبلَّغ الكافل مرة واحدة', function () {
    Mail::fake();
    $s = decliningSponsorship();

    $this->artisan('billing:run')->assertFailed();

    $s->refresh();
    expect($s->payment_issue_since->isToday())->toBeTrue()
        ->and($s->payment_failures)->toBe(1)
        ->and($s->next_retry_at->toDateString())->toBe(today()->addDays(3)->toDateString());
    Mail::assertSent(PaymentIssueMail::class, fn ($m) => $m->hasTo($s->sponsor->user->email));

    // غداً: ليس موعد المحاولة، فلا خصم ولا بريد
    $this->travel(1)->days();
    $this->artisan('billing:run')->assertSuccessful();
    expect(Payment::where('sponsorship_id', $s->id)->count())->toBe(1);

    // اليوم الثالث: المحاولة الثانية، ثم السابع: الأخيرة، والبريد لم يتكرّر
    $this->travel(2)->days();
    $this->artisan('billing:run')->assertFailed();
    $this->travel(4)->days();
    $this->artisan('billing:run')->assertFailed();

    $s->refresh();
    expect($s->payment_failures)->toBe(3)
        ->and($s->next_retry_at)->toBeNull()
        ->and(Payment::where('sponsorship_id', $s->id)->where('status', PaymentStatus::Failed)->count())->toBe(3);
    Mail::assertSentCount(1);

    // استنفد محاولاته: ينتظر بطاقة جديدة، لا يُخصم كل يوم
    $this->travel(1)->days();
    $this->artisan('billing:run')->assertSuccessful();
    expect(Payment::where('sponsorship_id', $s->id)->count())->toBe(3);
});

it('الكافل يرى التعثّر في بوابته: منذ متى، والمحاولة التالية، وسبب الرفض', function () {
    Mail::fake();
    $s = decliningSponsorship();
    $this->artisan('billing:run');

    $issue = $this->actingAs($s->sponsor->user)->getJson('/api/v1/portal/sponsorships')
        ->assertOk()->json('data.0.payment_issue');

    expect($issue['failures'])->toBe(1)
        ->and($issue['retries_left'])->toBe(2)
        ->and($issue['card_last4'])->toBe('0002')
        ->and($issue['reason'])->toContain('رفض البنك');
});

it('بطاقة جديدة من البوابة تدفع القسط فوراً وتُحفظ وتُنهي التعثّر', function () {
    Mail::fake();
    $s = decliningSponsorship();
    $this->artisan('billing:run');
    $due = $s->fresh()->next_due_at;

    $this->actingAs($s->sponsor->user)->putJson("/api/v1/portal/sponsorships/{$s->id}/card", [
        'card_token' => 'tok_demo_ok', 'card_brand' => 'فيزا', 'card_last4' => '4242',
    ])->assertOk()->assertJsonMissingPath('data.payment_issue');

    $s->refresh();
    expect($s->hasPaymentIssue())->toBeFalse()
        ->and($s->payment_failures)->toBe(0)
        ->and($s->card_last4)->toBe('4242')
        ->and($s->gateway_payment_method)->toStartWith('pm_demo_')->not->toBe('pm_demo_decline')
        ->and($s->next_due_at->gt($due))->toBeTrue()
        ->and(Payment::where('sponsorship_id', $s->id)->where('status', PaymentStatus::Succeeded)->count())->toBe(1);

    // الشهر القادم يُخصم بالبطاقة الجديدة
    $this->travelTo($s->next_due_at->copy()->addHour());
    $this->artisan('billing:run')->assertSuccessful();
    expect(Payment::where('sponsorship_id', $s->id)->where('status', PaymentStatus::Succeeded)->count())->toBe(2);
});

it('بطاقة مرفوضة من البوابة تُرجع سبب البنك ولا تغيّر شيئاً', function () {
    Mail::fake();
    $s = decliningSponsorship();
    $this->artisan('billing:run');

    $this->actingAs($s->sponsor->user)->putJson("/api/v1/portal/sponsorships/{$s->id}/card", ['card_token' => 'tok_demo_decline'])
        ->assertUnprocessable()->assertJsonValidationErrors('card_token');

    expect($s->fresh()->hasPaymentIssue())->toBeTrue()
        ->and($s->fresh()->gateway_payment_method)->toBe('pm_demo_decline');
});

it('لا يدفع أحدٌ بطاقته على كفالة غيره، ولا على كفالة بلا قسط متأخر', function () {
    $s = decliningSponsorship();
    $other = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'next_due_at' => now()->addWeek()]);

    $this->actingAs($other->sponsor->user)->putJson("/api/v1/portal/sponsorships/{$s->id}/card", ['card_token' => 'tok_demo_ok'])
        ->assertForbidden();
    $this->actingAs($other->sponsor->user)->putJson("/api/v1/portal/sponsorships/{$other->id}/card", ['card_token' => 'tok_demo_ok'])
        ->assertStatus(409);

    expect(Payment::count())->toBe(0);
});

it('دفعةٌ يسجّلها المحاسب تُنهي التعثّر أيضاً', function () {
    Mail::fake();
    $s = decliningSponsorship();
    $this->artisan('billing:run');

    app(\App\Actions\Payments\RecordPayment::class)->handle($s->fresh(), 'manual-'.$s->id, method: PaymentMethod::BankTransfer);

    expect($s->fresh()->hasPaymentIssue())->toBeFalse();
});

it('قسط الشهر الماضي المتعثّر إن حُصّل هذا الشهر لا يحجب قسط هذا الشهر', function () {
    Mail::fake();
    $this->travelTo(now()->startOfMonth()->addDays(9));
    // استحقّ قبل شهر ولم يُدفع: قسطان مستحقان، كلٌّ بمفتاح شهره
    $s = decliningSponsorship([
        'gateway_payment_method' => 'pm_demo_saved',
        'billing_day' => 5,
        'next_due_at' => now()->subMonthNoOverflow()->setDay(5),
    ]);

    $this->artisan('billing:run')->assertSuccessful();
    expect($s->fresh()->next_due_at->isSameMonth(now()))->toBeTrue();

    $this->artisan('billing:run')->assertSuccessful();
    expect(Payment::where('sponsorship_id', $s->id)->where('status', PaymentStatus::Succeeded)->count())->toBe(2)
        ->and($s->fresh()->next_due_at->isFuture())->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Models\User;

/**
 * المال الذي يتحرّك بلا الكافل: الخصم الشهري، والاسترداد، ونهاية الكفالة
 * مع نهاية ملف الطفل. كل واحدٍ منها كان يسجّل مالاً لم يتحرّك أو يُبقي
 * خصماً لا سبب له.
 */
function dueCardSponsorship(array $extra = []): Sponsorship
{
    return Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Active,
        'monthly_amount' => 30,
        'billing_day' => 1,
        'next_due_at' => now()->subDay(),
        'payment_method' => PaymentMethod::Card->value,
        'gateway_payment_method' => 'pm_demo_saved',
        'card_brand' => 'فيزا',
        'card_last4' => '4242',
        ...$extra,
    ]);
}

/* ------------------------------------------------------------ الخصم الشهري */

it('الخصم الشهري يمرّ بالبوابة بالبطاقة المحفوظة، ويحمل مرجعها وآخر أرقامها', function () {
    $s = dueCardSponsorship();

    $this->artisan('billing:run')->assertSuccessful();

    $payment = Payment::where('sponsorship_id', $s->id)->sole();
    expect($payment)
        ->status->toBe(PaymentStatus::Succeeded)
        ->gateway_reference->toStartWith('demo_ch_')
        ->card_last4->toBe('4242')
        ->receipt_number->not->toBeNull()
        ->and($s->fresh()->next_due_at->isFuture())->toBeTrue();
});

it('كفالة ببطاقة لا محفوظة لها لا تُسجَّل مدفوعة: محاولة فاشلة بسببها للمحاسب', function () {
    $s = dueCardSponsorship(['gateway_payment_method' => null]);
    $due = $s->next_due_at->toDateString();

    $this->artisan('billing:run')->assertFailed();

    expect(Payment::where('sponsorship_id', $s->id)->where('status', PaymentStatus::Succeeded)->count())->toBe(0)
        ->and(Payment::where('sponsorship_id', $s->id)->where('status', PaymentStatus::Failed)->value('failure_reason'))->toContain('لا بطاقة محفوظة')
        // القسط باقٍ مستحقاً: لم يُدفع
        ->and($s->fresh()->next_due_at->toDateString())->toBe($due);
});

it('رفض البنك للقسط يُحفظ بسببه، ولا إيصال ولا تقدّم في الموعد', function () {
    $s = dueCardSponsorship(['gateway_payment_method' => 'pm_demo_decline']);
    $due = $s->next_due_at->toDateString();

    $this->artisan('billing:run')->assertFailed();

    $failed = Payment::where('sponsorship_id', $s->id)->sole();
    expect($failed)
        ->status->toBe(PaymentStatus::Failed)
        ->receipt_number->toBeNull()
        ->failure_reason->toContain('رفض البنك')
        ->and($s->fresh()->next_due_at->toDateString())->toBe($due);
});

it('تشغيل الأمر مرتين في اليوم لا يخصم مرتين', function () {
    $s = dueCardSponsorship();

    $this->artisan('billing:run')->assertSuccessful();
    // الموعد عاد مستحقاً (كأن أحداً أعاده يدوياً): المفتاح الشهري يمنع خصماً ثانياً
    $s->forceFill(['next_due_at' => now()->subDay()])->save();
    $this->artisan('billing:run')->assertSuccessful();

    expect(Payment::where('sponsorship_id', $s->id)->where('status', PaymentStatus::Succeeded)->count())->toBe(1);
});

it('مرجع البطاقة المحفوظة لا يظهر في ردود الـ API', function () {
    $s = dueCardSponsorship();

    expect($s->toArray())->not->toHaveKey('gateway_payment_method');
});

/* ------------------------------------------------------------ الاسترداد */

it('استرداد دفعة سنوية يعيد الموعد اثني عشر شهراً لا شهراً واحداً', function () {
    $admin = User::factory()->admin()->create();
    $s = Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Active, 'monthly_amount' => 30, 'interval_months' => 12, 'billing_day' => 10,
        'next_due_at' => now()->addDays(3),
    ]);
    $before = $s->next_due_at->toDateString();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/payments', [
        'sponsorship_id' => $s->id, 'method' => 'cash', 'reference' => 'Y-1', 'amount' => 360,
    ])->assertCreated()->json('data.id');
    expect(Payment::find($id)->amount)->toEqual(360);

    $this->actingAs($admin)->postJson("/api/v1/admin/payments/{$id}/refund", ['reason' => 'دُفعت بالخطأ'])->assertOk();

    // يعود إلى موعده قبل الدفعة، على يوم الفوترة
    expect($s->fresh()->next_due_at->toDateString())
        ->toBe(\Illuminate\Support\Carbon::parse($before)->startOfMonth()->setDay(10)->toDateString());
});

/* ------------------------------------------------------------ نهاية الملف */

it('تخرّج الطفل يُنهي كفالاته ويوقف خصمها، ويُلغي حجزه غير المدفوع', function () {
    $admin = User::factory()->admin()->create();
    $orphan = Orphan::factory()->create(['status' => OrphanStatus::Active]);
    $active = Sponsorship::factory()->create(['orphan_id' => $orphan->id, 'status' => SponsorshipStatus::Active, 'share_percent' => 50]);
    $paused = Sponsorship::factory()->create(['orphan_id' => $orphan->id, 'status' => SponsorshipStatus::Paused, 'share_percent' => 25]);
    $hold = Sponsorship::factory()->create(['orphan_id' => $orphan->id, 'status' => SponsorshipStatus::Pending, 'share_percent' => 25]);

    $this->actingAs($admin)->postJson("/api/v1/admin/orphans/{$orphan->id}/status", [
        'status' => 'graduated', 'reason' => 'أنهى دراسته الثانوية',
    ])->assertOk()->assertJsonPath('sponsorships_ended', 3);

    foreach ([$active, $paused] as $s) {
        expect($s->fresh())
            ->status->toBe(SponsorshipStatus::Completed)
            ->next_due_at->toBeNull()
            ->cancellation_reason->toBe('تخرّج الطفل');
    }
    expect($hold->fresh()->status)->toBe(SponsorshipStatus::Cancelled)
        ->and($orphan->fresh()->status)->toBe(OrphanStatus::Graduated);

    // ولا يجد أمر الفوترة شيئاً يخصمه لهذا الطفل
    $this->artisan('billing:run')->assertSuccessful();
    expect(Payment::whereIn('sponsorship_id', [$active->id, $paused->id, $hold->id])->count())->toBe(0);
});

/* ------------------------------------------------------------ صفحة الكفالة */

it('الكفالة بالبطاقة تحفظ مرجع البطاقة للأقساط القادمة', function () {
    $orphan = Orphan::factory()->create([
        'status' => OrphanStatus::Active, 'monthly_need' => 90, 'published_at' => now(), 'funded_percent' => 0,
    ]);

    $ref = $this->postJson('/api/v1/public/checkout', [
        'orphan_reference' => $orphan->reference_code, 'monthly_amount' => 30, 'interval_months' => 1,
        'method' => 'card', 'name' => 'متبرع', 'email' => 'saved@example.com',
        'card_token' => 'tok_demo_ok', 'card_brand' => 'فيزا', 'card_last4' => '4242',
    ])->assertCreated()->json('data.reference');

    expect(Sponsorship::where('reference_code', $ref)->first())
        ->gateway_payment_method->toStartWith('pm_demo_')
        ->payment_method->toBe('card')
        ->card_last4->toBe('4242');
});

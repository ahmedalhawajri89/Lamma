<?php

declare(strict_types=1);

use App\Enums\MessageStatus;
use App\Enums\OrphanStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReportStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Message;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * مسار المتبرع: الكفالة من الموقع، وبوابة الكافل.
 * كانت صفحة الكفالة محاكاة لا تُنشئ شيئاً، والبوابة تفتح تقارير الطفل لحجزٍ
 * غير مدفوع وتحسب الدفعات الفاشلة فيما «قدّمه» الكافل.
 */
function sponsorableOrphan(float $need = 90): Orphan
{
    return Orphan::factory()->create([
        'status' => OrphanStatus::Active, 'monthly_need' => $need, 'published_at' => now(), 'funded_percent' => 0,
    ]);
}

/* ------------------------------------------------------------ الكفالة من الموقع */

it('الزائر يكفل بالبطاقة: يُنشأ حسابه ويدخل، وتُدفع الدورة كاملة، وتتقدّم بدورتها', function () {
    $orphan = sponsorableOrphan();

    $res = $this->postJson('/api/v1/public/checkout', [
        'orphan_reference' => $orphan->reference_code, 'monthly_amount' => 30, 'interval_months' => 3,
        'method' => 'card', 'name' => 'متبرع جديد', 'email' => 'donor@example.com',
        'card_token' => 'tok_demo_ok', 'card_brand' => 'فيزا', 'card_last4' => '4242',
    ])->assertCreated();

    expect($res->json('data'))
        ->status->toBe('active')
        ->cycle_amount->toEqual(90)
        ->account_created->toBeTrue()
        ->demo->toBeTrue()
        ->receipt_number->not->toBeNull();

    $sponsorship = Sponsorship::where('reference_code', $res->json('data.reference'))->first();
    expect($sponsorship->interval_months)->toBe(3)
        ->and(Payment::where('sponsorship_id', $sponsorship->id)->value('amount'))->toEqual(90)
        // ثلاثة أقساط تغطي ثلاثة أشهر، من اليوم
        ->and($sponsorship->next_due_at->toDateString())->toBe(now()->startOfMonth()->addMonthsNoOverflow(3)->setDay(min(now()->day, 28))->toDateString());

    $this->assertAuthenticated();
    expect(User::where('email', 'donor@example.com')->first()->hasRole('sponsor'))->toBeTrue();
});

it('رفض البطاقة يلغي الحجز فوراً فلا يُمسك الطفل بلا دفع', function () {
    $orphan = sponsorableOrphan();

    $this->postJson('/api/v1/public/checkout', [
        'orphan_reference' => $orphan->reference_code, 'interval_months' => 1, 'method' => 'card',
        'name' => 'متبرع', 'email' => 'declined@example.com', 'card_token' => 'tok_demo_decline',
    ])->assertStatus(402)->assertJsonPath('code', 'payment_failed');

    expect($orphan->sponsorships()->live()->count())->toBe(0)
        ->and(Payment::where('status', PaymentStatus::Failed)->count())->toBe(1);
});

it('التحويل البنكي حجزٌ بانتظار التحويل مع تعليماته ومهلته', function () {
    $orphan = sponsorableOrphan();

    $res = $this->postJson('/api/v1/public/checkout', [
        'orphan_reference' => $orphan->reference_code, 'interval_months' => 12, 'method' => 'bank_transfer',
        'name' => 'متبرع', 'email' => 'bank@example.com',
    ])->assertCreated();

    expect($res->json('data'))
        ->status->toBe('pending')
        ->bank->toHaveKey('iban')
        ->hold_expires_at->not->toBeNull()
        ->receipt_number->toBeNull();
});

it('البريد المسجَّل يُطلب منه الدخول، ولا تُنسب كفالة إلى حساب غيره', function () {
    Sponsor::factory()->create()->user->update(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/public/checkout', [
        'orphan_reference' => sponsorableOrphan()->reference_code, 'interval_months' => 1, 'method' => 'bank_transfer',
        'name' => 'منتحل', 'email' => 'taken@example.com',
    ])->assertStatus(409)->assertJsonPath('code', 'login_required');
});

it('المبلغ الأكبر من المتبقي يُرفض بوضوح، والحجز لا يتكرر ولا يتجاوز الحد', function () {
    $sponsor = Sponsor::factory()->create();
    $orphan = sponsorableOrphan(50);

    $this->actingAs($sponsor->user)->postJson('/api/v1/public/checkout', [
        'orphan_reference' => $orphan->reference_code, 'monthly_amount' => 80, 'interval_months' => 1, 'method' => 'bank_transfer',
    ])->assertUnprocessable()->assertJsonValidationErrors('monthly_amount');

    $hold = fn (Orphan $o) => $this->actingAs($sponsor->user)->postJson('/api/v1/public/checkout', [
        'orphan_reference' => $o->reference_code, 'interval_months' => 1, 'method' => 'bank_transfer',
    ]);

    $hold($orphan)->assertCreated();
    $hold($orphan)->assertUnprocessable();   // الطفل نفسه مرتين
    $hold(sponsorableOrphan())->assertCreated();
    $hold(sponsorableOrphan())->assertCreated();
    $hold(sponsorableOrphan())->assertUnprocessable(); // الحجز الرابع غير المدفوع
});

/* ------------------------------------------------------------ بوابة الكافل */

it('الحجز غير المدفوع لا يفتح تقارير الطفل', function () {
    $sponsor = Sponsor::factory()->create();
    $orphan = sponsorableOrphan();
    Sponsorship::factory()->for($orphan)->for($sponsor)->create(['status' => SponsorshipStatus::Pending]);
    $report = Report::create([
        'orphan_id' => $orphan->id, 'period_year' => 2026, 'period_quarter' => 2, 'title' => 'تقرير',
        'status' => ReportStatus::Published, 'published_at' => now(),
    ]);

    $this->actingAs($sponsor->user)->getJson("/api/v1/portal/reports/{$report->id}")->assertForbidden();
    expect($this->actingAs($sponsor->user)->getJson('/api/v1/portal/reports')->json('meta.total'))->toBe(0);
});

it('«ما قدّمته له» يحسب الدفعات المكتملة وحدها', function () {
    $sponsor = Sponsor::factory()->create();
    $s = Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Active]);

    foreach ([[PaymentStatus::Succeeded, 30], [PaymentStatus::Failed, 30], [PaymentStatus::Refunded, 30]] as $i => [$status, $amount]) {
        Payment::create([
            'reference_code' => 'PAY-80000'.$i, 'sponsorship_id' => $s->id, 'sponsor_id' => $sponsor->id,
            'amount' => $amount, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => $amount,
            'method' => 'card', 'status' => $status, 'paid_at' => now(), 'idempotency_key' => 'k'.$i,
        ]);
    }

    $row = collect($this->actingAs($sponsor->user)->getJson('/api/v1/portal/overview')->json('data.sponsorships'))->first();
    expect($row['total_paid'])->toEqual(30);
});

it('رسالة الطفل غير المراجَعة لا تصل الكافل، والكافل السابق لا يراسل', function () {
    $sponsor = Sponsor::factory()->create();
    $s = Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Active]);

    $pending = Message::create(['sponsorship_id' => $s->id, 'sender_type' => 'orphan', 'body' => 'عنواننا شارع 5']);
    $approved = Message::create(['sponsorship_id' => $s->id, 'sender_type' => 'orphan', 'body' => 'شكراً لك']);
    $approved->forceFill(['status' => MessageStatus::Approved])->save();

    $ids = collect($this->actingAs($sponsor->user)->getJson("/api/v1/portal/sponsorships/{$s->id}/messages")->json('data'))->pluck('id');
    expect($ids)->toContain($approved->id)->not->toContain($pending->id);

    $s->update(['status' => SponsorshipStatus::Cancelled]);
    $this->actingAs($sponsor->user)->postJson("/api/v1/portal/sponsorships/{$s->id}/messages", ['body' => 'مرحبا'])->assertForbidden();
});

it('تغيير البريد يشترط كلمة المرور، وتغيير الكلمة يُخرج الأجهزة الأخرى', function () {
    $user = Sponsor::factory()->create()->user;
    $user->update(['password' => Hash::make('Old-pass-123')]);

    $this->actingAs($user)->patchJson('/api/v1/portal/profile', ['email' => 'new@example.com'])
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->actingAs($user)->patchJson('/api/v1/portal/profile', ['email' => 'new@example.com', 'current_password' => 'Old-pass-123'])
        ->assertOk();

    \Illuminate\Support\Facades\DB::table('sessions')->insert([
        'id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
    ]);
    $before = $user->fresh()->remember_token;

    $this->actingAs($user)->putJson('/api/v1/portal/profile/password', [
        'current_password' => 'Old-pass-123', 'password' => 'New-pass-456x', 'password_confirmation' => 'New-pass-456x',
    ])->assertOk();

    expect(\Illuminate\Support\Facades\DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse()
        ->and($user->fresh()->remember_token)->not->toBe($before);
});

it('سبب الإلغاء الطويل يُرفض برسالة واضحة لا بخطأ خادم', function () {
    $sponsor = Sponsor::factory()->create();
    $s = Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Active]);

    $this->actingAs($sponsor->user)->deleteJson("/api/v1/portal/sponsorships/{$s->id}", ['reason' => str_repeat('س', 300)])
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
});

it('الكافل يوقف كفالته مؤقتاً ويستأنفها، والاستئناف لا يُخصم على موعد فات', function () {
    $sponsor = Sponsor::factory()->create();
    $s = Sponsorship::factory()->for($sponsor)->create([
        'status' => SponsorshipStatus::Active, 'billing_day' => 1, 'next_due_at' => now()->subMonths(2),
    ]);

    $this->actingAs($sponsor->user)->patchJson("/api/v1/portal/sponsorships/{$s->id}", ['status' => 'paused'])->assertOk();
    expect($s->fresh())->status->toBe(SponsorshipStatus::Paused)->paused_at->not->toBeNull();

    $this->actingAs($sponsor->user)->patchJson("/api/v1/portal/sponsorships/{$s->id}", ['status' => 'active'])->assertOk();
    expect($s->fresh())->status->toBe(SponsorshipStatus::Active)
        ->and($s->fresh()->next_due_at->isFuture())->toBeTrue();

    // استئنافٌ مكرر لا يغيّر شيئاً
    $this->actingAs($sponsor->user)->patchJson("/api/v1/portal/sponsorships/{$s->id}", ['status' => 'active'])->assertStatus(409);
});

it('الحجز لا يُفعَّل بالإيقاف والاستئناف، وكافلٌ آخر لا يلمس كفالة غيره', function () {
    $sponsor = Sponsor::factory()->create();
    $pending = Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Pending]);
    $this->actingAs($sponsor->user)->patchJson("/api/v1/portal/sponsorships/{$pending->id}", ['status' => 'active'])->assertStatus(409);
    $this->actingAs($sponsor->user)->patchJson("/api/v1/portal/sponsorships/{$pending->id}", ['status' => 'paused'])->assertStatus(409);
    expect($pending->fresh()->status)->toBe(SponsorshipStatus::Pending);

    $other = Sponsor::factory()->create();
    $mine = Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Active]);
    $this->actingAs($other->user)->patchJson("/api/v1/portal/sponsorships/{$mine->id}", ['status' => 'paused'])->assertForbidden();
});

it('لوحة الكافل: الحجز لا يُعدّ يتيماً مكفولاً، ويُعرف موعد سقوطه، والمنتهية تبقى في تاريخه', function () {
    $sponsor = Sponsor::factory()->create();
    Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Pending]);
    $ended = Sponsorship::factory()->for($sponsor)->create(['status' => SponsorshipStatus::Cancelled, 'ended_at' => now()]);
    Payment::create([
        'reference_code' => 'PAY-810001', 'sponsorship_id' => $ended->id, 'sponsor_id' => $sponsor->id,
        'amount' => 30, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => 30,
        'method' => 'card', 'status' => PaymentStatus::Succeeded, 'paid_at' => now(), 'idempotency_key' => 'past1',
    ]);

    $data = $this->actingAs($sponsor->user)->getJson('/api/v1/portal/overview')->json('data');
    expect($data['stats']['orphans_sponsored'])->toBe(0)
        ->and($data['stats']['pending_count'])->toBe(1)
        ->and($data['sponsorships'][0]['hold_expires_at'])->not->toBeNull()
        ->and($data['past_sponsorships'])->toHaveCount(1)
        ->and($data['past_sponsorships'][0]['total_paid'])->toEqual(30);
});

it('كفالةٌ مرفوضة لا تترك حساباً ولا جلسة للزائر', function () {
    // اكتمل تمويله: CreateSponsorship يرفض، والحساب كان يُنشأ قبلها ويدخل صاحبه
    $orphan = sponsorableOrphan(50);
    Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 50]);

    $this->postJson('/api/v1/public/checkout', [
        'orphan_reference' => $orphan->reference_code, 'interval_months' => 1,
        'method' => 'card', 'name' => 'زائر جديد', 'email' => 'late@example.com', 'card_token' => 'tok_demo_ok',
    ])->assertUnprocessable()->assertJsonValidationErrors('orphan_reference');

    expect(User::where('email', 'late@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

it('حجوزات التحويل البنكي من العنوان نفسه محدودة في اليوم أياً كان الحساب', function () {
    // زائرٌ جديد في كل مرة: لا جلسة الحجز السابق
    $hold = fn (int $i) => $this->flushSession()->postJson('/api/v1/public/checkout', [
        'orphan_reference' => sponsorableOrphan()->reference_code, 'interval_months' => 1,
        'method' => 'bank_transfer', 'name' => 'حاجز '.$i, 'email' => "hold{$i}@example.com",
    ]);

    // كل حجزٍ بحسابٍ جديد: حدّ «ثلاثة لكل كافل» لا يراها
    foreach (range(1, App\Http\Controllers\Api\V1\Public\CheckoutController::DAILY_HOLDS_PER_IP) as $i) {
        auth()->forgetGuards();
        $hold($i)->assertCreated();
    }

    auth()->forgetGuards();
    $hold(99)->assertStatus(429)->assertJsonPath('code', 'hold_limit');
    expect(User::where('email', 'hold99@example.com')->exists())->toBeFalse();
});

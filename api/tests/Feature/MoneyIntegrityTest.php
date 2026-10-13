<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Disbursement;
use App\Models\DisbursementCategory;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Models\User;

/**
 * سلامة المال: ما وجده فحص QA من دفعات تُسجَّل لغير صاحبها، وعملات تُحسب
 * بالدولار، واستردادات بلا أثر، وصرف يعتمده من عدّله.
 */
function manualPayment(User $by, Sponsorship $s, string $reference, array $extra = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($by)->postJson('/api/v1/admin/payments', [
        'sponsorship_id' => $s->id, 'method' => 'cash', 'reference' => $reference, ...$extra,
    ]);
}

/* ------------------------------------------------------------ الدفعات */

it('الإيصال نفسه لكافلين مختلفين دفعتان منفصلتان، لا دفعة الأول مرتين', function () {
    $accountant = User::factory()->accountant()->create();
    $a = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'monthly_amount' => 30]);
    $b = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'monthly_amount' => 45]);

    manualPayment($accountant, $a, '1')->assertCreated();
    $second = manualPayment($accountant, $b, '1')->assertCreated();

    expect($second->json('data.amount'))->toEqual(45)
        ->and(Payment::where('gateway_reference', '1')->count())->toBe(2);

    // أما الإيصال نفسه للكفالة نفسها فيُرفض بوضوح لا بنجاح صامت
    manualPayment($accountant, $a, '1')->assertStatus(409);
});

it('رقم الحوالة يُحفظ ويُبحث به، وتاريخ الوصول يُحترم', function () {
    $accountant = User::factory()->accountant()->create();
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);

    manualPayment($accountant, $s, 'TRX-88213', ['method' => 'bank_transfer', 'paid_at' => now()->subDays(6)->toDateString()])->assertCreated();

    $row = $this->actingAs($accountant)->getJson('/api/v1/admin/payments?q=TRX-88213')->assertOk()->json('data.0');
    expect($row['gateway_reference'])->toBe('TRX-88213')
        ->and(substr($row['paid_at'], 0, 10))->toBe(now()->subDays(6)->toDateString());
});

it('الدفعة على كفالة بانتظار الدفع تفعّلها، وعلى كفالة منتهية تُرفض، والمبلغ الشاذ يُوقف', function () {
    $accountant = User::factory()->accountant()->create();

    $pending = Sponsorship::factory()->create(['status' => SponsorshipStatus::Pending, 'monthly_amount' => 40]);
    manualPayment($accountant, $pending, 'P-1')->assertCreated();
    expect($pending->fresh()->status)->toBe(SponsorshipStatus::Active);

    $cancelled = Sponsorship::factory()->create(['status' => SponsorshipStatus::Cancelled]);
    manualPayment($accountant, $cancelled, 'C-1')->assertUnprocessable();

    manualPayment($accountant, $pending, 'BIG-1', ['amount' => 40 * 13])->assertUnprocessable()->assertJsonValidationErrors('amount');
});

it('الاسترداد يحفظ من ومتى ولماذا، ويعيد موعد القسط، ولا يتكرر', function () {
    $admin = User::factory()->admin()->create();
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'billing_day' => 10]);
    $before = $s->next_due_at?->toDateString();

    $payment = Payment::find(manualPayment($admin, $s, 'R-1')->assertCreated()->json('data.id'));
    $advanced = $s->fresh()->next_due_at->toDateString();
    expect($advanced)->not->toBe($before);

    $this->actingAs($admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['reason' => 'حُوّلت بالخطأ'])->assertOk();

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Refunded)
        ->refunded_by->toBe($admin->id)
        ->refund_reason->toBe('حُوّلت بالخطأ')
        ->failure_reason->toBeNull()
        // يعود شهراً واحداً عمّا قدّمته الدفعة، على يوم الخصم نفسه
        ->and($s->fresh()->next_due_at->toDateString())->toBe(\Illuminate\Support\Carbon::parse($advanced)->subMonthNoOverflow()->setDay(10)->toDateString());

    $this->actingAs($admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['reason' => 'مرة ثانية'])->assertForbidden();
});

it('الخصم الآلي لا يخصم بطاقةً ممن يدفع بتحويل', function () {
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'next_due_at' => now()->subDay()]);
    Payment::create([
        'reference_code' => 'PAY-9000001', 'sponsorship_id' => $s->id, 'sponsor_id' => $s->sponsor_id,
        'amount' => 30, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => 30,
        'method' => PaymentMethod::BankTransfer, 'status' => PaymentStatus::Succeeded,
        'paid_at' => now()->subMonth(), 'idempotency_key' => 'seed-transfer',
    ]);

    $this->artisan('billing:run')->assertSuccessful();

    expect(Payment::where('sponsorship_id', $s->id)->where('method', PaymentMethod::Card)->count())->toBe(0);
});

/* ------------------------------------------------------------ الصرف */

function disbursement(User $by, array $data = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($by)->postJson('/api/v1/admin/disbursements', [
        'category_id' => DisbursementCategory::where('is_overhead', false)->value('id'),
        'amount' => 100, 'currency' => 'USD', 'spent_on' => now()->toDateString(), ...$data,
    ]);
}

it('الصرف بعملة أخرى يشترط سعر صرف، ويُحسب به لا بالدولار', function () {
    $accountant = User::factory()->accountant()->create();

    disbursement($accountant, ['currency' => 'ILS', 'amount' => 1000])->assertUnprocessable()->assertJsonValidationErrors('fx_rate');
    disbursement($accountant, ['currency' => 'XYZ'])->assertUnprocessable()->assertJsonValidationErrors('currency');

    $res = disbursement($accountant, ['currency' => 'ILS', 'amount' => 1000, 'fx_rate' => 0.27])->assertCreated();
    expect($res->json('data.amount_base'))->toEqual(270)->and($res->json('data.fx_rate'))->toEqual(0.27);
});

it('المصروف الإداري لا يُربط بطفل', function () {
    $accountant = User::factory()->accountant()->create();
    $overhead = DisbursementCategory::where('is_overhead', true)->value('id');
    $orphan = \App\Models\Orphan::factory()->create();

    disbursement($accountant, ['category_id' => $overhead, 'orphan_id' => $orphan->id])
        ->assertUnprocessable()->assertJsonValidationErrors('orphan_id');
});

it('التسجيل المكرر يُوقف إلا بتأكيد صريح', function () {
    $accountant = User::factory()->accountant()->create();

    disbursement($accountant)->assertCreated();
    disbursement($accountant)->assertStatus(409);
    disbursement($accountant, ['confirm_duplicate' => true])->assertCreated();
});

it('من عدّل مبلغ صرفٍ لا يعتمده، والاعتماد لا يتكرر', function () {
    $recorder = User::factory()->accountant()->create();
    $editor = User::factory()->accountant()->create();
    $third = User::factory()->accountant()->create();

    $id = disbursement($recorder)->assertCreated()->json('data.id');

    $this->actingAs($editor)->patchJson("/api/v1/admin/disbursements/{$id}", ['amount' => 150])->assertOk();
    $this->actingAs($editor)->postJson("/api/v1/admin/disbursements/{$id}/approve")->assertForbidden();

    $this->actingAs($third)->postJson("/api/v1/admin/disbursements/{$id}/approve")->assertOk();
    expect(Disbursement::find($id)->approved_by)->toBe($third->id);
});

it('المشرف الميداني لا يرى مبالغ الكفالات ولا بيانات الكفلاء', function () {
    $country = \App\Models\Country::where('iso2', 'PS')->value('id');
    $orphan = \App\Models\Orphan::factory()->create(['family_id' => \App\Models\Family::factory()->inCountry($country)]);
    Sponsorship::factory()->for($orphan)->create(['status' => SponsorshipStatus::Active]);

    $row = $this->actingAs(User::factory()->supervisor($country)->create())
        ->getJson('/api/v1/admin/sponsorships')->assertOk()->json('data.0');

    expect($row)->not->toHaveKeys(['monthly_amount', 'next_due_at', 'billing_day', 'sponsor']);
});

it('عمليات المال تُكتب في سجل النشاط', function () {
    $accountant = User::factory()->accountant()->create();
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active]);

    manualPayment($accountant, $s, 'LOG-1')->assertCreated();
    disbursement($accountant)->assertCreated();

    $events = \Spatie\Activitylog\Models\Activity::where('causer_id', $accountant->id)->pluck('event')->all();
    expect($events)->toContain('recorded');
    expect(count(array_filter($events, fn ($e) => $e === 'recorded')))->toBe(2);
});

it('سجل النشاط يُقرأ: فعل عربي، والسجل باسمه ورابطه، والقيم بتسمياتها', function () {
    $admin = User::factory()->admin()->create();
    $s = Sponsorship::factory()->create(['status' => SponsorshipStatus::Active, 'billing_day' => 5]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/sponsorships/{$s->id}", ['status' => 'paused'])->assertOk();

    $row = collect($this->actingAs($admin)->getJson('/api/v1/admin/activity?subject_type=Sponsorship')->assertOk()->json('data'))
        ->firstWhere('subject_name', $s->reference_code);

    expect($row['action'])->toBe('عدّل كفالة')
        ->and($row['subject_path'])->toContain($s->reference_code)
        ->and(collect($row['changes'])->firstWhere('field', 'status'))
        ->toMatchArray(['label' => 'الحالة', 'before' => 'نشطة', 'after' => SponsorshipStatus::Paused->label()]);

    $this->actingAs($admin)->getJson('/api/v1/admin/activity?subject_type=Nope')->assertUnprocessable();
});

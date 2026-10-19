<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Models\ExchangeRate;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Support\Fx;
use Illuminate\Support\Facades\Http;

/**
 * المرحلة 5: الكافل بعملته. مبلغٌ ثابت بعملته كل شهر، وما يعادله بالأساس
 * بسعر يوم الخصم، ونسبة تمويل الطفل تتبع السعر.
 */
function setRate(string $code, float $rate): void
{
    ExchangeRate::updateOrCreate(['currency' => $code], ['rate_to_base' => $rate, 'source' => 'test', 'fetched_at' => now()]);
    Fx::flush();
}

function needyOrphan(float $need = 100): Orphan
{
    $o = Orphan::factory()->create(['monthly_need' => $need]);
    $o->program->update(['monthly_price' => $need, 'currency' => 'USD']);

    return $o->fresh();
}

it('يحوّل بين العملات بالسعر الحاليّ، ويقرّب مبلغ الكافل لوحدةٍ كاملة لأعلى', function () {
    setRate('GBP', 1.25);

    expect(Fx::toBase(40, 'GBP'))->toBe(50.0)
        ->and(Fx::fromBase(50, 'GBP'))->toBe(40.0)
        ->and(Fx::friendly(65, 'GBP'))->toBe(52.0)
        ->and(Fx::friendly(65, 'USD'))->toBe(65.0)
        ->and(fn () => Fx::rate('XYZ'))->toThrow(InvalidArgumentException::class);
});

it('الكفالة بالجنيه: مبلغها بالجنيه ثابت، وما يعادله بالدولار يُحسب في تمويل الطفل', function () {
    setRate('GBP', 1.25);
    $orphan = needyOrphan(100);
    $sponsor = Sponsor::factory()->create();

    $s = app(\App\Actions\Sponsorships\CreateSponsorship::class)->handle($sponsor, $orphan, monthlyAmount: 40, currency: 'GBP');

    expect($s->charge_currency)->toBe('GBP')
        ->and((float) $s->charge_amount)->toBe(40.0)
        ->and((float) $s->monthly_amount)->toBe(50.0)
        ->and($s->currency)->toBe('USD')
        ->and($s->share_percent)->toBe(50)
        ->and($orphan->fresh()->funded_percent)->toBe(50)
        ->and($s->chargeCycleAmount())->toBe(40.0);
});

it('حدود المبلغ بعملة الكافل: لا يتجاوز المتبقي محوّلاً، ولا يقلّ عن ما يعادل 5', function () {
    setRate('GBP', 1.25);
    $orphan = needyOrphan(100);
    $create = app(\App\Actions\Sponsorships\CreateSponsorship::class);

    expect(fn () => $create->handle(Sponsor::factory()->create(), $orphan, monthlyAmount: 81, currency: 'GBP'))
        ->toThrow(\Illuminate\Validation\ValidationException::class, '80.00 GBP');
    expect(fn () => $create->handle(Sponsor::factory()->create(), $orphan, monthlyAmount: 3, currency: 'GBP'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $create->handle(Sponsor::factory()->create(), $orphan, monthlyAmount: 10, currency: 'JPY'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // كفالة كاملة بالجنيه: 80 جنيهاً تكفي حاجة 100 دولار
    $full = $create->handle(Sponsor::factory()->create(), $orphan, currency: 'GBP');
    expect((float) $full->charge_amount)->toBe(80.0)
        ->and($orphan->fresh()->funded_percent)->toBe(100);
});

it('الخصم الشهري بعملة الكافل، والدفعة تحمل سعر يومها وما يعادلها', function () {
    setRate('GBP', 1.25);
    $s = Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Active, 'monthly_amount' => 50, 'charge_currency' => 'GBP', 'charge_amount' => 40,
        'billing_day' => 1, 'next_due_at' => now()->subDay(), 'payment_method' => PaymentMethod::Card->value, 'gateway_payment_method' => 'pm_demo_saved',
    ]);
    setRate('GBP', 1.30);

    $this->artisan('billing:run')->assertSuccessful();

    $p = Payment::where('sponsorship_id', $s->id)->sole();
    expect($p->currency)->toBe('GBP')
        ->and((float) $p->amount)->toBe(40.0)
        ->and((float) $p->fx_rate)->toBe(1.3)
        ->and((float) $p->amount_base)->toBe(52.0)
        ->and($p->status)->toBe(PaymentStatus::Succeeded);
});

it('تحديث الأسعار يجلبها من المصدر ويعيد حساب ما يعادل الكفالات بعملةٍ أخرى', function () {
    setRate('GBP', 1.25);
    $orphan = needyOrphan(100);
    $s = app(\App\Actions\Sponsorships\CreateSponsorship::class)->handle(Sponsor::factory()->create(), $orphan, monthlyAmount: 40, currency: 'GBP');
    ExchangeRate::query()->update(['source' => 'open.er-api.com']);

    // ينخفض الجنيه من 1.25 إلى 1.2
    Http::fake(['*' => Http::response(['result' => 'success', 'rates' => ['GBP' => 1 / 1.2, 'EUR' => 0.9, 'SAR' => 3.75]])]);

    $this->artisan('kafala:rates-refresh')->assertSuccessful();

    expect(Fx::rate('GBP'))->toBe(1.2)
        ->and((float) $s->fresh()->monthly_amount)->toBe(48.0)
        ->and($orphan->fresh()->funded_percent)->toBe(48);
});

it('مصدرٌ معطّل أو قفزةٌ غير معقولة لا تمسّ الأسعار الحالية', function () {
    setRate('GBP', 1.25);
    ExchangeRate::query()->update(['source' => 'open.er-api.com']);

    Http::fakeSequence()
        ->push('down', 503)->push('down', 503)->push('down', 503)
        ->push(['result' => 'success', 'rates' => ['GBP' => 0.4]]);
    $this->artisan('kafala:rates-refresh')->assertFailed();
    expect(Fx::rate('GBP'))->toBe(1.25);

    // 1 / 0.4 = 2.5: الجنيه «يتضاعف» في يوم، خطأ مصدرٍ لا يُقبل
    $this->artisan('kafala:rates-refresh')->assertSuccessful();
    expect(Fx::rate('GBP'))->toBe(1.25);
});

it('فرقٌ دون الدولار من تقلّب السعر لا يعيد طفلاً مكفولاً بالكامل إلى الموقع', function () {
    setRate('GBP', 1.25);
    $orphan = needyOrphan(100);
    app(\App\Actions\Sponsorships\CreateSponsorship::class)->handle(Sponsor::factory()->create(), $orphan, currency: 'GBP');
    ExchangeRate::query()->update(['source' => 'open.er-api.com']);

    // 80 جنيهاً بسعر 1.244 = 99.52 دولاراً
    Http::fake(['*' => Http::response(['result' => 'success', 'rates' => ['GBP' => 1 / 1.244]])]);
    $this->artisan('kafala:rates-refresh');

    expect($orphan->fresh()->funded_percent)->toBe(100)
        ->and($orphan->fresh()->remainingNeed())->toBe(0.0);
});

it('صفحة الكفالة تعرض عملات الكفلاء وأسعارها، وتكفل بالجنيه', function () {
    setRate('GBP', 1.25);
    $orphan = needyOrphan(100);

    $config = $this->getJson('/api/v1/public/checkout/config')->assertOk()->json('data');
    expect(collect($config['currencies'])->pluck('code')->all())->toContain('USD', 'GBP', 'EUR')
        ->and(collect($config['currencies'])->firstWhere('code', 'GBP')['rate'])->toEqual(1.25);

    $res = $this->postJson('/api/v1/public/checkout', [
        'orphan_reference' => $orphan->reference_code, 'monthly_amount' => 40, 'currency' => 'GBP', 'interval_months' => 1,
        'method' => 'card', 'card_token' => 'tok_demo_ok', 'card_last4' => '4242', 'disclosure' => true,
        'name' => 'Sarah Ahmed', 'email' => 'sarah@example.com',
    ])->assertCreated();

    $p = Payment::where('status', PaymentStatus::Succeeded)->sole();
    expect($p->currency)->toBe('GBP')
        ->and((float) $p->amount_base)->toBe(50.0)
        ->and($res->json('data.currency'))->toBe('GBP')
        ->and(Sponsor::whereHas('user', fn ($q) => $q->where('email', 'sarah@example.com'))->value('preferred_currency'))->toBe('GBP');
});

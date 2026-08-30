<?php

declare(strict_types=1);

use App\Enums\SponsorshipStatus;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * قيود قاعدة البيانات.
 *
 * هذه أهم اختبارات المشروع: تتحقق أن الحماية في **المحرّك** لا في انضباط
 * الكود. لو أُزيل كل الكود وبقيت هذه القيود، لبقيت البيانات سليمة.
 */
it('يمنع كفالتين حيّتين لنفس الكافل ونفس الطفل', function () {
    $sponsor = Sponsor::factory()->create();
    $orphan = Orphan::factory()->create();

    Sponsorship::factory()->for($sponsor)->for($orphan)->create();

    expect(fn () => Sponsorship::factory()->for($sponsor)->for($orphan)->create())
        ->toThrow(QueryException::class);
});

it('يسمح للكافل بالعودة إلى الطفل نفسه بعد الإلغاء', function () {
    // هذا ما يفشل فيه UNIQUE(sponsor_id, orphan_id) العادي، ولذلك
    // استُعمل العمود المُولَّد active_key الذي يصير NULL عند الإلغاء
    $sponsor = Sponsor::factory()->create();
    $orphan = Orphan::factory()->create();

    Sponsorship::factory()->for($sponsor)->for($orphan)->cancelled()->create();

    $second = Sponsorship::factory()->for($sponsor)->for($orphan)->create();

    expect($second->exists)->toBeTrue()
        ->and(Sponsorship::where('sponsor_id', $sponsor->id)->count())->toBe(2);
});

it('يفرغ active_key عند الإلغاء فيحرَّر القيد', function () {
    $sponsorship = Sponsorship::factory()->create();

    $key = fn () => DB::table('sponsorships')->where('id', $sponsorship->id)->value('active_key');

    expect($key())->toBe(1);

    $sponsorship->update(['status' => SponsorshipStatus::Cancelled]);

    expect($key())->toBeNull();
});

it('يمنع تسجيل الدفعة نفسها مرتين', function () {
    // مفتاح التفرّد هو ما يجعل الخصم المزدوج مستحيلاً حين يضغط الكافل
    // «ادفع» مرتين أو تعيد البوابة إرسال الـ webhook
    $sponsorship = Sponsorship::factory()->create();

    $row = fn () => [
        'reference_code' => fake()->unique()->numerify('PAY-#######'),
        'sponsorship_id' => $sponsorship->id,
        'sponsor_id' => $sponsorship->sponsor_id,
        'amount' => 65, 'currency' => 'USD', 'fx_rate' => 1, 'amount_base' => 65,
        'method' => 'card', 'status' => 'succeeded', 'paid_at' => now(),
        'idempotency_key' => 'same-key',
    ];

    Payment::create($row());

    expect(fn () => Payment::create($row()))->toThrow(QueryException::class);
});

it('يمنع تقريرين للطفل نفسه في الربع نفسه', function () {
    $orphan = Orphan::factory()->create();

    $row = [
        'orphan_id' => $orphan->id,
        'period_year' => 2026,
        'period_quarter' => 2,
        'title' => 'تقرير',
    ];

    Report::create($row);

    expect(fn () => Report::create($row))->toThrow(QueryException::class);
});

it('يرفض حذف يتيم له كفالة', function () {
    // RESTRICT على المفتاح الأجنبي: السجل المالي لا يُيتَّم
    $sponsorship = Sponsorship::factory()->create();

    expect(fn () => DB::table('orphans')->where('id', $sponsorship->orphan_id)->delete())
        ->toThrow(QueryException::class);
});

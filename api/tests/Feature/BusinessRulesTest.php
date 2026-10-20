<?php

declare(strict_types=1);

use App\Actions\Orphans\VerifyOrphan;
use App\Actions\Payments\RecordPayment;
use App\Actions\Sponsorships\CreateSponsorship;
use App\Enums\OrphanStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Orphan;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('ينسخ سعر البرنامج لحظة الإنشاء فلا يتبع تغيّره', function () {
    // snapshot pricing: رفع السعر لا يمسّ التزام كافل قائم
    $sponsor = Sponsor::factory()->create();
    $orphan = Orphan::factory()->create(['monthly_need' => 65]);

    $sponsorship = app(CreateSponsorship::class)->handle($sponsor, $orphan);

    $orphan->program->update(['monthly_price' => 200]);

    expect((float) $sponsorship->fresh()->monthly_amount)->toBe(65.0);
});

it('يرفض الكفالة على ملف مكتمل التمويل', function () {
    $sponsor = Sponsor::factory()->create();
    $orphan = Orphan::factory()->create(['monthly_need' => 65]);

    Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 65]);

    expect(fn () => app(CreateSponsorship::class)->handle($sponsor, $orphan->fresh()))
        ->toThrow(ValidationException::class);
});

it('يرفض الكفالة على ملف لم يُوثَّق بعد', function () {
    $sponsor = Sponsor::factory()->create();
    $orphan = Orphan::factory()->pending()->create();

    expect(fn () => app(CreateSponsorship::class)->handle($sponsor, $orphan))
        ->toThrow(ValidationException::class);
});

it('لا يتجاوز المبلغ ما تبقّى من حاجة الطفل: الأكبر يُرفض بوضوح، والافتراضي يُقصر عليه', function () {
    // كافلان يضغطان «اكفل» معاً كانا يتجاوزان الحاجة لولا قفل الصف.
    // والمبلغ الأكبر كان يُخفَّض بصمت، فيُخصم غير ما رآه الكافل.
    $orphan = Orphan::factory()->create(['monthly_need' => 100]);
    Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 70]);

    expect(fn () => app(CreateSponsorship::class)->handle(Sponsor::factory()->create(), $orphan->fresh(), 100.0))
        ->toThrow(ValidationException::class);

    $sponsorship = app(CreateSponsorship::class)->handle(Sponsor::factory()->create(), $orphan->fresh());

    expect((float) $sponsorship->monthly_amount)->toBe(30.0)
        ->and($orphan->fresh()->funded_percent)->toBe(100);
});

it('يعيد الدفعة الأولى بدل الخصم مرتين', function () {
    $sponsorship = Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Pending,
    ]);

    $action = app(RecordPayment::class);

    $first = $action->handle($sponsorship, 'idem-key-1');
    $second = $action->handle($sponsorship->fresh(), 'idem-key-1');

    expect($second->id)->toBe($first->id)
        ->and($sponsorship->fresh()->payments()->count())->toBe(1);
});

it('يفعّل الكفالة عند أول دفعة ويدفع موعد الاستحقاق', function () {
    $sponsorship = Sponsorship::factory()->create([
        'status' => SponsorshipStatus::Pending,
        'billing_day' => 10,
        'next_due_at' => now()->startOfMonth()->setDay(10)->toDateString(),
    ]);

    $before = $sponsorship->next_due_at;

    app(RecordPayment::class)->handle($sponsorship, 'idem-key-2');

    expect($sponsorship->fresh())
        ->status->toBe(SponsorshipStatus::Active)
        ->and($sponsorship->fresh()->next_due_at->gt($before))->toBeTrue();
});

it('يرفض التوثيق بلا المستندات الثلاثة', function () {
    // هذا ما يحوّل ادّعاء «ثلاث مراحل تحقّق» إلى شرط قابل للفشل
    $orphan = Orphan::factory()->pending()->create();
    $admin = User::factory()->admin()->create();

    expect(fn () => app(VerifyOrphan::class)->verify($orphan, $admin))
        ->toThrow(ValidationException::class);
});

it('يوثّق وينقل الحالة إلى «نشط» عند اكتمال المستندات', function () {
    $orphan = Orphan::factory()->pending()->withVerifiedDocuments()->create();
    $admin = User::factory()->admin()->create();

    $verified = app(VerifyOrphan::class)->verify($orphan, $admin);

    expect($verified->verified_at)->not->toBeNull()
        ->and($verified->status)->toBe(OrphanStatus::Active);
});

it('يرفض نشر ملف غير موثّق', function () {
    $orphan = Orphan::factory()->pending()->create();

    expect(fn () => app(VerifyOrphan::class)->publish($orphan))
        ->toThrow(ValidationException::class);
});

<?php

declare(strict_types=1);

use App\Enums\OrphanStatus;
use App\Enums\SponsorshipStatus;
use App\Models\Orphan;
use App\Models\Sponsorship;

/**
 * نسبة التمويل.
 *
 * تُحسب في Observer لا في المتحكّم، لأن الكفالة تتغيّر من ستة مسارات مختلفة
 * والاستدعاء اليدوي يعني نسيانه في أحدها يوماً — فتظهر نسبة خاطئة على صفحة
 * عامة يقرؤها متبرّع.
 */
it('يحدّث نسبة التمويل تلقائياً عند إنشاء كفالة', function () {
    $orphan = Orphan::factory()->create(['monthly_need' => 100]);

    expect($orphan->funded_percent)->toBe(0);

    Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 40]);

    expect($orphan->fresh()->funded_percent)->toBe(40);
});

it('يجمع كفالتين على الطفل نفسه', function () {
    $orphan = Orphan::factory()->create(['monthly_need' => 100]);

    Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 40]);
    Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 35]);

    expect($orphan->fresh()->funded_percent)->toBe(75);
});

it('يرفع الحالة إلى «مكتمل التمويل» عند بلوغ المئة', function () {
    $orphan = Orphan::factory()->create(['monthly_need' => 100]);

    Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 100]);

    expect($orphan->fresh())
        ->funded_percent->toBe(100)
        ->status->toBe(OrphanStatus::FullyFunded);
});

it('يعيد الحالة إلى «نشط» عند إلغاء الكفالة', function () {
    // الطفل يعود إلى الموقع ينتظر كافلاً بديلاً — تلقائياً
    $orphan = Orphan::factory()->create(['monthly_need' => 100]);
    $sponsorship = Sponsorship::factory()->for($orphan)->create(['monthly_amount' => 100]);

    $sponsorship->update(['status' => SponsorshipStatus::Cancelled]);

    expect($orphan->fresh())
        ->funded_percent->toBe(0)
        ->status->toBe(OrphanStatus::Active);
});

it('لا يحتسب الكفالة الملغاة ويحتسب الموقوفة', function () {
    // الموقوفة تُحتسب لأن الكافل ينوي العودة، ولا يجوز أن يُعرض الطفل
    // على كافل آخر ثم يعود الأول فلا يجد مكانه
    $orphan = Orphan::factory()->create(['monthly_need' => 100]);

    Sponsorship::factory()->for($orphan)->create([
        'monthly_amount' => 50, 'status' => SponsorshipStatus::Paused,
    ]);
    Sponsorship::factory()->for($orphan)->cancelled()->create(['monthly_amount' => 50]);

    expect($orphan->fresh()->funded_percent)->toBe(50);
});

it('يشتق العمر من تاريخ الميلاد لا من عمود مخزّن', function () {
    $orphan = Orphan::factory()->create([
        'date_of_birth' => now()->subYears(9)->subMonths(2)->toDateString(),
    ]);

    expect($orphan->age)->toBe(9);
});

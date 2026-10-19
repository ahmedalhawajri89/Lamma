<?php

declare(strict_types=1);

use App\Enums\AssociationStatus;
use App\Enums\CareArrangement;
use App\Enums\OrphanStatus;
use App\Models\Association;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\User;

/**
 * المرحلة 2: لا يُنشر طفلٌ للكفالة إلا في أسرته، بإقرار رعاية حديث، عبر
 * جمعية موثّقة. والجمعية الموقوفة يختفي أطفالها.
 */
function draftOrphan(array $family = []): Orphan
{
    return Orphan::factory()->create([
        'status' => OrphanStatus::Draft, 'verified_at' => now(), 'published_at' => null,
        'family_id' => Family::factory()->state($family),
    ]);
}

it('يُنشر الطفل المُوثَّق في أسرته عبر جمعية موثّقة', function () {
    $orphan = draftOrphan();

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/v1/admin/orphans/{$orphan->id}/publish")->assertOk();

    expect($orphan->fresh()->published_at)->not->toBeNull();
});

it('يرفض النشر بسببٍ مقروء: دار رعاية، أو جمعية غير موثّقة، أو إقرار قديم', function (array $family, string $reason) {
    $orphan = draftOrphan(is_callable($family) ? $family() : $family);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/v1/admin/orphans/{$orphan->id}/publish")
        ->assertUnprocessable()
        ->assertJsonFragment([$reason]);

    expect($orphan->fresh()->published_at)->toBeNull();
})->with([
    'دار رعاية' => [['care_arrangement' => CareArrangement::ResidentialCare], 'الطفل في دار رعاية. «لمّة» تكفل الأطفال في أسرهم وحدهم.'],
    'إقرار قديم' => [['care_attested_at' => now()->subMonths(13)], 'لم يُقَرّ ترتيب رعايته خلال اثني عشر شهراً. أكّده بزيارة ميدانية.'],
    'بلا إقرار' => [['care_attested_at' => null], 'لم يُقَرّ ترتيب رعايته خلال اثني عشر شهراً. أكّده بزيارة ميدانية.'],
    'جمعية قيد المراجعة' => [fn () => ['association_id' => Association::factory()->pending()->create(['name_ar' => 'جمعية الاختبار'])->id], 'جمعية الأسرة («جمعية الاختبار») غير موثّقة بعد.'],
]);

it('يُخفى الطفل دائماً، ولو لم تعد شروط نشره قائمة', function () {
    $orphan = Orphan::factory()->create(['status' => OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now()]);
    $orphan->family->update(['care_arrangement' => CareArrangement::ResidentialCare]);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/v1/admin/orphans/{$orphan->id}/unpublish")->assertOk();

    expect($orphan->fresh()->published_at)->toBeNull();
});

it('إيقاف الجمعية يُخفي أطفالها من الموقع، وما قبل المرحلة 2 بلا جمعية يبقى ظاهراً', function () {
    $visible = Orphan::factory()->create(['status' => OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now()]);
    $suspendedKid = Orphan::factory()->create(['status' => OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now()]);
    $legacy = Orphan::factory()->create([
        'status' => OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now(),
        'family_id' => Family::factory()->state(['association_id' => null]),
    ]);
    Family::whereKey($legacy->family_id)->update(['association_id' => null]);   // لا يملؤه booted

    $suspendedKid->family->update(['association_id' => Association::factory()->create()->id]);
    $suspendedKid->family->association->update(['status' => AssociationStatus::Suspended]);

    $ids = Orphan::query()->public()->pluck('id');

    expect($ids)->toContain($visible->id)->toContain($legacy->id)->not->toContain($suspendedKid->id);
});

it('الزيارة الميدانية تجدّد إقرار الرعاية أو تُسقطه', function () {
    $orphan = Orphan::factory()->create(['status' => OrphanStatus::Active, 'verified_at' => now(), 'published_at' => now()]);
    $orphan->family->update(['care_attested_at' => now()->subMonths(11)]);
    $admin = User::factory()->admin()->create();
    $visit = fn (bool $ok) => $this->actingAs($admin)->postJson('/api/v1/admin/visits', [
        'orphan_id' => $orphan->id, 'visited_at' => now()->toDateTimeString(),
        'findings' => 'زرنا الطفل في بيته مع أمه.', 'care_confirmed' => $ok,
    ])->assertCreated();

    $visit(true);
    expect($orphan->family->fresh()->care_attested_at->isToday())->toBeTrue()
        ->and($orphan->family->fresh()->care_attested_by)->toBe($admin->id);

    $visit(false);
    expect($orphan->family->fresh()->care_attested_at)->toBeNull();
});

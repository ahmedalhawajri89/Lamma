<?php

declare(strict_types=1);

use App\Models\Disbursement;
use App\Models\DisbursementCategory;
use App\Models\Orphan;
use App\Models\User;

/**
 * شاشة الصرف: توزيع المعتمَد على البنود، ونسبة التشغيل، وما ينتظر الاعتماد،
 * وما لا إيصال له، والتصدير.
 */
function spend(DisbursementCategory $c, float $amount, bool $approved, array $extra = []): Disbursement
{
    return Disbursement::create([
        'category_id' => $c->id, 'amount' => $amount, 'currency' => 'USD', 'amount_base' => $amount, 'fx_rate' => 1,
        'spent_on' => now()->subDay()->toDateString(), 'description' => 'صرف للاختبار', 'recorded_by' => User::factory()->create()->id,
        'approved_at' => $approved ? now() : null, ...$extra,
    ]);
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Disbursement::query()->delete();
});

it('يوزّع المعتمَد على البنود ويحسب نسبة التشغيل، ويعدّ ما ينتظر وما بلا إيصال', function (): void {
    $care = DisbursementCategory::where('is_overhead', false)->firstOrFail();
    $ops = DisbursementCategory::where('is_overhead', true)->firstOrFail();

    spend($care, 90, true);
    spend($ops, 10, true, ['receipt_path' => 'receipts/x.pdf']);
    spend($care, 50, false);   // لا يدخل التوزيع حتى يُعتمد

    $meta = $this->getJson('/api/v1/admin/disbursements?status=pending')->assertOk()->json('meta');

    expect($meta['total'])->toBe(1)
        ->and($meta['pending_count'])->toBe(1)
        ->and($meta['approved_count'])->toBe(2)
        ->and($meta['no_receipt_count'])->toBe(2)
        ->and((float) $meta['sum_approved'])->toEqual(100.0)
        ->and((float) $meta['overhead_percent'])->toEqual(10.0)
        ->and($meta['by_category'])->toHaveCount(2)
        ->and($meta['by_category'][0]['id'])->toBe($care->id);
});

it('يبحث باسم الطفل، ويفلتر المصروف العام وما بلا إيصال', function (): void {
    $care = DisbursementCategory::where('is_overhead', false)->firstOrFail();
    $orphan = Orphan::factory()->create(['first_name' => 'ياسمين']);
    spend($care, 20, true, ['orphan_id' => $orphan->id]);
    spend($care, 30, true, ['receipt_path' => 'receipts/y.pdf']);

    expect($this->getJson('/api/v1/admin/disbursements?q=ياسم')->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/v1/admin/disbursements?scope=general')->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/v1/admin/disbursements?no_receipt=1')->json('meta.total'))->toBe(1);
});

it('يصدّر الصرف ويسجّل التصدير', function (): void {
    spend(DisbursementCategory::firstOrFail(), 15, true);

    $csv = $this->get('/api/v1/admin/disbursements/export')->assertOk()->streamedContent();
    expect(substr_count($csv, "\n"))->toBe(2);
    $this->assertDatabaseHas('activity_log', ['description' => 'صدّر الصرف']);
});

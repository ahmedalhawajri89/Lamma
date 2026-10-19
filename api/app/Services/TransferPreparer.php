<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\TransferStatus;
use App\Models\AssociationTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تحويلات الشهر للجمعيات الشريكة، من الدفعات نفسها.
 *
 * لكل جمعية: ما دفعه كفلاء أطفالها فعلاً في الشهر (الدفعات الناجحة، لا
 * المستردّة)، وتشغيل كل دفعة بنسبتها المحفوظة عليها، والباقي يُحوَّل.
 * وتفصيله لكل طفل، فيقرأ الكافل حصة طفله لا مجموع الجمعية.
 *
 * يُعاد تشغيله بأمان: المسوّدة تُبنى من جديد بما استجدّ من دفعات، والمُرسَل
 * والمؤكَّد لا يُمسّ؛ مالٌ خرج لا يُعاد حسابه.
 */
final class TransferPreparer
{
    /** @return array{created: int, rebuilt: int, locked: int, total: float} */
    public function prepare(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $start->copy()->addMonth();

        // لكل طفل في جمعية: عدد دفعاته الناجحة في الشهر، ومجموعها، وتشغيلها
        $rows = DB::table('payments')
            ->join('sponsorships', 'sponsorships.id', '=', 'payments.sponsorship_id')
            ->join('orphans', 'orphans.id', '=', 'sponsorships.orphan_id')
            ->join('families', 'families.id', '=', 'orphans.family_id')
            ->whereNotNull('families.association_id')
            ->where('payments.status', PaymentStatus::Succeeded->value)
            ->where('payments.paid_at', '>=', $start)
            ->where('payments.paid_at', '<', $end)
            ->groupBy('families.association_id', 'orphans.id')
            ->selectRaw('families.association_id, orphans.id as orphan_id, count(*) as payments_count')
            ->selectRaw('SUM(payments.amount_base) as received')
            ->selectRaw('SUM(ROUND(payments.amount_base * COALESCE(payments.platform_fee_percent, 7) / 100, 2)) as fee')
            ->get()
            ->groupBy('association_id');

        $summary = ['created' => 0, 'rebuilt' => 0, 'locked' => 0, 'total' => 0.0];

        foreach ($rows as $associationId => $children) {
            DB::transaction(function () use ($associationId, $children, $start, &$summary): void {
                $transfer = AssociationTransfer::query()
                    ->where('association_id', $associationId)
                    ->whereDate('period', $start->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($transfer !== null && $transfer->status !== TransferStatus::Draft) {
                    $summary['locked']++;

                    return;
                }

                $received = round((float) $children->sum('received'), 2);
                $fee = round((float) $children->sum('fee'), 2);

                $transfer ??= new AssociationTransfer([
                    'association_id' => $associationId,
                    'period' => $start->toDateString(),
                    'reference_code' => sprintf('TRF-%s-A%04d', $start->format('Y-m'), $associationId),
                    'status' => TransferStatus::Draft,
                ]);
                $summary[$transfer->exists ? 'rebuilt' : 'created']++;

                $transfer->fill([
                    'currency' => config('kafala.base_currency', 'USD'),
                    'received_base' => $received,
                    'fee_base' => $fee,
                    'amount_base' => round($received - $fee, 2),
                ])->save();

                $transfer->lines()->delete();
                $transfer->lines()->createMany($children->map(fn ($c): array => [
                    'orphan_id' => $c->orphan_id,
                    'payments_count' => (int) $c->payments_count,
                    'received_base' => round((float) $c->received, 2),
                    'fee_base' => round((float) $c->fee, 2),
                    'amount_base' => round((float) $c->received - (float) $c->fee, 2),
                ])->values()->all());

                $summary['total'] += (float) $transfer->amount_base;
            });
        }

        $summary['total'] = round($summary['total'], 2);

        return $summary;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FundEntryKind;
use App\Enums\PaymentStatus;
use App\Enums\TransferStatus;
use App\Models\AssociationTransferLine;
use App\Models\ContinuityFundEntry;
use App\Models\Disbursement;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Support\Locale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * «أين ذهب مالك»: سجلّ الطفل شهراً بشهر، كما يراه كافله.
 *
 * لكل شهر:
 *  · ما وصل لهذا الطفل من كل كفلائه، وحصة هذا الكافل منه؛
 *  · نسبة التشغيل منه (المحفوظة على كل دفعة)؛
 *  · ما غطّاه صندوق الاستمرارية له إن تعثّر قسط؛
 *  · ما حُوِّل لجمعيته عنه (من تحويل الشهر المُرسَل أو المؤكَّد وحده)؛
 *  · ما صُرف عليه فعلاً، بنداً بنداً (الصرف المعتمَد بتاريخ صرفه)، ومنه ما
 *    له إيصالٌ موثّق؛
 * والرصيد: ما حُوِّل ولم يُصرف بعد، عند الجمعية للطفل.
 *
 * كل رقمٍ هنا استعلامٌ على صفوف المال نفسها، لا رقمٌ مكتوب.
 */
final class ChildLedgerService
{
    /** @return array<string, mixed> */
    public function forSponsorship(Sponsorship $sponsorship, int $months = 12): array
    {
        $orphan = $sponsorship->orphan()->with('family.association')->firstOrFail();

        $now = now()->startOfMonth();
        $since = Carbon::parse($sponsorship->started_at ?? $sponsorship->created_at)->startOfMonth();
        $from = $since->max($now->copy()->subMonths($months - 1));
        $to = $now->copy()->addMonth();

        // الدفعات الناجحة للطفل في المدة: كلها، ولكلٍّ صاحبها ونسبتها
        $payments = Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->whereBetween('paid_at', [$from, $to])
            ->whereHas('sponsorship', fn ($q) => $q->where('orphan_id', $orphan->id))
            ->get(['id', 'sponsor_id', 'amount_base', 'platform_fee_percent', 'paid_at']);

        $transfers = AssociationTransferLine::query()
            ->where('orphan_id', $orphan->id)
            ->whereHas('transfer', fn ($q) => $q->whereIn('status', [TransferStatus::Sent, TransferStatus::Confirmed])->whereBetween('period', [$from->toDateString(), $to->toDateString()]))
            ->with('transfer:id,reference_code,period,status,sent_at,confirmed_at')
            ->get();

        // ما غطّاه صندوق الاستمرارية للطفل حين تعثّر قسطٌ لأحد كفلائه
        $grants = ContinuityFundEntry::query()
            ->where('kind', FundEntryKind::Grant)
            ->where('orphan_id', $orphan->id)
            ->whereBetween('created_at', [$from, $to])
            ->get(['amount_base', 'created_at']);

        $spending = Disbursement::query()
            ->approved()
            ->where('orphan_id', $orphan->id)
            ->whereBetween('spent_on', [$from->toDateString(), $to->copy()->subDay()->toDateString()])
            ->with('category:id,slug,name_ar,name_en,color')
            ->get(['id', 'category_id', 'amount_base', 'spent_on', 'receipt_path']);

        $months = [];
        for ($m = $now->copy(); $m->greaterThanOrEqualTo($from); $m->subMonth()) {
            $key = $m->format('Y-m');
            $inMonth = fn ($date) => Carbon::parse($date)->format('Y-m') === $key;

            $paid = $payments->filter(fn (Payment $p) => $inMonth($p->paid_at));
            $line = $transfers->first(fn ($l) => $l->transfer->period->format('Y-m') === $key);
            $spent = $spending->filter(fn (Disbursement $d) => $inMonth($d->spent_on));

            $months[] = [
                'period' => $key,
                'label' => $m->copy()->locale(app()->getLocale())->translatedFormat('F Y'),
                'received' => round((float) $paid->sum('amount_base'), 2),
                'mine' => round((float) $paid->where('sponsor_id', $sponsorship->sponsor_id)->sum('amount_base'), 2),
                'fee' => round($paid->sum(fn (Payment $p) => $p->feeBase()), 2),
                'fund' => round((float) $grants->filter(fn ($g) => $inMonth($g->created_at))->sum('amount_base'), 2),
                'transfer' => $line ? [
                    'amount' => round((float) $line->amount_base, 2),
                    'status' => $line->transfer->status->value,
                    'status_label' => $line->transfer->status->label(),
                    'reference' => $line->transfer->reference_code,
                    'sent_at' => $line->transfer->sent_at?->toDateString(),
                    'confirmed_at' => $line->transfer->confirmed_at?->toDateString(),
                ] : null,
                'spent' => $this->byCategory($spent),
                'spent_total' => round((float) $spent->sum('amount_base'), 2),
            ];
        }

        $received = round((float) $payments->sum('amount_base'), 2);
        $mine = round((float) $payments->where('sponsor_id', $sponsorship->sponsor_id)->sum('amount_base'), 2);
        $transferred = round((float) $transfers->sum('amount_base'), 2);
        $spentTotal = round((float) $spending->sum('amount_base'), 2);
        $association = $orphan->family?->association;

        return [
            'child' => ['name' => $orphan->translated('first_name'), 'reference' => $orphan->reference_code],
            'association' => $association && $association->isVerified()
                ? ['name' => $association->displayName(), 'slug' => $association->slug]
                : null,
            'fee_percent' => Payment::currentFeePercent(),
            'from' => $from->format('Y-m'),
            'months' => $months,
            'categories' => $this->byCategory($spending),
            'totals' => [
                'received' => $received,
                'mine' => $mine,
                'fee' => round($payments->sum(fn (Payment $p) => $p->feeBase()), 2),
                'fund' => round((float) $grants->sum('amount_base'), 2),
                'transferred' => $transferred,
                'spent' => $spentTotal,
                // ما حُوِّل ولم يُصرف بعد: عند الجمعية للطفل
                'balance' => round($transferred - $spentTotal, 2),
                'my_share_percent' => $received > 0 ? (int) round($mine * 100 / $received) : null,
                'receipts' => $spending->whereNotNull('receipt_path')->count(),
                'spend_items' => $spending->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Disbursement>  $spending
     * @return list<array{slug: string, label: string, color: ?string, amount: float, receipts: int}>
     */
    private function byCategory($spending): array
    {
        return $spending
            ->groupBy(fn (Disbursement $d) => $d->category->slug ?? 'other')
            ->map(fn ($items, $slug): array => [
                'slug' => (string) $slug,
                'label' => (string) Locale::pick($items->first()->category?->name_ar, $items->first()->category?->name_en),
                'color' => $items->first()->category?->color,
                'amount' => round((float) $items->sum('amount_base'), 2),
                'receipts' => $items->whereNotNull('receipt_path')->count(),
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }
}

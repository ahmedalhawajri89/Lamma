<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FundEntryKind;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Http\Controllers\Controller;
use App\Models\ContinuityFundEntry;
use App\Models\Sponsorship;
use App\Services\ContinuityFund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * صندوق الاستمرارية في اللوحة: رصيده وقيوده، والكفالات المتعثّرة الآن
 * (ما زالت تُعاد محاولاتها، أو يغطّيها الصندوق)، وتسجيل تبرّعٍ له.
 *
 * العرض لمن يرى الصرف، والتبرّع لمن يعتمده: مالٌ يدخل الصندوق.
 */
class ContinuityFundController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('disbursements.view'), 403);

        $request->validate(['kind' => ['nullable', Rule::in(FundEntryKind::values())]]);

        $entries = ContinuityFundEntry::query()
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')->toString()))
            ->with(['orphan:id,first_name,reference_code', 'association:id,name_ar', 'sponsorship:id,reference_code', 'recorder:id,name'])
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 20));

        $totals = ContinuityFundEntry::query()->toBase()->selectRaw('kind, count(*) as n, SUM(amount_base) as total')->groupBy('kind')->get()->keyBy('kind');
        $year = now()->startOfYear();
        $sum = fn (array $kinds, ?Carbon $from = null) => round((float) ContinuityFundEntry::query()
            ->whereIn('kind', $kinds)->when($from, fn ($q) => $q->where('period', '>=', $from->toDateString()))->sum('amount_base'), 2);

        return response()->json([
            'data' => collect($entries->items())->map(fn (ContinuityFundEntry $e) => [
                'id' => $e->id,
                'kind' => $e->kind->value,
                'kind_label' => $e->kind->label(),
                'is_in' => $e->kind->isIn(),
                'amount' => (float) $e->amount_base,
                'period' => $e->period->format('Y-m'),
                'created_at' => $e->created_at?->toIso8601String(),
                'note' => $e->note,
                'orphan' => $e->orphan ? ['id' => $e->orphan->id, 'name' => $e->orphan->first_name, 'reference_code' => $e->orphan->reference_code] : null,
                'association' => $e->association?->name_ar,
                'sponsorship' => $e->sponsorship?->reference_code,
                'recorded_by' => $e->recorder?->name,
            ])->all(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'kind_counts' => $totals->map(fn ($r) => (int) $r->n),
                'all_count' => (int) $totals->sum('n'),
                'summary' => [
                    'balance' => ContinuityFundEntry::balance(),
                    'percent' => ContinuityFund::percent(),
                    'max_months' => ContinuityFund::maxMonths(),
                    'recovery_days' => (int) config('kafala.continuity.recovery_days', 14),
                    'in_year' => $sum([FundEntryKind::Share->value, FundEntryKind::Donation->value], $year),
                    'out_year' => $sum([FundEntryKind::Grant->value], $year),
                    'children_helped' => ContinuityFundEntry::query()->where('kind', FundEntryKind::Grant)->distinct()->count('orphan_id'),
                ],
            ],
        ]);
    }

    /**
     * الكفالات المتعثّرة الآن: ما زالت محاولاتها جارية أو استُنفدت، وما يغطّيها
     * الصندوق منذ آخر دفعة. المتابعة هاتفاً أو بريداً تبدأ من هنا.
     */
    public function atRisk(Request $request, ContinuityFund $fund): JsonResponse
    {
        abort_unless($request->user()->can('disbursements.view'), 403);

        // من غطّاه الصندوق في الأشهر الأخيرة، ومن تعثّر قسطه الآن
        $coveredIds = ContinuityFundEntry::query()
            ->where('kind', FundEntryKind::Grant)
            ->where('created_at', '>=', now()->subMonths(ContinuityFund::maxMonths() + 1))
            ->distinct()->pluck('sponsorship_id')->filter();

        $rows = Sponsorship::query()
            ->where('status', SponsorshipStatus::Active)
            ->where(fn ($q) => $q->whereNotNull('payment_issue_since')->orWhereIn('id', $coveredIds))
            ->with(['sponsor:id,user_id,sponsor_code', 'sponsor.user:id,name,email,phone', 'orphan:id,first_name,reference_code'])
            ->orderBy('payment_issue_since')
            ->limit(200)
            ->get()
            ->map(function (Sponsorship $s) use ($fund): ?array {
                $covered = $fund->monthsCovered($s);
                if (! $s->hasPaymentIssue() && $covered === 0) {
                    return null;
                }

                return [
                    'id' => $s->id,
                    'reference_code' => $s->reference_code,
                    'state' => $s->hasPaymentIssue() ? 'failing' : 'covered',
                    'sponsor' => ['id' => $s->sponsor_id, 'name' => $s->sponsor?->user?->name, 'email' => $s->sponsor?->user?->email, 'phone' => $s->sponsor?->user?->phone],
                    'orphan' => $s->orphan ? ['id' => $s->orphan->id, 'name' => $s->orphan->first_name, 'reference_code' => $s->orphan->reference_code] : null,
                    'amount' => (float) $s->monthly_amount,
                    'card_last4' => $s->card_last4,
                    'since' => $s->payment_issue_since?->toDateString(),
                    'failures' => (int) $s->payment_failures,
                    'max_attempts' => Sponsorship::maxPaymentAttempts(),
                    'next_retry_at' => $s->next_retry_at?->toDateString(),
                    'next_due_at' => $s->next_due_at?->toDateString(),
                    // متى يغطّيه الصندوق إن لم يُحلّ: بعد مهلة الاستعادة من استحقاقه
                    'covers_on' => $s->hasPaymentIssue() ? $s->next_due_at?->copy()->addDays((int) config('kafala.continuity.recovery_days', 14))->toDateString() : null,
                    'months_covered' => $covered,
                    'reason' => $s->hasPaymentIssue()
                        ? $s->payments()->where('status', PaymentStatus::Failed)->latest('id')->value('failure_reason')
                        : null,
                ];
            })
            ->filter()
            ->values();

        return response()->json(['data' => $rows]);
    }

    /** تبرّعٌ للصندوق نفسه (لا لطفل): يدخل رصيده ويظهر في قيوده. */
    public function donate(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('disbursements.approve'), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'note.required' => 'اكتب من أين جاء التبرّع: به يُطابَق مع كشف البنك.',
            'received_on.before_or_equal' => 'تاريخ الوصول لا يكون في المستقبل.',
        ]);

        $entry = ContinuityFundEntry::create([
            'kind' => FundEntryKind::Donation,
            'amount_base' => round((float) $validated['amount'], 2),
            'period' => Carbon::parse($validated['received_on'])->startOfMonth()->toDateString(),
            'note' => $validated['note'],
            'recorded_by' => $request->user()->id,
        ]);

        activity('finance')
            ->performedOn($entry)
            ->causedBy($request->user())
            ->withProperties(['amount' => (float) $entry->amount_base, 'note' => $entry->note])
            ->log('سجّل تبرّعاً لصندوق الاستمرارية');

        return response()->json([
            'message' => sprintf('سُجّل التبرّع. رصيد الصندوق الآن %s.', number_format(ContinuityFundEntry::balance(), 2)),
            'data' => ['id' => $entry->id, 'balance' => ContinuityFundEntry::balance()],
        ], 201);
    }
}

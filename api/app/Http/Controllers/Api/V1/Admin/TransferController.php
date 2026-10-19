<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TransferStatus;
use App\Http\Controllers\Controller;
use App\Models\AssociationTransfer;
use App\Services\TransferPreparer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * التحويلات الشهرية للجمعيات الشريكة في اللوحة: مسوّدةٌ تجهّزها
 * TransferPreparer من الدفعات، فيرسلها المحاسب بمرجع الحوالة، ثم يؤكّد
 * وصولها. المُرسَل يظهر للكافل في «أين ذهب مالك» ولا يُعاد حسابه.
 *
 * العرض لمن يرى الصرف، والإرسال والتأكيد والتجهيز لمن يعتمده: مالٌ خارج.
 */
class TransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('disbursements.view'), 403);

        $request->validate([
            'status' => ['nullable', Rule::in(TransferStatus::values())],
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        $rows = $this->filtered($request)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->with('association:id,name_ar,country_id', 'association.country:id,name_ar')
            ->withCount('lines')
            ->orderByDesc('period')
            ->orderByRaw("FIELD(status, 'draft', 'sent', 'confirmed')")
            ->paginate($this->perPage($request, 25));

        $counts = $this->filtered($request)->selectRaw('status, count(*) as n, SUM(amount_base) as total')->groupBy('status')->get()->keyBy('status');

        return response()->json([
            'data' => collect($rows->items())->map(fn (AssociationTransfer $t) => $this->row($t))->all(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'status_counts' => $counts->map(fn ($r) => (int) $r->n),
                'status_totals' => $counts->map(fn ($r) => round((float) $r->total, 2)),
                'all_count' => (int) $counts->sum('n'),
                'periods' => AssociationTransfer::query()->selectRaw("DATE_FORMAT(period, '%Y-%m') as p")->distinct()->orderByDesc('p')->pluck('p'),
            ],
        ]);
    }

    public function show(Request $request, AssociationTransfer $transfer): JsonResponse
    {
        abort_unless($request->user()->can('disbursements.view'), 403);

        $transfer->load(['association:id,name_ar,country_id', 'association.country:id,name_ar', 'lines.orphan:id,reference_code,first_name', 'sender:id,name', 'confirmer:id,name']);

        return response()->json(['data' => [
            ...$this->row($transfer),
            'sent_by' => $transfer->sender?->name,
            'confirmed_by' => $transfer->confirmer?->name,
            'lines' => $transfer->lines->sortByDesc('amount_base')->values()->map(fn ($l) => [
                'orphan_id' => $l->orphan_id,
                'orphan' => $l->orphan ? "{$l->orphan->first_name} ({$l->orphan->reference_code})" : null,
                'payments_count' => $l->payments_count,
                'received' => (float) $l->received_base,
                'fee' => (float) $l->fee_base,
                'amount' => (float) $l->amount_base,
            ])->all(),
        ]]);
    }

    /** تجهيز مسوّدات شهرٍ مكتمل (والماضي إن لم يُحدَّد). */
    public function prepare(Request $request, TransferPreparer $preparer): JsonResponse
    {
        abort_unless($request->user()->can('disbursements.approve'), 403);

        $validated = $request->validate(['period' => ['nullable', 'date_format:Y-m']]);
        $month = isset($validated['period'])
            ? Carbon::createFromFormat('Y-m-d', $validated['period'].'-01')
            : now(config('kafala.display_timezone'))->subMonthNoOverflow();

        // الشهر الجاري لم يكتمل: تحويله أول الشهر التالي
        if ($month->copy()->startOfMonth()->greaterThanOrEqualTo(now()->startOfMonth())) {
            throw ValidationException::withMessages(['period' => 'الشهر الجاري لم يكتمل بعد. تُجهَّز تحويلاته أول الشهر التالي.']);
        }

        $r = $preparer->prepare($month);

        return response()->json([
            'message' => sprintf('جُهّزت تحويلات %s: %d جديدة، و%d مسوّدة حُدّثت%s.', $month->format('Y-m'), $r['created'], $r['rebuilt'], $r['locked'] ? "، و{$r['locked']} أُرسلت من قبل فلم تُمسّ" : ''),
            'data' => $r,
        ]);
    }

    /** الإرسال: بمرجع الحوالة، ومن هنا يراه الكافل. */
    public function send(Request $request, AssociationTransfer $transfer): JsonResponse
    {
        abort_unless($request->user()->can('disbursements.approve'), 403);
        abort_unless($transfer->status === TransferStatus::Draft, 422, 'هذا التحويل أُرسل من قبل.');
        abort_if((float) $transfer->amount_base <= 0, 422, 'تحويلٌ بلا مبلغ لا يُرسل.');

        $validated = $request->validate(['bank_reference' => ['required', 'string', 'min:3', 'max:80']], [
            'bank_reference.required' => 'اكتب مرجع الحوالة: به تطابق الجمعية ما وصلها.',
        ]);

        $transfer->update([
            'status' => TransferStatus::Sent,
            'bank_reference' => $validated['bank_reference'],
            'sent_at' => now(),
            'sent_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'سُجّل الإرسال. يراه الكفلاء الآن في سجلّ أطفالهم.', 'data' => $this->row($transfer->fresh(['association:id,name_ar,country_id', 'association.country:id,name_ar']))]);
    }

    /** تأكيد وصول الحوالة للجمعية. */
    public function confirm(Request $request, AssociationTransfer $transfer): JsonResponse
    {
        abort_unless($request->user()->can('disbursements.approve'), 403);
        abort_unless($transfer->status === TransferStatus::Sent, 422, 'يُؤكَّد الوصول بعد الإرسال.');

        $transfer->update(['status' => TransferStatus::Confirmed, 'confirmed_at' => now(), 'confirmed_by' => $request->user()->id]);

        return response()->json(['message' => 'تأكّد وصول التحويل للجمعية.', 'data' => $this->row($transfer->fresh(['association:id,name_ar,country_id', 'association.country:id,name_ar']))]);
    }

    /** @return Builder<AssociationTransfer> */
    private function filtered(Request $request): Builder
    {
        return AssociationTransfer::query()
            ->when($request->filled('period'), fn ($q) => $q->whereDate('period', $request->string('period')->toString().'-01'))
            ->when($request->filled('association_id'), fn ($q) => $q->where('association_id', $request->integer('association_id')));
    }

    /** @return array<string, mixed> */
    private function row(AssociationTransfer $t): array
    {
        return [
            'id' => $t->id,
            'reference_code' => $t->reference_code,
            'period' => $t->period->format('Y-m'),
            'association' => $t->association?->name_ar,
            'country' => $t->association?->country?->name_ar,
            'currency' => $t->currency,
            'received' => (float) $t->received_base,
            'fee' => (float) $t->fee_base,
            'amount' => (float) $t->amount_base,
            'children' => $t->lines_count ?? $t->lines()->count(),
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'bank_reference' => $t->bank_reference,
            'sent_at' => $t->sent_at?->toIso8601String(),
            'confirmed_at' => $t->confirmed_at?->toIso8601String(),
        ];
    }
}

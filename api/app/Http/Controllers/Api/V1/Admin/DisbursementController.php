<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDisbursementRequest;
use App\Http\Resources\DisbursementResource;
use App\Models\Disbursement;
use App\Models\DisbursementCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DisbursementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Disbursement::class);

        $query = Disbursement::query()
            ->with([
                'category:id,slug,name_ar,color,is_overhead',
                'orphan:id,reference_code,first_name',
                'recorder:id,name', 'approver:id,name',
            ])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('orphan_id'), fn ($q) => $q->where('orphan_id', $request->integer('orphan_id')))
            ->when($request->boolean('pending_only'), fn ($q) => $q->whereNull('approved_at'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('spent_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('spent_on', '<=', $request->date('to')));

        // toBase(): صفّ المجاميع ليس كياناً — لا مفاتيح له ولا علاقات،
        // وهيدرته موديلاً تجعل Eloquent يحاول تحميل علاقاته فيسقط الطلب
        $totals = (clone $query)
            ->toBase()
            ->selectRaw('COALESCE(SUM(amount_base), 0) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN approved_at IS NULL THEN amount_base ELSE 0 END), 0) as pending')
            ->first();

        $items = $query->latest('spent_on')->latest('id')->paginate($this->perPage($request, 25));

        return response()->json([
            'data' => DisbursementResource::collection($items->items()),
            'meta' => [
                'total' => $items->total(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'sum_all' => round((float) $totals->total, 2),
                'sum_pending' => round((float) $totals->pending, 2),
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Disbursement::class);

        return response()->json([
            'data' => DisbursementCategory::active()->get()->map(fn ($c): array => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name_ar,
                'color' => $c->color,
                'is_overhead' => $c->is_overhead,
            ]),
        ]);
    }

    public function store(StoreDisbursementRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['receipt', 'confirm_duplicate']);
        $base = config('kafala.base_currency');
        $rate = $data['currency'] === $base ? 1.0 : (float) $data['fx_rate'];

        /*
         * إعادة إرسال (شبكة بطيئة، تبويبان، نقرة ثانية) كانت تنشئ صرفين
         * متطابقين يُعتمدان واحداً واحداً. صرفٌ بالبند والطفل والمبلغ والتاريخ
         * نفسها سجّله الشخص نفسه في آخر ربع ساعة يُردّ إلا بتأكيد صريح.
         */
        if (! $request->boolean('confirm_duplicate')) {
            $twin = Disbursement::query()
                ->where('recorded_by', $request->user()->id)
                ->where('category_id', $data['category_id'])
                ->where('orphan_id', $data['orphan_id'] ?? null)
                ->where('amount', $data['amount'])
                ->whereDate('spent_on', $data['spent_on'])
                // البيان جزء من هوية الصرف: رسوم مدرستين بالمبلغ نفسه صرفان لا صرف مكرر
                ->where('description', $data['description'] ?? null)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->first();

            if ($twin !== null) {
                return response()->json([
                    'message' => 'سجّلت صرفاً مطابقاً قبل قليل. إن كان صرفاً ثانياً فعلاً فأكّد التسجيل.',
                    'duplicate_of' => $twin->id,
                ], 409);
            }
        }

        $disbursement = Disbursement::create([
            ...$data,
            'fx_rate' => $rate,
            'amount_base' => round((float) $data['amount'] * $rate, 2),
            'recorded_by' => $request->user()->id,
            'receipt_path' => $request->hasFile('receipt')
                ? $request->file('receipt')->store('receipts', config('kafala.uploads.disk'))
                : null,
        ]);

        $this->log($request, $disbursement, 'recorded', 'سجّل صرفاً');
        $this->flush($disbursement->spent_on?->year);

        return response()->json([
            'message' => 'سُجّل الصرف. يحتاج اعتماداً قبل أن يدخل تقارير الشفافية.',
            'data' => new DisbursementResource($disbursement->load(['category', 'orphan'])),
        ], 201);
    }

    /**
     * الاعتماد هو ما يُدخل الصرف في حلقة الشفافية على الموقع العام،
     * فهو الخطوة التي تحتاج عيناً ثانية — ولا يعتمده المسجِّل ولا من عدّله
     * (تفرضه DisbursementPolicy). والقفل يمنع اعتماده مرتين بنقرتين.
     */
    public function approve(Request $request, Disbursement $disbursement): JsonResponse
    {
        $this->authorize('approve', $disbursement);

        DB::transaction(function () use ($request, $disbursement): void {
            $locked = Disbursement::query()->lockForUpdate()->findOrFail($disbursement->id);
            abort_if($locked->approved_at !== null, 409, 'اعتُمد هذا الصرف من قبل.');

            $locked->update(['approved_by' => $request->user()->id, 'approved_at' => now()]);
        });

        $disbursement->refresh();
        $this->log($request, $disbursement, 'approved', 'اعتمد صرفاً');
        $this->flush($disbursement->spent_on?->year);

        return response()->json([
            'message' => 'اعتُمد الصرف. صار جزءاً من أرقام الشفافية المنشورة.',
            'data' => new DisbursementResource($disbursement->load(['category', 'orphan', 'approver'])),
        ]);
    }

    public function update(Request $request, Disbursement $disbursement): JsonResponse
    {
        $this->authorize('update', $disbursement);

        $validated = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:1000000'],
            'category_id' => ['sometimes', 'integer', 'exists:disbursement_categories,id'],
            'spent_on' => ['sometimes', 'date', 'before_or_equal:today'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $oldYear = $disbursement->spent_on?->year;
        $old = $disbursement->only(['amount', 'category_id', 'spent_on', 'description']);

        if (isset($validated['amount'])) {
            // السعر المحفوظ مع الصرف، أو المستخرَج من نسبة المبلغين لما قبل حفظه
            $amount = (float) $disbursement->amount;
            $rate = $disbursement->fx_rate !== null
                ? (float) $disbursement->fx_rate
                : ($amount > 0 ? (float) $disbursement->amount_base / $amount : 1.0);

            $validated['amount_base'] = round((float) $validated['amount'] * $rate, 2);
        }

        $disbursement->update([...$validated, 'updated_by' => $request->user()->id]);

        $this->log($request, $disbursement, 'updated', 'عدّل صرفاً', ['old' => $old]);
        $this->flush($oldYear, $disbursement->spent_on?->year);

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new DisbursementResource($disbursement->fresh()->load('category')),
        ]);
    }

    /** الإيصال لمن يراجع الصرف: كان مخزّناً ولا طريق إليه، فيعتمد المراجع ما لم يره. */
    public function receipt(Request $request, Disbursement $disbursement): StreamedResponse
    {
        $this->authorize('viewReceipt', $disbursement);

        $disk = Storage::disk(config('kafala.uploads.disk'));
        abort_unless($disbursement->receipt_path && $disk->exists($disbursement->receipt_path), 404, 'لا إيصال لهذا الصرف.');

        return $disk->response($disbursement->receipt_path);
    }

    /** @param array<string, mixed> $extra */
    private function log(Request $request, Disbursement $disbursement, string $event, string $text, array $extra = []): void
    {
        activity()->performedOn($disbursement)->causedBy($request->user())->event($event)
            ->withProperties([
                'attributes' => $disbursement->only(['amount', 'currency', 'amount_base', 'category_id', 'orphan_id', 'spent_on']),
                ...$extra,
            ])
            ->log($text);
    }

    /** أرقام الشفافية العامة مُخزَّنة في الكاش — أي تغيير في الصرف يُبطلها فوراً. */
    private function flush(?int ...$years): void
    {
        Cache::forget('transparency.proof');

        // سنة الصرف نفسه لا السنة الجارية وحدها: اعتماد صرفٍ من العام الماضي
        // كان يترك أرقام ذلك العام المنشورة قديمة
        foreach (array_unique(array_filter([now()->year, ...$years])) as $year) {
            Cache::forget("transparency.spending.{$year}");
        }
    }
}

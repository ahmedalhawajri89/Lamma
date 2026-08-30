<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDisbursementRequest;
use App\Http\Resources\DisbursementResource;
use App\Models\Disbursement;
use App\Models\DisbursementCategory;
use App\Services\TransparencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $items = $query->latest('spent_on')->latest('id')->paginate($request->integer('per_page', 25));

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

    public function categories(): JsonResponse
    {
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
        $data = $request->safe()->except('receipt');
        $rate = (float) ($data['fx_rate'] ?? 1.0);

        $disbursement = Disbursement::create([
            ...$data,
            'amount_base' => round((float) $data['amount'] * $rate, 2),
            'recorded_by' => $request->user()->id,
            'receipt_path' => $request->hasFile('receipt')
                ? $request->file('receipt')->store('receipts', config('kafala.uploads.disk'))
                : null,
        ]);

        $this->flush();

        return response()->json([
            'message' => 'سُجّل الصرف. يحتاج اعتماداً قبل أن يدخل تقارير الشفافية.',
            'data' => new DisbursementResource($disbursement->load(['category', 'orphan'])),
        ], 201);
    }

    /**
     * الاعتماد هو ما يُدخل الصرف في حلقة الشفافية على الموقع العام،
     * فهو الخطوة التي تحتاج عيناً ثانية — ولا يعتمد المسجِّل صرفه بنفسه
     * (تفرضه DisbursementPolicy).
     */
    public function approve(Request $request, Disbursement $disbursement): JsonResponse
    {
        $this->authorize('approve', $disbursement);

        $disbursement->update([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->flush();

        return response()->json([
            'message' => 'اعتُمد الصرف. صار جزءاً من أرقام الشفافية المنشورة.',
            'data' => new DisbursementResource($disbursement->fresh()->load(['category', 'orphan', 'approver'])),
        ]);
    }

    public function update(Request $request, Disbursement $disbursement): JsonResponse
    {
        $this->authorize('update', $disbursement);

        $validated = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'category_id' => ['sometimes', 'integer', 'exists:disbursement_categories,id'],
            'spent_on' => ['sometimes', 'date', 'before_or_equal:today'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['amount'])) {
            /**
             * سعر الصرف ليس عموداً على `disbursements` — هو محفوظ ضمنياً في
             * النسبة بين `amount` و`amount_base`. نستخرجه منها بدل تخمينه،
             * فيبقى تقييم الصرف بالعملة الأساس متسقاً بعد تعديل المبلغ.
             */
            $amount = (float) $disbursement->amount;
            $rate = $amount > 0 ? (float) $disbursement->amount_base / $amount : 1.0;

            $validated['amount_base'] = round((float) $validated['amount'] * $rate, 2);
        }

        $disbursement->update($validated);
        $this->flush();

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new DisbursementResource($disbursement->fresh()->load('category')),
        ]);
    }

    /** أرقام الشفافية العامة مُخزَّنة في الكاش — أي تغيير في الصرف يُبطلها فوراً. */
    private function flush(): void
    {
        Cache::forget('transparency.proof');
        Cache::forget('transparency.spending.'.now()->year);
        app(TransparencyService::class);
    }
}

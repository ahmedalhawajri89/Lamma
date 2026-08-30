<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Sponsorships\CancelSponsorship;
use App\Actions\Sponsorships\CreateSponsorship;
use App\Enums\SponsorshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SponsorshipResource;
use App\Models\Orphan;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SponsorshipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sponsorship::class);

        $items = Sponsorship::query()
            ->with([
                'orphan:id,reference_code,first_name,photo_path,date_of_birth,funded_percent',
                'sponsor', 'sponsor.user:id,name,email',
                'program',
            ])
            ->withCount('payments')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->integer('program_id')))
            ->when($request->boolean('zakat_only'), fn ($q) => $q->where('is_zakat', true))
            ->when($request->boolean('due_soon'), fn ($q) => $q->active()
                ->whereBetween('next_due_at', [now()->toDateString(), now()->addDays(7)->toDateString()]))
            ->when($request->filled('q'), fn ($q) => $q->where('reference_code', 'like', '%'.$request->string('q')->toString().'%'))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => SponsorshipResource::collection($items->items()),
            'meta' => ['total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage()],
        ]);
    }

    public function show(Sponsorship $sponsorship): JsonResponse
    {
        $this->authorize('view', $sponsorship);

        return response()->json([
            'data' => new SponsorshipResource($sponsorship->load([
                'orphan.family.country', 'sponsor.user', 'program',
                'payments' => fn ($q) => $q->latest('created_at')->limit(24),
            ])),
        ]);
    }

    /** إنشاء كفالة نيابةً عن كافل — لتسجيل التبرعات الواردة عبر البنك أو نقداً. */
    public function store(Request $request, CreateSponsorship $action): JsonResponse
    {
        $this->authorize('manage', Sponsorship::class);

        $validated = $request->validate([
            'sponsor_id' => ['required', 'integer', 'exists:sponsors,id'],
            'orphan_id' => ['required', 'integer', 'exists:orphans,id'],
            'monthly_amount' => ['nullable', 'numeric', 'min:5'],
            'is_zakat' => ['nullable', 'boolean'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:'.config('kafala.billing.max_billing_day')],
        ]);

        $sponsorship = $action->handle(
            sponsor: Sponsor::findOrFail($validated['sponsor_id']),
            orphan: Orphan::findOrFail($validated['orphan_id']),
            monthlyAmount: isset($validated['monthly_amount']) ? (float) $validated['monthly_amount'] : null,
            isZakat: (bool) ($validated['is_zakat'] ?? false),
            billingDay: $validated['billing_day'] ?? null,
        );

        return response()->json([
            'message' => 'أُنشئت الكفالة.',
            'data' => new SponsorshipResource($sponsorship->load(['orphan', 'sponsor.user', 'program'])),
        ], 201);
    }

    /**
     * الإيقاف المؤقت يبقي الكفالة «حيّة» في القيد الفريد ويبقيها محتسبةً في
     * تمويل الطفل — لأن الكافل ينوي العودة، ولا يجوز أن يظهر الطفل بلا كفالة
     * فيُعرض على كافل آخر ثم يعود الأول فلا يجد مكانه.
     */
    public function update(Request $request, Sponsorship $sponsorship): JsonResponse
    {
        $this->authorize('manage', Sponsorship::class);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([
                SponsorshipStatus::Active->value,
                SponsorshipStatus::Paused->value,
            ])],
            'monthly_amount' => ['sometimes', 'numeric', 'min:5'],
            'billing_day' => ['sometimes', 'integer', 'min:1', 'max:'.config('kafala.billing.max_billing_day')],
        ]);

        if (isset($validated['status'])) {
            $validated['paused_at'] = $validated['status'] === SponsorshipStatus::Paused->value ? now() : null;
        }

        $sponsorship->update($validated);

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new SponsorshipResource($sponsorship->fresh()->load(['orphan', 'sponsor.user', 'program'])),
        ]);
    }

    public function destroy(Request $request, Sponsorship $sponsorship, CancelSponsorship $action): JsonResponse
    {
        $this->authorize('cancel', $sponsorship);

        $action->handle($sponsorship, $request->string('reason')->toString() ?: null);

        return response()->json(['message' => 'أُلغيت الكفالة.']);
    }
}

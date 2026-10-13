<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Actions\Sponsorships\CancelSponsorship;
use App\Actions\Sponsorships\CreateSponsorship;
use App\Http\Controllers\Controller;
use App\Http\Resources\SponsorshipResource;
use App\Models\Orphan;
use App\Models\Sponsorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorshipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sponsor = $request->user()->sponsor;
        abort_if($sponsor === null, 403);

        $items = $sponsor->sponsorships()
            ->with(['orphan:id,reference_code,first_name,photo_path,date_of_birth,funded_percent', 'program'])
            ->withCount('payments')
            ->withSum('payments', 'amount_base')
            ->latest('started_at')
            ->paginate($this->perPage($request, 15));

        return response()->json([
            'data' => SponsorshipResource::collection($items->items()),
            'meta' => ['total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage()],
        ]);
    }

    public function store(Request $request, CreateSponsorship $action): JsonResponse
    {
        $sponsor = $request->user()->sponsor;
        abort_if($sponsor === null, 403, 'هذا الحساب ليس حساب كافل.');

        $validated = $request->validate([
            'orphan_reference' => ['required', 'string', 'exists:orphans,reference_code'],
            'monthly_amount' => ['nullable', 'numeric', 'min:5', 'max:5000'],
            'is_zakat' => ['nullable', 'boolean'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:'.config('kafala.billing.max_billing_day')],
        ], [
            'orphan_reference.exists' => 'لم نجد هذه الحالة.',
            'monthly_amount.min' => 'أقل مبلغ للكفالة ٥ دولارات شهرياً.',
            'billing_day.max' => 'يوم الفوترة بين ١ و٢٨ حتى لا يختفي القسط في فبراير.',
        ]);

        $orphan = Orphan::where('reference_code', $validated['orphan_reference'])->firstOrFail();

        $sponsorship = $action->handle(
            sponsor: $sponsor,
            orphan: $orphan,
            monthlyAmount: isset($validated['monthly_amount']) ? (float) $validated['monthly_amount'] : null,
            isZakat: (bool) ($validated['is_zakat'] ?? false),
            billingDay: $validated['billing_day'] ?? null,
        );

        return response()->json([
            'message' => 'أنشأنا كفالتك. تبدأ فعلياً بعد أول دفعة.',
            'data' => new SponsorshipResource($sponsorship->load(['orphan', 'program'])),
        ], 201);
    }

    public function destroy(Request $request, Sponsorship $sponsorship, CancelSponsorship $action): JsonResponse
    {
        $this->authorize('cancel', $sponsorship);

        $action->handle($sponsorship, $request->string('reason')->toString() ?: null);

        return response()->json([
            'message' => 'أُلغيت الكفالة. شكراً لما قدّمته، وسنؤمّن كافلاً بديلاً للطفل.',
        ]);
    }
}

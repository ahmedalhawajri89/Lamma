<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Actions\Payments\RecordPayment;
use App\Actions\Sponsorships\CancelSponsorship;
use App\Actions\Sponsorships\CreateSponsorship;
use App\Http\Controllers\Controller;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Http\Resources\SponsorshipResource;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Payments\PaymentGateway;
use App\Services\ChildLedgerService;
use App\Services\ContinuityFund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SponsorshipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sponsor = $request->user()->sponsor;
        abort_if($sponsor === null, 403);

        $items = $sponsor->sponsorships()
            ->with(['orphan:id,reference_code,first_name,gender,photo_path,date_of_birth,funded_percent', 'program'])
            ->withCount(['payments' => fn ($q) => $q->settled()])
            ->withSum(['payments' => fn ($q) => $q->settled()], 'amount_base')
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

    /**
     * إيقاف مؤقت واستئناف بيد الكافل.
     *
     * كان الخيار الوحيد في لوحته «إيقاف الكفالة» وهو إلغاءٌ نهائي، فمن
     * أراد استراحة شهرين خسر مكانه عند الطفل. الإيقاف يُبقي الطفل محجوزاً له
     * ولا يُخصم شيء، والاستئناف يبدأ من يوم الفوترة القادم لا من موعد فات.
     */
    public function update(Request $request, Sponsorship $sponsorship): JsonResponse
    {
        $this->authorize('pause', $sponsorship);

        $validated = $request->validate([
            'status' => ['required', Rule::in([SponsorshipStatus::Active->value, SponsorshipStatus::Paused->value])],
        ]);
        $target = SponsorshipStatus::from($validated['status']);

        $sponsorship = DB::transaction(function () use ($sponsorship, $target): Sponsorship {
            $locked = Sponsorship::query()->lockForUpdate()->findOrFail($sponsorship->id);

            $allowed = [
                SponsorshipStatus::Active->value => SponsorshipStatus::Paused,
                SponsorshipStatus::Paused->value => SponsorshipStatus::Active,
            ];
            abort_unless(
                ($allowed[$locked->status->value] ?? null) === $target,
                409,
                $locked->status === SponsorshipStatus::Pending
                    ? 'الكفالة لم تبدأ بعد. تبدأ بأول دفعة تصل.'
                    : 'حالة الكفالة تغيّرت. حدّث الصفحة.',
            );

            $locked->update($target === SponsorshipStatus::Paused
                ? ['status' => $target, 'paused_at' => now()]
                : ['status' => $target, 'paused_at' => null, 'next_due_at' => $locked->upcomingBillingDate()]);

            return $locked;
        });

        activity('sponsorships')
            ->performedOn($sponsorship)
            ->causedBy($request->user())
            ->withProperties(['status' => $target->value, 'by' => 'sponsor'])
            ->log($target === SponsorshipStatus::Paused ? 'أوقف الكافل كفالته مؤقتاً' : 'استأنف الكافل كفالته');

        return response()->json([
            'message' => $target === SponsorshipStatus::Paused
                ? 'أوقفنا الخصم مؤقتاً. يبقى الطفل محجوزاً لك، واستأنف متى شئت.'
                : 'استأنفت الكفالة. أول قسط في '.$sponsorship->next_due_at?->toDateString().'.',
            'data' => new SponsorshipResource($sponsorship->load(['orphan', 'program'])),
        ]);
    }

    /**
     * القسط المتعثّر ببطاقة جديدة: يُخصم بها القسط المستحق فوراً، وتُحفظ
     * للأشهر القادمة، وينتهي التعثّر. بمفتاح الشهر نفسه الذي يستعمله
     * billing:run، فلا يُخصم القسط مرتين إن التقيا.
     */
    public function updateCard(Request $request, Sponsorship $sponsorship, PaymentGateway $gateway, RecordPayment $record): JsonResponse
    {
        $this->authorize('updateCard', $sponsorship);

        $validated = $request->validate([
            'card_token' => ['required', 'string', 'max:120'],
            'card_brand' => ['nullable', 'string', 'max:20'],
            'card_last4' => ['nullable', 'digits:4'],
        ], ['card_token.required' => __('أكمل بيانات البطاقة.')]);

        abort_unless(
            // وما غطّاه الصندوق: يدفع الكافل قسطه القادم مقدّماً فيعود العدّ من جديد
            $sponsorship->status === SponsorshipStatus::Active
                && ($sponsorship->hasPaymentIssue() || $sponsorship->next_due_at?->lte(today()) || app(ContinuityFund::class)->monthsCovered($sponsorship) > 0),
            409,
            __('لا قسط متأخر على هذه الكفالة.'),
        );

        $key = $sponsorship->dueKey();

        // قسط هذا الموعد وصل من قبل (بيد المحاسب أو تشغيلٍ متزامن): لا خصم ثانٍ
        if (Payment::where('idempotency_key', $key)->where('status', PaymentStatus::Succeeded)->exists()) {
            $sponsorship->clearPaymentIssue();
            $sponsorship->save();
            abort(409, __('وصل قسط هذا الموعد من قبل. حدّث الصفحة.'));
        }
        $result = $gateway->charge($validated['card_token'], $sponsorship->cycleAmount(), $sponsorship->currency, $key);

        if (! $result->succeeded) {
            throw ValidationException::withMessages(['card_token' => $result->failureReason ?? __('رفض البنك العملية. جرّب بطاقة أخرى.')]);
        }

        $last4 = isset($validated['card_last4']) ? substr((string) $validated['card_last4'], -4) : null;

        DB::transaction(function () use ($sponsorship, $record, $key, $validated, $last4, $result): void {
            $record->handle(
                sponsorship: $sponsorship,
                idempotencyKey: $key,
                method: PaymentMethod::Card,
                cardBrand: $validated['card_brand'] ?? null,
                cardLast4: $last4,
                gatewayReference: $result->reference,
            );

            $sponsorship->forceFill([
                'payment_method' => PaymentMethod::Card->value,
                'gateway_payment_method' => $result->paymentMethod ?? $sponsorship->gateway_payment_method,
                'card_brand' => $validated['card_brand'] ?? null,
                'card_last4' => $last4,
            ])->save();
        });

        activity('sponsorships')
            ->performedOn($sponsorship)
            ->causedBy($request->user())
            ->withProperties(['by' => 'sponsor', 'card_last4' => $last4])
            ->log('حدّث الكافل بطاقته ودفع القسط المتعثّر');

        return response()->json([
            'message' => __('وصل القسط، شكراً لك. حفظنا البطاقة الجديدة للأشهر القادمة.'),
            'data' => new SponsorshipResource($sponsorship->fresh(['orphan', 'program'])),
        ]);
    }

    /** «أين ذهب مالك»: سجلّ الطفل شهراً بشهر (ChildLedgerService). */
    public function ledger(Request $request, Sponsorship $sponsorship, ChildLedgerService $ledger): JsonResponse
    {
        $this->authorize('viewLedger', $sponsorship);

        $months = min(24, max(3, $request->integer('months', 12)));

        return response()->json(['data' => $ledger->forSponsorship($sponsorship, $months)]);
    }

    public function destroy(Request $request, Sponsorship $sponsorship, CancelSponsorship $action): JsonResponse
    {
        $this->authorize('cancel', $sponsorship);

        // العمود 255 حرفاً: سببٌ أطول كان يُسقط الطلب فيظهر «الخدمة غير متاحة»
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']], [
            'reason.max' => 'اختصر السبب إلى 255 حرفاً.',
        ]);

        $action->handle($sponsorship, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'أُلغيت الكفالة. شكراً لما قدّمته، وسنؤمّن كافلاً بديلاً للطفل.',
        ]);
    }
}

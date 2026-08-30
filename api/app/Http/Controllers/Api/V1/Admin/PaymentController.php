<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Payments\RecordPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Sponsorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->with([
                'sponsor', 'sponsor.user:id,name,email',
                'sponsorship', 'sponsorship.orphan:id,reference_code,first_name,photo_path,date_of_birth,funded_percent',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->string('method')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->date('to')))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = $request->string('q')->toString();
                $w->where('reference_code', 'like', "%{$term}%")
                    ->orWhere('receipt_number', 'like', "%{$term}%");
            }));

        // المجاميع تُحسب على الاستعلام نفسه قبل التصفيح، لا على الصفحة الظاهرة —
        // «إجمالي المحصّل» يجب أن يعني الكل لا العشرين المعروضة
        // withoutEagerLoads() لازم: النسخة ترث with() من الاستعلام الأصلي،
        // فيحاول Eloquent تحميل العلاقات على صفٍّ يحمل COUNT و SUM فقط
        // بلا مفاتيحه الأجنبية — فيسقط الطلب.
        $totals = (clone $query)->settled()
            ->toBase()
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount_base), 0) as total')
            ->first();

        $payments = $query
            ->orderByRaw('COALESCE(paid_at, created_at) DESC')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => PaymentResource::collection($payments->items()),
            'meta' => [
                'total' => $payments->total(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'settled_count' => (int) $totals->count,
                'settled_amount' => round((float) $totals->total, 2),
            ],
        ]);
    }

    /** تسجيل دفعة يدوية — تحويل بنكي أو نقد وصل المكتب. */
    public function store(Request $request, RecordPayment $action): JsonResponse
    {
        $this->authorize('record', Payment::class);

        $validated = $request->validate([
            'sponsorship_id' => ['required', 'integer', 'exists:sponsorships,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in([PaymentMethod::BankTransfer->value, PaymentMethod::Cash->value])],
            'reference' => ['required', 'string', 'max:64'],
        ], [
            'method.in' => 'التسجيل اليدوي للتحويل البنكي والنقد فقط — دفعات البطاقات تأتي من البوابة.',
            'reference.required' => 'رقم الحوالة أو الإيصال مطلوب — وهو ما يمنع تسجيل الدفعة نفسها مرتين.',
        ]);

        $payment = $action->handle(
            sponsorship: Sponsorship::findOrFail($validated['sponsorship_id']),
            // مفتاح التفرّد من رقم الحوالة نفسه: تسجيلها مرتين يعيد الأولى ولا يخصم ثانيةً
            idempotencyKey: hash('sha256', 'manual-'.$validated['reference']),
            amount: isset($validated['amount']) ? (float) $validated['amount'] : null,
            method: PaymentMethod::from($validated['method']),
        );

        return response()->json([
            'message' => 'سُجّلت الدفعة.',
            'data' => new PaymentResource($payment->load('sponsorship.orphan')),
        ], 201);
    }

    /**
     * الاسترداد لا يحذف الدفعة ولا يعدّل مبلغها: يغيّر حالتها فقط.
     * حذف صفٍّ مالي يجعل التدقيق مستحيلاً.
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('refund', $payment);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $payment->update([
            'status' => PaymentStatus::Refunded,
            'failure_reason' => $validated['reason'],
        ]);

        return response()->json([
            'message' => 'سُجّل الاسترداد.',
            'data' => new PaymentResource($payment->fresh()),
        ]);
    }
}

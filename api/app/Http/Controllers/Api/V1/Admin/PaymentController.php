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
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use App\Actions\Payments\RefundPayment;
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
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                // المرجع الداخلي والإيصال ورقم الحوالة، واسم الكافل أو الطفل
                $w->where('reference_code', 'like', "%{$term}%")
                    ->orWhere('receipt_number', 'like', "%{$term}%")
                    ->orWhere('gateway_reference', 'like', "%{$term}%")
                    ->orWhereHas('sponsor.user', fn ($u) => $u->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('sponsorship.orphan', fn ($o) => $o->where('first_name', 'like', "%{$term}%"));
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
            'paid_at' => ['nullable', 'date', 'before_or_equal:today', 'after:-1 year'],
        ], [
            'method.in' => 'التسجيل اليدوي للتحويل البنكي والنقد فقط. دفعات البطاقات تأتي من البوابة.',
            'reference.required' => 'رقم الحوالة أو الإيصال مطلوب، وهو ما يمنع تسجيل الدفعة نفسها مرتين.',
            'paid_at.before_or_equal' => 'تاريخ الدفعة لا يكون في المستقبل.',
        ]);

        $sponsorship = Sponsorship::findOrFail($validated['sponsorship_id']);

        // كفالة منتهية لا تُقبل عليها دفعة: كانت تُقبل فيتقدّم موعد قسطٍ لن يأتي
        abort_unless($sponsorship->status->isLive(), 422, 'هذه الكفالة منتهية. لا تُسجَّل عليها دفعة.');

        // سهوٌ بصفر زائد (1000 بدل 100) يُوقف هنا لا في تقرير آخر السنة
        $amount = isset($validated['amount']) ? (float) $validated['amount'] : (float) $sponsorship->monthly_amount;
        if ($amount > (float) $sponsorship->monthly_amount * 12) {
            throw ValidationException::withMessages([
                'amount' => 'المبلغ يتجاوز اثني عشر قسطاً لهذه الكفالة. تأكد منه، أو سجّله على دفعات.',
            ]);
        }

        $reference = trim($validated['reference']);

        /*
         * مفتاح التفرّد من الكفالة والوسيلة والمرجع معاً. كان من المرجع وحده،
         * فإيصال نقدي رقمه «1» لكافلين مختلفين يعيد دفعة الأول ويردّ «سُجّلت»،
         * فيُستلم المال ولا يُسجَّل أبداً.
         */
        $key = hash('sha256', implode('|', ['manual', $sponsorship->id, $validated['method'], mb_strtolower($reference)]));

        if (($existing = Payment::where('idempotency_key', $key)->first()) !== null) {
            return response()->json([
                'message' => "هذه الحوالة مسجّلة من قبل ({$existing->reference_code}). لم تُسجَّل مرة ثانية.",
                'data' => new PaymentResource($existing->load('sponsorship.orphan')),
            ], 409);
        }

        $payment = $action->handle(
            sponsorship: $sponsorship,
            idempotencyKey: $key,
            amount: $amount,
            method: PaymentMethod::from($validated['method']),
            gatewayReference: $reference,
            paidAt: isset($validated['paid_at']) ? Carbon::parse($validated['paid_at'])->setTimeFrom(now()) : null,
            recordedBy: $request->user()->id,
        );

        activity()->performedOn($payment)->causedBy($request->user())->event('recorded')
            ->withProperties(['amount' => $amount, 'currency' => $payment->currency, 'method' => $validated['method'], 'reference' => $reference])
            ->log('سجّل دفعة يدوية');

        return response()->json([
            'message' => 'سُجّلت الدفعة.',
            'data' => new PaymentResource($payment->load('sponsorship.orphan')),
        ], 201);
    }

    /**
     * الاسترداد لا يحذف الدفعة ولا يعدّل مبلغها: يغيّر حالتها ويحفظ من ومتى
     * ولماذا، ويعيد موعد القسط شهراً (RefundPayment).
     */
    public function refund(Request $request, Payment $payment, RefundPayment $action): JsonResponse
    {
        $this->authorize('refund', $payment);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'reason.min' => 'اكتب سبب الاسترداد بوضوح، يبقى في سجل الدفعة.',
        ]);

        $payment = $action->handle($payment, $request->user(), $validated['reason']);

        return response()->json([
            'message' => 'سُجّل الاسترداد.',
            'data' => new PaymentResource($payment->fresh()),
        ]);
    }
}

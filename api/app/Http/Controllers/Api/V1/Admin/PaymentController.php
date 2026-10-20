<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\RefundPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Sponsorship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    private const SORTS = ['date', 'amount_base'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = $this->filtered($request)
            ->with([
                'sponsor', 'sponsor.user:id,name,email',
                'sponsorship', 'sponsorship.orphan:id,reference_code,first_name,photo_path,date_of_birth,funded_percent',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $sort = in_array($request->string('sort')->toString(), self::SORTS, true) ? $request->string('sort')->toString() : 'date';
        $dir = $request->string('dir')->toString() === 'asc' ? 'ASC' : 'DESC';

        $payments = $query
            ->when($sort === 'date', fn ($q) => $q->orderByRaw("COALESCE(paid_at, created_at) {$dir}"), fn ($q) => $q->orderBy('amount_base', $dir))
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request, 25));

        return response()->json([
            'data' => PaymentResource::collection($payments->items()),
            'meta' => [
                'total' => $payments->total(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                ...$this->summary($request),
            ],
        ]);
    }

    /**
     * ملخّص الفترة تحت الفلاتر نفسها (عدا الحالة): ما حُصّل، وما فشل، وما
     * استُرد، ونسبة النجاح، وكم فشلت ولم تُستدرك بعد. المجاميع على الاستعلام
     * كله لا على الصفحة الظاهرة.
     */
    private function summary(Request $request): array
    {
        $base = $this->filtered($request);

        $rows = (clone $base)->toBase()
            ->select('status', DB::raw('COUNT(*) as n'), DB::raw('COALESCE(SUM(amount_base), 0) as total'))
            ->groupBy('status')->get()->keyBy('status');

        $n = fn (string $s) => (int) ($rows[$s]->n ?? 0);
        $t = fn (string $s) => round((float) ($rows[$s]->total ?? 0), 2);
        $attempts = $n('succeeded') + $n('failed') + $n('refunded');

        return [
            'status_counts' => $rows->map(fn ($r) => (int) $r->n),
            'all_count' => $rows->sum('n'),
            'settled_count' => $n('succeeded'),
            'settled_amount' => $t('succeeded'),
            'failed_count' => $n('failed'),
            'failed_amount' => $t('failed'),
            'refunded_count' => $n('refunded'),
            'refunded_amount' => $t('refunded'),
            // المستردّة نجحت ثم رُدّت: من محاولات الدفع لا من فشلها
            'success_rate' => $attempts ? round(($n('succeeded') + $n('refunded')) / $attempts * 100) : null,
            'unrecovered_count' => (clone $base)->tap(fn ($q) => $this->unrecovered($q))->count(),
        ];
    }

    /**
     * فشلت ولم تُستدرك: دفعة فاشلة لكفالة ما زالت قائمة، ولم تأتِ بعدها دفعة
     * ناجحة على الكفالة نفسها. هذه ما يُتصل بالكافل بشأنها.
     */
    private function unrecovered($query)
    {
        return $query->where('payments.status', PaymentStatus::Failed)
            ->whereHas('sponsorship', fn ($s) => $s->whereIn('status', ['active', 'pending', 'paused']))
            ->whereNotExists(fn ($later) => $later->from('payments as later')
                ->whereColumn('later.sponsorship_id', 'payments.sponsorship_id')
                ->where('later.status', PaymentStatus::Succeeded->value)
                ->whereRaw('COALESCE(later.paid_at, later.created_at) > payments.created_at'));
    }

    /** الفلاتر المشتركة بين الجدول وملخّصه والتصدير، عدا الحالة */
    private function filtered(Request $request): Builder
    {
        return Payment::query()
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->string('method')))
            // الفترة بتاريخ الحدث: الدفع للناجحة، والمحاولة للفاشلة (paid_at فيها فارغ)
            ->when($request->filled('from'), fn ($q) => $q->whereRaw('COALESCE(paid_at, created_at) >= ?', [$this->localDay($request->input('from'))]))
            ->when($request->filled('to'), fn ($q) => $q->whereRaw('COALESCE(paid_at, created_at) <= ?', [$this->localDay($request->input('to'), end: true)]))
            ->when($request->boolean('unrecovered'), fn ($q) => $this->unrecovered($q))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                // المرجع الداخلي والإيصال ورقم الحوالة، واسم الكافل أو الطفل
                $w->where('reference_code', 'like', "%{$term}%")
                    ->orWhere('receipt_number', 'like', "%{$term}%")
                    ->orWhere('gateway_reference', 'like', "%{$term}%")
                    ->orWhereHas('sponsor.user', fn ($u) => $u->where('name', 'like', "%{$term}%"))
                    // رمز الكافل: من صفحة الكافل «كل دفعاته»
                    ->orWhereHas('sponsor', fn ($s) => $s->where('sponsor_code', $term))
                    ->orWhereHas('sponsorship.orphan', fn ($o) => $o->where('first_name', 'like', "%{$term}%"));
            }));
    }

    /**
     * تصدير المدفوعات بفلاترها: ما يحتاجه المحاسب لمطابقة كشف البنك والبوابة.
     * يُسجَّل في سجل النشاط، ولا يحمل إلا ما يلزم المطابقة.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = $this->filtered($request)
            ->with(['sponsor:id,user_id,sponsor_code', 'sponsor.user:id,name', 'sponsorship:id,reference_code,orphan_id', 'sponsorship.orphan:id,reference_code,first_name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByRaw('COALESCE(paid_at, created_at) ASC');

        activity('payments')
            ->causedBy($request->user())
            ->withProperties(['filters' => $request->only(['q', 'status', 'method', 'from', 'to', 'unrecovered']), 'rows' => (clone $query)->count()])
            ->log('صدّر المدفوعات');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['المرجع', 'الإيصال', 'التاريخ', 'الكافل', 'رمز الكافل', 'الطفل', 'الكفالة', 'المبلغ', 'العملة', 'بالدولار', 'الوسيلة', 'رقم الحوالة', 'الحالة', 'ملاحظة']);

            $query->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->reference_code,
                        $p->receipt_number,
                        ($p->paid_at ?? $p->created_at)?->timezone(config('kafala.display_timezone'))->format('Y-m-d H:i'),
                        $p->sponsor?->user?->name,
                        $p->sponsor?->sponsor_code,
                        $p->sponsorship?->orphan?->first_name,
                        $p->sponsorship?->reference_code,
                        number_format((float) $p->amount, 2, '.', ''),
                        $p->currency,
                        number_format((float) $p->amount_base, 2, '.', ''),
                        $p->method->label(),
                        $p->gateway_reference,
                        $p->status->label(),
                        $p->refund_reason ?? $p->failure_reason,
                    ]);
                }
            });

            fclose($out);
        }, 'payments-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
            // كفالةٌ بعملةٍ أخرى: سعر الصرف كما في كشف البنك، وإلا سعر اليوم
            'fx_rate' => ['nullable', 'numeric', 'min:0.000001', 'max:100000'],
        ], [
            'method.in' => 'التسجيل اليدوي للتحويل البنكي والنقد فقط. دفعات البطاقات تأتي من البوابة.',
            'reference.required' => 'رقم الحوالة أو الإيصال مطلوب، وهو ما يمنع تسجيل الدفعة نفسها مرتين.',
            'paid_at.before_or_equal' => 'تاريخ الدفعة لا يكون في المستقبل.',
        ]);

        $sponsorship = Sponsorship::findOrFail($validated['sponsorship_id']);

        // كفالة منتهية لا تُقبل عليها دفعة: كانت تُقبل فيتقدّم موعد قسطٍ لن يأتي
        abort_unless($sponsorship->status->isLive(), 422, 'هذه الكفالة منتهية. لا تُسجَّل عليها دفعة.');

        // سهوٌ بصفر زائد (1000 بدل 100) يُوقف هنا لا في تقرير آخر السنة
        // بعملة الكافل: تحويل كافلٍ بالجنيه يصل بالجنيه
        $amount = isset($validated['amount']) ? (float) $validated['amount'] : $sponsorship->chargeMonthly();
        if ($amount > $sponsorship->chargeMonthly() * 12) {
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
            fxRate: $sponsorship->isForeignCurrency() && isset($validated['fx_rate']) ? (float) $validated['fx_rate'] : null,
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

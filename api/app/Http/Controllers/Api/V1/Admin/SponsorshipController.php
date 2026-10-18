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
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SponsorshipController extends Controller
{
    private const SORTS = ['id', 'next_due_at', 'monthly_amount', 'started_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sponsorship::class);

        $user = $request->user();
        $money = $user->can('payments.view');
        $sort = in_array($request->string('sort')->toString(), self::SORTS, true) ? $request->string('sort')->toString() : 'id';
        // من لا يرى المال لا يرتّب به: الترتيب نفسه يكشف المبالغ
        if (! $money && in_array($sort, ['next_due_at', 'monthly_amount'], true)) {
            $sort = 'id';
        }
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $items = $this->filtered($request)
            ->with([
                'orphan:id,reference_code,first_name,last_name,photo_path,date_of_birth,funded_percent',
                'program',
            ])
            // الكافل وبياناته لمن يملك رؤية الكفلاء وحده
            ->when($user->can('sponsors.view'), fn ($q) => $q->with(['sponsor', 'sponsor.user:id,name,email']))
            ->withCount(['payments' => fn ($q) => $q->settled()])
            // «منتهية»: ملغاة ومكتملة معاً، فكلتاهما سجلٌّ مغلق
            ->when($request->filled('status'), fn ($q) => $request->string('status')->toString() === 'ended'
                ? $q->whereIn('status', [SponsorshipStatus::Cancelled, SponsorshipStatus::Completed])
                : $q->where('status', $request->string('status')))
            // الحيّة أولاً: كانت الصفحة الأولى كلها ملغاة، وما يحتاج متابعة في آخرها
            ->orderByRaw("CASE WHEN status IN ('active', 'pending', 'paused') THEN 0 ELSE 1 END")
            ->when($sort !== 'id', fn ($q) => $q->orderByRaw("{$sort} IS NULL")->orderBy($sort, $dir))
            ->latest('id')
            ->paginate($this->perPage($request, 20));

        return response()->json([
            'data' => SponsorshipResource::collection($items->items()),
            'meta' => [
                'total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
                ...$this->facets($request),
            ],
        ]);
    }

    /**
     * رأس الشاشة: عدد كل حالة، وما يحتاج متابعة (المتأخرة)، وما يُنتظر
     * تحصيله هذا الأسبوع. والمال لمن يملك رؤيته وحده.
     */
    private function facets(Request $request): array
    {
        $base = $this->filtered($request);
        $tz = config('kafala.display_timezone');
        $today = now($tz)->toDateString();
        $week = now($tz)->addDays(7)->toDateString();

        $counts = (clone $base)->toBase()->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')->pluck('aggregate', 'status')->map(fn ($n) => (int) $n);

        $out = [
            'status_counts' => [
                ...$counts->only(['active', 'paused', 'pending'])->all(),
                'ended' => ($counts['cancelled'] ?? 0) + ($counts['completed'] ?? 0),
            ],
            'all_count' => $counts->sum(),
            'overdue_count' => (clone $base)->whereIn('status', [SponsorshipStatus::Active, SponsorshipStatus::Pending])
                ->whereDate('next_due_at', '<', $today)->count(),
        ];

        if ($request->user()->can('payments.view')) {
            $dueWeek = (clone $base)->active()->whereBetween('next_due_at', [$today, $week])->get(['monthly_amount', 'interval_months']);
            $out['due_week_count'] = $dueWeek->count();
            // ما يُخصم فعلاً: القسط الربعي أو السنوي كاملاً لا الشهري
            $out['due_week_amount'] = round($dueWeek->sum(fn ($s) => (float) $s->monthly_amount * max(1, (int) $s->interval_months)), 2);
            $out['monthly_commitment'] = round((float) (clone $base)->active()->sum('monthly_amount'), 2);
        }

        return $out;
    }

    /** الفلاتر المشتركة بين الجدول وعدّاداته، عدا الحالة */
    private function filtered(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $user = $request->user();
        $tz = config('kafala.display_timezone');

        return Sponsorship::query()
            // المشرف يرى كفالات أيتام بلده وحده
            ->when(
                $user->assignedCountryId() !== null && ! $user->isAdmin(),
                fn ($q) => $q->whereHas('orphan.family', fn ($f) => $f->where('country_id', $user->assignedCountryId())),
            )
            // الحيّة كلها: بانتظار الدفع ونشطة وموقوفة (لقائمة الدفعة اليدوية)
            ->when($request->boolean('live'), fn ($q) => $q->whereIn('status', [SponsorshipStatus::Pending, SponsorshipStatus::Active, SponsorshipStatus::Paused]))
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->integer('program_id')))
            ->when($request->boolean('zakat_only'), fn ($q) => $q->where('is_zakat', true))
            ->when($request->boolean('due_soon'), fn ($q) => $q->active()
                // «اليوم» يوم المنظمة لا يوم UTC
                ->whereBetween('next_due_at', [now($tz)->toDateString(), now($tz)->addDays(7)->toDateString()]))
            // المتأخرة: موعد قسطها مضى وهي ما زالت حيّة
            ->when($request->boolean('overdue'), fn ($q) => $q->whereIn('status', [SponsorshipStatus::Active, SponsorshipStatus::Pending])
                ->whereDate('next_due_at', '<', now($tz)->toDateString()))
            ->when($request->filled('q'), function ($q) use ($request, $user): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                $q->where(function ($w) use ($term, $user): void {
                    $w->where('reference_code', 'like', "%{$term}%")
                        ->orWhereHas('orphan', fn ($o) => $o->where('first_name', 'like', "%{$term}%")->orWhere('reference_code', 'like', "%{$term}%"));
                    if ($user->can('sponsors.view')) {
                        $w->orWhereHas('sponsor.user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                            ->orWhereHas('sponsor', fn ($s) => $s->where('sponsor_code', $term));
                    }
                });
            });
    }

    public function show(Request $request, Sponsorship $sponsorship): JsonResponse
    {
        $this->authorize('view', $sponsorship);

        $user = $request->user();
        $sponsorship->load('orphan.family.country');

        abort_unless(
            $user->assignedCountryId() === null || $user->isAdmin()
                || $sponsorship->orphan?->family?->country_id === $user->assignedCountryId(),
            404,
        );

        if ($user->can('sponsors.view')) {
            $sponsorship->load('sponsor.user');
        } else {
            $sponsorship->load('sponsor');
        }

        return response()->json([
            'data' => new SponsorshipResource($sponsorship->load([
                'program',
                'payments' => fn ($q) => $q->orderByRaw('COALESCE(paid_at, created_at) DESC')->limit(24),
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

        $current = $sponsorship->status;

        // المنتهية والملغاة سجلٌّ مغلق: إحياؤها كان يتجاوز فحص التمويل والقفل
        abort_unless($current->isLive(), 422, 'هذه كفالة منتهية. لا تُعدَّل.');

        /*
         * الانتقالات المسموحة هنا: نشطة ⇄ موقوفة. أما «بانتظار الدفع» فتتفعّل
         * بأول دفعة وحدها (RecordPayment)، لا بحفظ نموذج: كان تعديل يوم الخصم
         * يرسل `active` فتتفعّل كفالة لم يُدفع لها شيء ويبدأ الخصم عليها.
         */
        $status = isset($validated['status']) ? SponsorshipStatus::from($validated['status']) : $current;
        if ($status !== $current) {
            $allowed = [
                SponsorshipStatus::Active->value => [SponsorshipStatus::Paused],
                SponsorshipStatus::Paused->value => [SponsorshipStatus::Active],
            ];
            abort_unless(
                in_array($status, $allowed[$current->value] ?? [], true),
                422,
                $current === SponsorshipStatus::Pending
                    ? 'الكفالة تتفعّل بأول دفعة تصل، لا من هنا.'
                    : 'لا يمكن نقل الكفالة إلى هذه الحالة.',
            );
        }

        DB::transaction(function () use ($sponsorship, $validated, $current, $status): void {
            $changes = [];

            if (isset($validated['monthly_amount']) && (float) $validated['monthly_amount'] !== (float) $sponsorship->monthly_amount) {
                // رفع المبلغ يمرّ بفحص الحاجة المتبقية نفسه الذي يمرّ به الإنشاء،
                // بقفل الطفل؛ وإلا صار ممولاً فوق حاجته والنسبة تُقصّ عند 100 فلا يُرى
                $orphan = Orphan::query()->lockForUpdate()->findOrFail($sponsorship->orphan_id);
                $others = (float) $orphan->sponsorships()
                    ->whereKeyNot($sponsorship->id)
                    ->whereIn('status', array_filter(SponsorshipStatus::cases(), fn ($s) => $s->countsTowardFunding()))
                    ->sum('monthly_amount');
                $room = round((float) $orphan->monthly_need - $others, 2);

                if ((float) $validated['monthly_amount'] > $room) {
                    throw ValidationException::withMessages([
                        'monthly_amount' => 'المبلغ يتجاوز ما تبقّى من حاجة الطفل ($'.number_format(max(0, $room), 2).').',
                    ]);
                }

                $changes['monthly_amount'] = $validated['monthly_amount'];
                $changes['share_percent'] = (int) round((float) $validated['monthly_amount'] / max(1, (float) $orphan->monthly_need) * 100);
            }

            if (isset($validated['billing_day']) && (int) $validated['billing_day'] !== $sponsorship->billing_day) {
                $changes['billing_day'] = (int) $validated['billing_day'];
            }

            if ($status !== $current) {
                $changes['status'] = $status;
                $changes['paused_at'] = $status === SponsorshipStatus::Paused ? now() : null;
            }

            /*
             * موعد القسط القادم يتبع يوم الخصم، ويكون في المستقبل دائماً: تغيير
             * اليوم كان لا يسري إلا بعد شهر، والاستئناف كان يُبقي موعداً قديماً
             * فيُخصم فوراً ويتأخر الجدول كله إلى الأبد.
             */
            if (isset($changes['billing_day']) || ($current === SponsorshipStatus::Paused && $status === SponsorshipStatus::Active)) {
                $changes['next_due_at'] = $sponsorship->upcomingBillingDate($changes['billing_day'] ?? null);
            }

            if ($changes !== []) {
                $sponsorship->update($changes);
            }

            if (isset($changes['monthly_amount'])) {
                $sponsorship->orphan->recalculateFunding();
            }
        });

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new SponsorshipResource($sponsorship->fresh()->load(['orphan', 'sponsor.user', 'program'])),
        ]);
    }

    public function destroy(Request $request, Sponsorship $sponsorship, CancelSponsorship $action): JsonResponse
    {
        $this->authorize('cancel', $sponsorship);

        // الموظف يذكر سبب الإلغاء: هو ما يُقرأ في السجل بعد سنة (والكافل في بوابته اختياري)
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [
            'reason.required' => 'اذكر سبب الإلغاء، يبقى في سجل الكفالة.',
            'reason.min' => 'وضّح سبب الإلغاء أكثر.',
        ]);

        $action->handle($sponsorship, $request->string('reason')->toString() ?: null);

        return response()->json(['message' => 'أُلغيت الكفالة.']);
    }
}

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
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sponsorship::class);

        $user = $request->user();

        $items = Sponsorship::query()
            ->with([
                'orphan:id,reference_code,first_name,photo_path,date_of_birth,funded_percent',
                'program',
            ])
            // الكافل وبياناته لمن يملك رؤية الكفلاء وحده: المشرف الميداني كان يتلقّى
            // مدينة الكافل وعملته وتفضيلات تواصله وإن غاب اسمه
            ->when($user->can('sponsors.view'), fn ($q) => $q->with(['sponsor', 'sponsor.user:id,name,email']))
            // والمشرف يرى كفالات أيتام بلده وحده
            ->when(
                $user->assignedCountryId() !== null && ! $user->isAdmin(),
                fn ($q) => $q->whereHas('orphan.family', fn ($f) => $f->where('country_id', $user->assignedCountryId())),
            )
            ->withCount('payments')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            // الحيّة كلها: بانتظار الدفع ونشطة وموقوفة (لقائمة الدفعة اليدوية)
            ->when($request->boolean('live'), fn ($q) => $q->whereIn('status', [SponsorshipStatus::Pending, SponsorshipStatus::Active, SponsorshipStatus::Paused]))
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->integer('program_id')))
            ->when($request->boolean('zakat_only'), fn ($q) => $q->where('is_zakat', true))
            ->when($request->boolean('due_soon'), fn ($q) => $q->active()
                ->whereBetween('next_due_at', [now()->toDateString(), now()->addDays(7)->toDateString()]))
            // المتأخرة: موعد قسطها مضى وهي ما زالت حيّة. هي ما يحتاج متابعة،
            // و«تستحق خلال أسبوع» لا تشملها
            ->when($request->boolean('overdue'), fn ($q) => $q->whereIn('status', [SponsorshipStatus::Active, SponsorshipStatus::Pending])
                ->whereDate('next_due_at', '<', now()->toDateString()))
            ->when($request->filled('q'), function ($q) use ($request, $user): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                $q->where(function ($w) use ($term, $user): void {
                    $w->where('reference_code', 'like', "%{$term}%")
                        ->orWhereHas('orphan', fn ($o) => $o->where('first_name', 'like', "%{$term}%")->orWhere('reference_code', 'like', "%{$term}%"));
                    if ($user->can('sponsors.view')) {
                        $w->orWhereHas('sponsor.user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                    }
                });
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => SponsorshipResource::collection($items->items()),
            'meta' => ['total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage()],
        ]);
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
                $day = $changes['billing_day'] ?? $sponsorship->billing_day;
                $candidate = now()->startOfDay()->setDay(min($day, now()->daysInMonth));
                $changes['next_due_at'] = ($candidate->isPast() ? now()->addMonthNoOverflow()->setDay($day) : $candidate)->toDateString();
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

        $action->handle($sponsorship, $request->string('reason')->toString() ?: null);

        return response()->json(['message' => 'أُلغيت الكفالة.']);
    }
}

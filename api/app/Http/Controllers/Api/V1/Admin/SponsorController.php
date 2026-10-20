<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SponsorshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SponsorResource;
use App\Models\Payment;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorController extends Controller
{
    /** الترتيب المسموح؛ الحقول المشتقّة تُحسب في الاستعلام نفسه */
    private const SORTS = ['id', 'total_given', 'monthly_commitment', 'last_payment_at', 'sponsoring_since'];

    /**
     * مرحلة الكافل، وكلٌّ في واحدة فقط بالأولوية: نشط، ثم موقوف، ثم بانتظار
     * الدفع، ثم سابق (كان له كفالة وانتهت)، ثم لم يكفل بعد (سجّل ولم يبدأ).
     * السابق ومن لم يبدأ هما من يُتواصل معهم، وكانوا مختلطين بالنشطين.
     */
    private function segment(Builder $q, string $segment): Builder
    {
        $has = fn (array $statuses) => fn ($s) => $s->whereIn('status', $statuses);
        $live = [SponsorshipStatus::Pending, SponsorshipStatus::Active, SponsorshipStatus::Paused];

        return match ($segment) {
            'active' => $q->whereHas('sponsorships', $has([SponsorshipStatus::Active])),
            'paused' => $q->whereHas('sponsorships', $has([SponsorshipStatus::Paused]))
                ->whereDoesntHave('sponsorships', $has([SponsorshipStatus::Active])),
            'pending' => $q->whereHas('sponsorships', $has([SponsorshipStatus::Pending]))
                ->whereDoesntHave('sponsorships', $has([SponsorshipStatus::Active, SponsorshipStatus::Paused])),
            'past' => $q->has('sponsorships')->whereDoesntHave('sponsorships', $has($live)),
            'never' => $q->doesntHave('sponsorships'),
            default => $q,
        };
    }

    /** الفلاتر المشتركة بين الجدول وعدّاداته، عدا المرحلة */
    private function filtered(Request $request): Builder
    {
        return Sponsor::query()
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                $w->where('sponsor_code', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
            }))
            ->when($request->filled('country_id'), fn ($q) => $q->where('country_id', $request->integer('country_id')))
            ->when($request->boolean('zakat_only'), fn ($q) => $q->where('prefers_zakat', true))
            // متأخر: له كفالة نشطة فات موعد قسطها
            ->when($request->boolean('overdue'), fn ($q) => $q->whereHas('sponsorships', fn ($s) => $s
                ->where('status', SponsorshipStatus::Active)
                ->whereDate('next_due_at', '<', now(config('kafala.display_timezone'))->toDateString())));
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sponsors.view'), 403);

        $today = now(config('kafala.display_timezone'))->toDateString();
        $segment = $request->string('segment')->toString();
        // الحالة القديمة «نشط فقط» تبقى تعمل للروابط المحفوظة
        if ($segment === '' && $request->boolean('active_only')) {
            $segment = 'active';
        }

        $query = $this->segment($this->filtered($request), $segment)
            ->with(['user:id,name,email,status,last_login_at', 'country'])
            ->withCount([
                'sponsorships',
                'sponsorships as active_sponsorships_count' => fn ($q) => $q->active(),
                'sponsorships as overdue_sponsorships_count' => fn ($q) => $q->active()->whereDate('next_due_at', '<', $today),
            ])
            ->withSum(['payments' => fn ($q) => $q->settled()], 'amount_base')
            ->withSum(['sponsorships as monthly_commitment' => fn ($q) => $q->active()], 'monthly_amount')
            ->withMax(['payments as last_payment_at' => fn ($q) => $q->settled()], 'paid_at');

        $sort = in_array($request->string('sort')->toString(), self::SORTS, true) ? $request->string('sort')->toString() : 'id';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';
        $column = $sort === 'total_given' ? 'payments_sum_amount_base' : $sort;

        $sponsors = $query
            // الفارغ آخراً في الاتجاهين: من لم يدفع بعد ليس «الأقل دفعاً»
            ->when($sort !== 'id', fn ($q) => $q->orderByRaw("{$column} IS NULL"))
            ->orderBy($column, $dir)
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request, 20));

        $base = $this->filtered($request);

        return response()->json([
            'data' => SponsorResource::collection($sponsors->items()),
            'meta' => [
                'total' => $sponsors->total(), 'current_page' => $sponsors->currentPage(), 'last_page' => $sponsors->lastPage(),
                'segment_counts' => collect(['active', 'paused', 'pending', 'past', 'never'])
                    ->mapWithKeys(fn ($seg) => [$seg => $this->segment(clone $base, $seg)->count()]),
                'all_count' => (clone $base)->count(),
                'overdue_count' => (clone $base)->whereHas('sponsorships', fn ($s) => $s->active()->whereDate('next_due_at', '<', $today))->count(),
                // الالتزام الشهري لمن في الفلاتر: ما يُتوقَّع تحصيله كل شهر
                'monthly_commitment' => round((float) Sponsorship::query()->active()
                    ->whereIn('sponsor_id', (clone $base)->select('id'))->sum('monthly_amount'), 2),
            ],
        ]);
    }

    public function show(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($request->user()->can('sponsors.view'), 403);

        $sponsor->load([
            'user:id,name,email,phone,status,last_login_at,created_at',
            'country',
            'sponsorships' => fn ($q) => $q->orderByRaw("FIELD(status, 'active', 'paused', 'pending') = 0")->latest('started_at'),
            'sponsorships.orphan:id,reference_code,first_name,last_name,photo_path,date_of_birth,funded_percent',
            'sponsorships.program',
        ])
            ->loadCount(['sponsorships as active_sponsorships_count' => fn ($q) => $q->active()])
            ->loadSum(['payments' => fn ($q) => $q->settled()], 'amount_base');

        // آخر الدفعات بحالاتها: الفاشلة والمستردّة جزءٌ من قصة الكافل
        $payments = Payment::query()->where('sponsor_id', $sponsor->id)
            ->with('sponsorship.orphan:id,reference_code,first_name,photo_path,date_of_birth,funded_percent')
            ->orderByRaw('COALESCE(paid_at, created_at) DESC')
            ->limit(12)->get();

        return response()->json([
            'data' => [
                ...(new SponsorResource($sponsor))->resolve($request),
                'monthly_commitment' => $sponsor->monthlyCommitment(),
                'payments_count' => Payment::where('sponsor_id', $sponsor->id)->settled()->count(),
                'last_login_at' => $sponsor->user?->last_login_at?->toIso8601String(),
                'recent_payments' => PaymentResource::collection($payments)->resolve(),
            ],
        ]);
    }

    public function update(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($request->user()->can('sponsors.manage'), 403);

        $validated = $request->validate([
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'prefers_zakat' => ['sometimes', 'boolean'],
            'is_anonymous' => ['sometimes', 'boolean'],
        ]);

        $sponsor->update($validated);

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new SponsorResource($sponsor->fresh()->load('user')),
        ]);
    }
}

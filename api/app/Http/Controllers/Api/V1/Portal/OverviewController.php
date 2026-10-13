<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Http\Resources\SponsorshipResource;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Sponsorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * رأس لوحة الكافل.
 *
 * كل رقم هنا كان نصاً مكتوباً في dashboard.html:
 * «أيتام تكفلهم ٢»، و«إجمالي ما قدّمته ‎$3240»، و«تقارير جديدة ٢»،
 * و«القسط القادم ٢٥ أغسطس». صارت كلها استعلامات — فتصدق مع كل كافل،
 * لا مع كافل واحد وهمي.
 */
class OverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $sponsor = $request->user()->sponsor;

        abort_if($sponsor === null, 403, 'هذا الحساب ليس حساب كافل.');

        $unseenReports = Report::query()
            ->unseenBy($sponsor->id)
            ->whereIn('orphan_id', $sponsor->sponsorships()->funded()->select('orphan_id'))
            ->count();

        $nextDue = Sponsorship::query()
            ->where('sponsor_id', $sponsor->id)
            ->active()
            ->whereNotNull('next_due_at')
            ->orderBy('next_due_at')
            ->first();

        return response()->json([
            'data' => [
                'sponsor' => [
                    'code' => $sponsor->sponsor_code,
                    'since' => $sponsor->sponsoring_since?->toDateString(),
                    'years' => $sponsor->sponsoring_since?->diffInYears(now()) ?? 0,
                ],

                'stats' => [
                    // من بدأت كفالتهم فعلاً: الحجز بانتظار التحويل ليس كفالة بعد
                    'orphans_sponsored' => $sponsor->sponsorships()->funded()->distinct()->count('orphan_id'),
                    'pending_count' => $sponsor->sponsorships()->where('status', \App\Enums\SponsorshipStatus::Pending)->count(),
                    'monthly_commitment' => $sponsor->monthlyCommitment(),

                    // كل ما قدّمه فعلاً — مجموع الدفعات الناجحة لا الالتزام المُعلَن
                    'total_given' => round((float) Payment::query()
                        ->where('sponsor_id', $sponsor->id)
                        ->settled()
                        ->sum('amount_base'), 2),

                    'payments_count' => Payment::where('sponsor_id', $sponsor->id)->settled()->count(),
                    'new_reports' => $unseenReports,
                ],

                'next_payment' => $nextDue === null ? null : [
                    'due_at' => $nextDue->next_due_at?->toDateString(),
                    // ما يُخصم فعلاً في ذلك الموعد: القسط الربعي أو السنوي كاملاً
                    'amount' => $nextDue->cycleAmount(),
                    'interval_months' => (int) $nextDue->interval_months,
                    'currency' => $nextDue->currency,
                ],

                'sponsorships' => SponsorshipResource::collection(
                    $sponsor->sponsorships()
                        ->live()
                        ->with(['orphan:id,reference_code,first_name,photo_path,date_of_birth,funded_percent', 'program'])
                        // الدفعات المكتملة وحدها: كانت الفاشلة والمستردّة تُحسب فيما «قدّمه له»
                        ->withCount(['payments' => fn ($q) => $q->settled()])
                        ->withSum(['payments' => fn ($q) => $q->settled()], 'amount_base')
                        ->orderBy('next_due_at')
                        ->get(),
                )->resolve(),

                // كفالات انتهت: تاريخه معها لا يختفي من لوحته
                'past_sponsorships' => SponsorshipResource::collection(
                    $sponsor->sponsorships()
                        ->whereIn('status', [\App\Enums\SponsorshipStatus::Cancelled, \App\Enums\SponsorshipStatus::Completed])
                        // حجزٌ سقط بلا دفع لم يكن كفالة: لا يُعرض كتاريخ
                        ->whereHas('payments', fn ($q) => $q->settled())
                        ->with(['orphan:id,reference_code,first_name,photo_path,date_of_birth,funded_percent', 'program'])
                        ->withSum(['payments' => fn ($q) => $q->settled()], 'amount_base')
                        ->latest('ended_at')
                        ->limit(10)
                        ->get(),
                )->resolve(),

                'latest_reports' => ReportResource::collection(
                    Report::query()
                        ->published()
                        ->whereIn('orphan_id', $sponsor->sponsorships()->funded()->select('orphan_id'))
                        ->with(['orphan:id,reference_code,first_name,photo_path'])
                        ->withCount(['views' => fn ($q) => $q->where('sponsor_id', $sponsor->id)])
                        ->latest('published_at')
                        ->limit(4)
                        ->get(),
                )->resolve(),
            ],
        ]);
    }
}

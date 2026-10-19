<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrphanStatus;
use App\Enums\ReportStatus;
use App\Models\FieldVisit;
use App\Models\Orphan;
use App\Models\OrphanDocument;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\StaffScope;

/**
 * لوحة المشرف الميداني: عمل الأسبوع في بلده.
 *
 * كانت أربعة أرقام وقائمة حالات عاجلة، ولا شيء مما يخرج المشرف لأجله:
 *  1. من يزور: من فات موعده، ومن لم يُزر قط، ومن يحلّ موعده هذا الأسبوع.
 *  2. تقارير الربع: كم طفلاً كُتب تقريره من أطفال بلده، وما أُرجع إليه
 *     بملاحظة المراجع، وكم يوماً بقي على نهاية الربع.
 *  3. ما رصدته الزيارات: الاحتياجات المعلَّمة في آخر ثلاثين يوماً.
 *  4. الملفات: ما ينتظر التوثيق، وما بقي مسوّدة، والمستندات المرفوضة.
 */
class SupervisorDashboardService
{
    private const LIVE = [OrphanStatus::Active, OrphanStatus::FullyFunded, OrphanStatus::PendingVerification];

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $scope = StaffScope::for($user);
        $tz = config('kafala.display_timezone');
        $now = Carbon::now($tz);
        $today = $now->toDateString();

        // نطاق المشرف: بلده، أو جمعيته لموظف الجمعية (StaffScope)
        $inCountry = fn (Builder $q) => $q->whereHas('family', fn ($f) => $scope->families($f));
        $children = fn () => $inCountry(Orphan::query()->whereIn('orphans.status', self::LIVE));

        return [
            'country' => $user->staffProfile?->assignedCountry?->name_ar,
            'children' => $children()->count(),
            'visits' => $this->visits($children, $now),
            'reports' => $this->reports($user, $children, $now),
            'flagged' => $this->flagged($inCountry, $now),
            'files' => [
                'drafts' => $inCountry(Orphan::where('status', OrphanStatus::Draft))->count(),
                'pending_verification' => $inCountry(Orphan::where('status', OrphanStatus::PendingVerification))->count(),
                'rejected_documents' => OrphanDocument::whereNotNull('rejected_at')->whereNull('verified_at')
                    ->whereHas('orphan', fn ($o) => $inCountry($o))->count(),
                'no_photo' => $children()->whereNull('photo_path')->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function visits(\Closure $children, Carbon $now): array
    {
        $today = $now->toDateString();
        $week = $now->copy()->addDays(7)->toDateString();

        $latest = fn (string $column) => FieldVisit::query()->select($column)
            ->whereColumn('field_visits.orphan_id', 'orphans.id')
            ->latest('visited_at')->latest('id')->limit(1);

        // الموعد القادم = ما حدّدته آخر زيارة، كما في خطة الزيارات
        $withDue = fn () => $children()->select('orphans.*')
            ->addSelect(['next_due' => $latest('next_visit_due'), 'last_visited_at' => $latest('visited_at')]);

        $count = fn (\Closure $f) => DB::query()->fromSub($f($withDue()), 'x')->count();
        $overdue = $count(fn ($q) => $q->having('next_due', '<', $today));
        $never = $count(fn ($q) => $q->havingNull('last_visited_at'));
        $thisWeek = $count(fn ($q) => $q->having('next_due', '>=', $today)->having('next_due', '<=', $week));

        // من يُزار أولاً: من لم يُزر قط، ثم الأقرب موعداً (والفائت أقرب من كل شيء)
        $next = $withDue()
            ->with(['family:id,city_id', 'family.city:id,name_ar'])
            ->orderByRaw('last_visited_at IS NOT NULL')
            ->orderBy('next_due')
            ->limit(6)
            ->get()
            ->map(fn (Orphan $o) => [
                'id' => $o->id,
                'name' => $o->first_name,
                'reference_code' => $o->reference_code,
                'photo_url' => $o->photo_path ? asset('storage/'.$o->photo_path) : null,
                'city' => $o->family?->city?->name_ar,
                'next_due' => $o->next_due,
                'days' => $o->next_due ? (int) $now->copy()->startOfDay()->diffInDays(Carbon::parse($o->next_due), false) : null,
                'last_visited_at' => $o->last_visited_at ? Carbon::parse($o->last_visited_at)->toIso8601String() : null,
            ])->values();

        $monthStart = $now->copy()->startOfMonth()->utc();

        return [
            'overdue' => $overdue,
            'never' => $never,
            'week' => $thisWeek,
            'next' => $next,
            'month_done' => FieldVisit::where('visited_at', '>=', $monthStart)
                ->whereHas('orphan', fn ($o) => $o->whereIn('id', $children()->select('orphans.id')))->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function reports(User $user, \Closure $children, Carbon $now): array
    {
        $year = $now->year;
        $quarter = (int) ceil($now->month / 3);
        $end = $now->copy()->setDate($year, $quarter * 3, 1)->endOfMonth();

        $ids = $children()->where('orphans.status', '!=', OrphanStatus::PendingVerification)->pluck('orphans.id');
        $byStatus = Report::where('period_year', $year)->where('period_quarter', $quarter)->whereIn('orphan_id', $ids)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $sent = (int) (($byStatus[ReportStatus::Submitted->value] ?? 0) + ($byStatus[ReportStatus::Approved->value] ?? 0) + ($byStatus[ReportStatus::Published->value] ?? 0));
        $drafts = (int) ($byStatus[ReportStatus::Draft->value] ?? 0);

        $returned = Report::where('supervisor_id', $user->id)->where('status', ReportStatus::Draft)->whereNotNull('returned_at')
            ->with('orphan:id,first_name,reference_code')->latest('returned_at')->limit(3)->get()
            ->map(fn (Report $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'orphan' => $r->orphan?->first_name,
                'note' => $r->review_note,
                'returned_at' => $r->returned_at?->toIso8601String(),
            ])->values();

        return [
            'label' => "الربع {$quarter} من {$year}",
            'year' => $year,
            'quarter' => $quarter,
            'days_left' => (int) $now->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()),
            'expected' => $ids->count(),
            'sent' => $sent,
            'published' => (int) ($byStatus[ReportStatus::Published->value] ?? 0),
            'drafts' => $drafts,
            'missing' => max(0, $ids->count() - $sent - $drafts),
            'returned' => $returned,
            'returned_count' => Report::where('supervisor_id', $user->id)->where('status', ReportStatus::Draft)->whereNotNull('returned_at')->count(),
        ];
    }

    /** الاحتياجات التي علّمتها الزيارات في آخر ثلاثين يوماً */
    private function flagged(\Closure $inCountry, Carbon $now): array
    {
        $visits = FieldVisit::query()
            ->where('visited_at', '>=', $now->copy()->subDays(30)->utc())
            ->whereNotNull('needs_flagged')->where('needs_flagged', '!=', '[]')
            ->whereHas('orphan', fn ($o) => $inCountry($o))
            ->with('orphan:id,first_name,reference_code,photo_path')
            ->latest('visited_at')
            ->get();

        $tally = $visits->flatMap(fn (FieldVisit $v) => $v->needs_flagged ?? [])->countBy()->sortDesc();

        return [
            'count' => $visits->count(),
            'by_need' => $tally->map(fn ($n, $need) => ['need' => $need, 'count' => $n])->values()->take(5),
            'recent' => $visits->take(4)->map(fn (FieldVisit $v) => [
                'orphan_id' => $v->orphan_id,
                'name' => $v->orphan?->first_name,
                'reference_code' => $v->orphan?->reference_code,
                'needs' => $v->needs_flagged,
                'visited_at' => $v->visited_at?->toIso8601String(),
            ])->values(),
        ];
    }
}

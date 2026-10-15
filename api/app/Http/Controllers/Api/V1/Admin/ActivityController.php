<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\User;
use App\Services\ActivityFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** سجل النشاط — غير قابل للتفاوض في نظام يتعامل مع أموال وأطفال. */
class ActivityController extends Controller
{
    public function index(Request $request, ActivityFormatter $formatter): JsonResponse
    {
        abort_unless($request->user()->can('activity.view'), 403);
        $this->validateFilters($request, $formatter);

        $activities = $this->filtered($request)
            ->with(['causer:id,name', 'subject'])
            ->latest()
            ->latest('id')
            ->paginate($this->perPage($request, 30));

        return response()->json([
            'data' => ActivityResource::collection($activities->items()),
            'meta' => [
                'total' => $activities->total(), 'current_page' => $activities->currentPage(), 'last_page' => $activities->lastPage(),
                // للفلاتر: الأنواع بأسمائها، ومن له أثر في السجل
                'subject_types' => collect($formatter->subjectTypes())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'groups' => collect(ActivityFormatter::GROUPS)->map(fn ($g, $key) => ['value' => $key, 'label' => $g['label']])->values(),
                'actors' => User::whereIn('id', Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id'))
                    ->orderBy('name')->get(['id', 'name']),
                ...$this->overview($request),
            ],
        ]);
    }

    /**
     * السجل كما هو مفلتراً، ملف CSV: لمراجعٍ خارجي أو لحفظه خارج المنصة.
     * والتصدير نفسه يُسجَّل، فمن أخرج السجل يظهر فيه.
     */
    public function export(Request $request, ActivityFormatter $formatter): StreamedResponse
    {
        abort_unless($request->user()->can('activity.view'), 403);
        $this->validateFilters($request, $formatter);

        $query = $this->filtered($request)->with(['causer:id,name', 'subject'])->latest()->latest('id')->limit(5000);

        activity()->causedBy($request->user())
            ->withProperties(['filters' => $request->only(['group', 'causer_id', 'from', 'to', 'sensitive', 'subject_type']), 'rows' => min(5000, (clone $query)->count())])
            ->log('صدّر سجل النشاط');

        return response()->streamDownload(function () use ($query, $formatter): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['الوقت', 'من', 'الفعل', 'النوع', 'السجل', 'التغييرات', 'السبب', 'حساس']);

            foreach ($query->get() as $a) {
                $f = $formatter->format($a);
                fputcsv($out, [
                    $a->created_at?->timezone(config('kafala.display_timezone'))->format('Y-m-d H:i'),
                    $f['actor'],
                    $f['action'],
                    $f['subject_label'],
                    $f['subject_name'],
                    collect($f['changes'])->map(fn ($c) => "{$c['label']}: ".($c['before'] ?? 'فارغ').' ← '.($c['after'] ?? 'فارغ'))->implode(' | '),
                    $f['note'],
                    $f['sensitive'] ? 'نعم' : '',
                ]);
            }
            fclose($out);
        }, 'activity-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validateFilters(Request $request, ActivityFormatter $formatter): void
    {
        $request->validate([
            'subject_type' => ['nullable', Rule::in(array_keys($formatter->subjectTypes()))],
            'group' => ['nullable', Rule::in(array_keys(ActivityFormatter::GROUPS))],
            'causer_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }

    /** السجل بكل فلاتره: أساس القائمة والتصدير */
    private function filtered(Request $request, array $except = []): Builder
    {
        $has = fn (string $key) => ! in_array($key, $except, true) && $request->filled($key);

        return Activity::query()
            ->when($has('subject_type'), fn ($q) => $q->where('subject_type', 'App\\Models\\'.$request->string('subject_type')->toString()))
            ->when($has('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($has('causer_id'), fn ($q) => $q->where('causer_id', $request->integer('causer_id')))
            ->when($has('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->when($has('group'), fn ($q) => $this->whereGroup($q, $request->string('group')->toString()))
            ->when(! in_array('sensitive', $except, true) && $request->boolean('sensitive'), fn ($q) => $this->whereSensitive($q))
            ->when($has('from'), fn ($q) => $q->where('created_at', '>=', $this->localDay($request->input('from'))))
            ->when($has('to'), fn ($q) => $q->where('created_at', '<=', $this->localDay($request->input('to'), end: true)));
    }

    private function whereGroup(Builder $q, string $group): Builder
    {
        $types = array_map(fn ($t) => 'App\\Models\\'.$t, ActivityFormatter::GROUPS[$group]['types']);

        return $q->where(function ($w) use ($types, $group): void {
            $w->whereIn('subject_type', $types);
            if ($group === 'system') {
                $w->orWhere('event', 'settings_updated');
            }
        });
    }

    private function whereSensitive(Builder $q): Builder
    {
        return $q->where(function ($w): void {
            $w->whereIn('subject_type', array_map(fn ($t) => 'App\\Models\\'.$t, ActivityFormatter::SENSITIVE_TYPES))
                ->orWhereIn('event', ActivityFormatter::SENSITIVE_EVENTS);
            foreach (ActivityFormatter::EXPORT_PATTERN as $pattern) {
                $w->orWhere('description', 'like', $pattern);
            }
        });
    }

    /**
     * نظرة عامة فوق السجل: كم إجراءً يومياً، ومن يعمل، وكم في كل قسم.
     * كلٌّ تحت الفلاتر الأخرى لا تحت نفسه: أعداد الأقسام لا تتبع القسم
     * المختار، والأيام لا تتبع الفترة (هي ما تُختار منه الفترة).
     *
     * @return array<string, mixed>
     */
    private function overview(Request $request): array
    {
        $tz = config('kafala.display_timezone');
        $today = Carbon::now($tz)->startOfDay();
        $since = $today->copy()->subDays(29);

        // اليوم بتوقيت المنظمة لا UTC: إجراء الساعة الواحدة ليلاً في غزة من يومه
        $offset = Carbon::now($tz)->format('P');
        $day = DB::connection()->getDriverName() === 'sqlite'
            ? "date(datetime(created_at, '".Carbon::now($tz)->utcOffset()." minutes'))"
            : "DATE(CONVERT_TZ(created_at, '+00:00', '{$offset}'))";

        $daily = $this->filtered($request, ['from', 'to'])
            ->where('created_at', '>=', $since->copy()->utc())
            ->selectRaw("{$day} as d, count(*) as n")
            ->groupBy('d')
            ->pluck('n', 'd');

        $days = collect(range(29, 0))->map(function ($ago) use ($today, $daily) {
            $d = $today->copy()->subDays($ago)->toDateString();

            return ['date' => $d, 'count' => (int) ($daily[$d] ?? 0)];
        })->values();

        $groupCounts = collect(ActivityFormatter::GROUPS)->map(
            fn ($g, $key) => $this->whereGroup($this->filtered($request, ['group', 'subject_type']), $key)->count()
        );

        $actors = $this->filtered($request, ['causer_id'])
            ->whereNotNull('causer_id')
            ->selectRaw('causer_id, count(*) as n')
            ->groupBy('causer_id')
            ->orderByDesc('n')
            ->limit(5)
            ->get();
        $names = User::whereIn('id', $actors->pluck('causer_id'))->pluck('name', 'id');

        return [
            'daily' => $days,
            'today_count' => $days->last()['count'],
            'week_count' => $days->slice(-7)->sum('count'),
            'group_counts' => $groupCounts,
            'all_count' => $this->filtered($request, ['group', 'subject_type'])->count(),
            'sensitive_count' => $this->whereSensitive($this->filtered($request, ['sensitive']))->count(),
            'top_actors' => $actors->map(fn ($a) => ['id' => (int) $a->causer_id, 'name' => $names[$a->causer_id] ?? 'حساب محذوف', 'count' => (int) $a->n])->values(),
        ];
    }
}

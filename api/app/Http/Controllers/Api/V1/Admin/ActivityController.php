<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Services\ActivityFormatter;
use Spatie\Activitylog\Models\Activity;

/** سجل النشاط — غير قابل للتفاوض في نظام يتعامل مع أموال وأطفال. */
class ActivityController extends Controller
{
    public function index(Request $request, ActivityFormatter $formatter): JsonResponse
    {
        abort_unless($request->user()->can('activity.view'), 403);

        $types = $formatter->subjectTypes();

        $request->validate([
            'subject_type' => ['nullable', Rule::in(array_keys($types))],
            'causer_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $activities = Activity::query()
            ->with(['causer:id,name', 'subject'])
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', 'App\\Models\\'.$request->string('subject_type')->toString()))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('causer_id'), fn ($q) => $q->where('causer_id', $request->integer('causer_id')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 30), 1), 100));

        return response()->json([
            'data' => ActivityResource::collection($activities->items()),
            'meta' => [
                'total' => $activities->total(), 'current_page' => $activities->currentPage(), 'last_page' => $activities->lastPage(),
                // للفلاتر: الأنواع بأسمائها، ومن له أثر في السجل
                'subject_types' => collect($types)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'actors' => User::whereIn('id', Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id'))
                    ->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/** سجل النشاط — غير قابل للتفاوض في نظام يتعامل مع أموال وأطفال. */
class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('activity.view'), 403);

        $activities = Activity::query()
            ->with('causer:id,name')
            ->when($request->filled('subject_type'), fn ($q) => $q->where(
                'subject_type',
                'App\\Models\\'.$request->string('subject_type')->toString(),
            ))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('causer_id'), fn ($q) => $q->where('causer_id', $request->integer('causer_id')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->latest()
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'data' => ActivityResource::collection($activities->items()),
            'meta' => ['total' => $activities->total(), 'current_page' => $activities->currentPage(), 'last_page' => $activities->lastPage()],
        ]);
    }
}

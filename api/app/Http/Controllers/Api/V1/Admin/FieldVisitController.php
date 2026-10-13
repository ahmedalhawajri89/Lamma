<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FieldVisitResource;
use App\Models\FieldVisit;
use App\Models\Orphan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldVisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('visits.view'), 403);

        // المشرف يرى زيارات بلده وحده، والعدّاد «متأخرة» يتبع القائمة نفسها
        $scoped = fn () => FieldVisit::query()->when(
            $request->user()->assignedCountryId() !== null && ! $request->user()->isAdmin(),
            fn ($q) => $q->whereHas(
                'orphan.family',
                fn ($f) => $f->where('country_id', $request->user()->assignedCountryId()),
            ),
        );

        $visits = $scoped()
            ->with(['orphan:id,reference_code,first_name', 'supervisor:id,name'])
            ->when($request->filled('orphan_id'), fn ($q) => $q->where('orphan_id', $request->integer('orphan_id')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->latest('visited_at')
            ->paginate($this->perPage($request, 20));

        return response()->json([
            'data' => FieldVisitResource::collection($visits->items()),
            'meta' => [
                'total' => $visits->total(),
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'overdue' => $scoped()->overdue()->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('visits.record'), 403);

        $validated = $request->validate([
            'orphan_id' => ['required', 'integer', 'exists:orphans,id'],
            'visited_at' => ['required', 'date', 'before_or_equal:now'],
            'findings' => ['required', 'string', 'min:10', 'max:4000'],
            'needs_flagged' => ['nullable', 'array'],
            'needs_flagged.*' => ['string', 'max:40'],
            'next_visit_due' => ['nullable', 'date', 'after:today'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'visited_at.before_or_equal' => 'لا تُسجَّل زيارة بتاريخ مستقبلي.',
            'findings.min' => 'اكتب ملاحظات الزيارة، فسطر واحد لا يوثّق شيئاً.',
        ]);

        // لا تُسجَّل زيارة ليتيم خارج منطقة المشرف (السياسة نفسها التي تحجب ملفه عنه)
        $this->authorize('view', Orphan::findOrFail($validated['orphan_id']));

        $visit = FieldVisit::create([
            ...$validated,
            'supervisor_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'سُجّلت الزيارة.',
            'data' => new FieldVisitResource($visit->load('orphan')),
        ], 201);
    }
}

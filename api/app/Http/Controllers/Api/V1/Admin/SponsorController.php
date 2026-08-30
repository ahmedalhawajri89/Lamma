<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SponsorResource;
use App\Models\Sponsor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sponsors.view'), 403);

        $sponsors = Sponsor::query()
            ->with(['user:id,name,email,status,last_login_at', 'country'])
            ->withCount([
                'sponsorships',
                'sponsorships as active_sponsorships_count' => fn ($q) => $q->active(),
            ])
            ->withSum(['payments' => fn ($q) => $q->settled()], 'amount_base')
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = $request->string('q')->toString();
                $w->where('sponsor_code', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
            }))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->when($request->boolean('zakat_only'), fn ($q) => $q->where('prefers_zakat', true))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => SponsorResource::collection($sponsors->items()),
            'meta' => ['total' => $sponsors->total(), 'current_page' => $sponsors->currentPage(), 'last_page' => $sponsors->lastPage()],
        ]);
    }

    public function show(Request $request, Sponsor $sponsor): JsonResponse
    {
        abort_unless($request->user()->can('sponsors.view'), 403);

        return response()->json([
            'data' => new SponsorResource($sponsor->load([
                'user:id,name,email,phone,status,last_login_at,created_at',
                'country',
                'sponsorships.orphan:id,reference_code,first_name,photo_path,date_of_birth,funded_percent',
                'sponsorships.program',
            ])),
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AssociationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssociationResource;
use App\Models\Association;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * الجمعيات الشريكة في اللوحة: تسجيلها، وتوثيقها، وإيقافها. للمدير وحده:
 * التوثيق هو ما يفتح نشر أطفال الجمعية للكفالة (Orphan::publishBlockers)،
 * والإيقاف يُخفيهم من الموقع.
 */
class AssociationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate(['status' => ['nullable', Rule::in(AssociationStatus::values())]]);

        $rows = $this->filtered($request)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->with('country:id,name_ar')
            ->withCount(['families', 'staff'])
            ->addSelect(['children_count' => DB::table('orphans')
                ->join('families', 'families.id', '=', 'orphans.family_id')
                ->whereColumn('families.association_id', 'associations.id')
                ->whereNull('orphans.deleted_at')
                ->selectRaw('count(*)')])
            // ما ينتظر قراراً أولاً، ثم الأحدث
            ->orderByRaw("FIELD(status, 'under_review', 'applied', 'verified', 'suspended')")
            ->latest('id')
            ->paginate($this->perPage($request, 20));

        $counts = $this->filtered($request)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return response()->json([
            'data' => AssociationResource::collection($rows->getCollection())->resolve(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'status_counts' => $counts,
                'all_count' => (int) $counts->sum(),
            ],
        ]);
    }

    public function show(Request $request, Association $association): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(['data' => new AssociationResource(
            $association->load(['country:id,name_ar', 'city:id,name_ar', 'verifier:id,name'])->loadCount(['families', 'staff']),
        )]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate($this->rules($request));
        $association = Association::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name_en'] ?? null, $validated['name_ar']),
            'status' => AssociationStatus::UnderReview,
        ]);

        return response()->json([
            'message' => 'سُجّلت الجمعية قيد المراجعة. وثّقها حين تكتمل أوراقها.',
            'data' => new AssociationResource($association->load('country:id,name_ar')),
        ], 201);
    }

    public function update(Request $request, Association $association): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $association->update($request->validate($this->rules($request, partial: true)));

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new AssociationResource($association->fresh()->load('country:id,name_ar')),
        ]);
    }

    /**
     * التوثيق: لا تُوثَّق جمعية لم توقّع سياسة حماية الطفل ولم تُقرّ بأن
     * أطفالها في أسرهم. يُسجَّلان هنا إن أكّدهما المدير مع التوثيق.
     */
    public function verify(Request $request, Association $association): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'safeguarding_signed' => ['sometimes', 'boolean'],
            'family_care_attested' => ['sometimes', 'boolean'],
        ]);

        $association->forceFill([
            'safeguarding_signed_at' => $association->safeguarding_signed_at ?? (! empty($validated['safeguarding_signed']) ? now() : null),
            'family_care_attested_at' => $association->family_care_attested_at ?? (! empty($validated['family_care_attested']) ? now() : null),
        ]);

        $missing = array_filter([
            $association->safeguarding_signed_at === null ? 'لم توقّع الجمعية سياسة حماية الطفل.' : null,
            $association->family_care_attested_at === null ? 'لم تُقرّ الجمعية بأن أطفالها في أسرهم.' : null,
        ]);
        if ($missing !== []) {
            throw ValidationException::withMessages(['status' => array_values($missing)]);
        }

        $association->forceFill([
            'status' => AssociationStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
            'suspension_reason' => null,
        ])->save();

        return response()->json([
            'message' => 'وُثّقت الجمعية. صار نشر أطفالها ممكناً.',
            'data' => new AssociationResource($association->fresh()->load('country:id,name_ar')),
        ]);
    }

    /** الإيقاف يُخفي أطفال الجمعية من الموقع، وتبقى كفالاتهم القائمة. */
    public function suspend(Request $request, Association $association): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']], [
            'reason.required' => 'اكتب سبب الإيقاف: يُسجَّل ويقرؤه من يراجع الجمعية بعدك.',
        ]);

        $association->update(['status' => AssociationStatus::Suspended, 'suspension_reason' => $validated['reason']]);

        return response()->json([
            'message' => 'أُوقفت الجمعية وأُخفي أطفالها من الموقع. كفالاتهم القائمة مستمرة.',
            'data' => new AssociationResource($association->fresh()->load('country:id,name_ar')),
        ]);
    }

    /** @return Builder<Association> */
    private function filtered(Request $request): Builder
    {
        return Association::query()
            ->when($request->filled('country_id'), fn ($q) => $q->where('country_id', $request->integer('country_id')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.addcslashes($request->string('q')->toString(), '%_\\').'%';
                $q->where(fn ($w) => $w->where('name_ar', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('registration_number', 'like', $term));
            });
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(Request $request, bool $partial = false): array
    {
        $req = $partial ? ['sometimes'] : ['required'];
        $opt = $partial ? ['sometimes', 'nullable'] : ['nullable'];

        return [
            'name_ar' => [...$req, 'string', 'min:3', 'max:160'],
            'name_en' => [...$opt, 'string', 'max:160'],
            'country_id' => [...$req, 'integer', 'exists:countries,id'],
            'city_id' => [...$opt, 'integer', Rule::exists('cities', 'id')->where('country_id', $request->integer('country_id'))],
            'registration_number' => [...$opt, 'string', 'max:60'],
            'registration_authority' => [...$opt, 'string', 'max:160'],
            'email' => [...$opt, 'email:rfc', 'max:120'],
            'phone' => [...$opt, 'string', 'max:30'],
            'website' => [...$opt, 'url', 'max:200'],
            'about_ar' => [...$opt, 'string', 'max:2000'],
            'about_en' => [...$opt, 'string', 'max:2000'],
            'transfer_details' => [...$opt, 'string', 'max:1000'],
            'transfer_currency' => [...$opt, 'string', 'size:3'],
        ];
    }

    /** رابط صفحة الجمعية من اسمها الإنجليزي، وإلا رقمٌ لا يتكرّر. */
    private function uniqueSlug(?string $nameEn, string $nameAr): string
    {
        $base = Str::slug((string) $nameEn) ?: 'association-'.substr(md5($nameAr.microtime()), 0, 6);
        $slug = $base;
        for ($i = 2; Association::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Orphans\VerifyOrphan;
use App\Enums\OrphanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrphanRequest;
use App\Http\Requests\Admin\UpdateOrphanRequest;
use App\Http\Resources\OrphanResource;
use App\Models\Family;
use App\Models\Orphan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrphanController extends Controller
{
    private const SORTS = [
        'created_at', 'funded_percent', 'monthly_need', 'date_of_birth', 'reference_code',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Orphan::class);

        $query = Orphan::query()
            ->with([
                'program',
                'family', 'family.country', 'family.city', 'family.guardian',
            ])
            ->withCount(['sponsorships', 'reports', 'documents'])
            ->search($request->string('q')->toString());

        /**
         * تقييد المشرف الميداني ببلده — على مستوى الاستعلام لا بعد جلبه.
         * الترشيح في PHP بعد الجلب يسرّب العدد الكلي في التصفيح، وهو تسريبٌ
         * حقيقي وإن بدا صغيراً.
         */
        if (($countryId = $request->user()->assignedCountryId()) !== null && ! $request->user()->isAdmin()) {
            $query->whereHas('family', fn ($q) => $q->where('country_id', $countryId));
        }

        $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->integer('program_id')))
            ->when($request->filled('country_id'), fn ($q) => $q->whereHas(
                'family',
                fn ($f) => $f->where('country_id', $request->integer('country_id')),
            ))
            ->when($request->boolean('urgent'), fn ($q) => $q->where('is_urgent', true))
            ->when($request->boolean('unfunded'), fn ($q) => $q->where('funded_percent', '<', 100))
            ->when($request->boolean('unverified'), fn ($q) => $q->whereNull('verified_at'));

        $sort = in_array($request->string('sort')->toString(), self::SORTS, true)
            ? $request->string('sort')->toString()
            : 'created_at';

        $orphans = $query
            ->orderBy($sort, $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return response()->json([
            'data' => OrphanResource::collection($orphans->items()),
            'meta' => [
                'total' => $orphans->total(),
                'per_page' => $orphans->perPage(),
                'current_page' => $orphans->currentPage(),
                'last_page' => $orphans->lastPage(),
            ],
        ]);
    }

    public function show(Orphan $orphan): JsonResponse
    {
        $this->authorize('view', $orphan);

        return response()->json([
            'data' => new OrphanResource($orphan->load([
                'program.features',
                'family.country', 'family.city', 'family.guardian',
                'documents',
                'sponsorships.sponsor.user:id,name,email',
                'reports' => fn ($q) => $q->latest('period_year')->latest('period_quarter'),
                'fieldVisits' => fn ($q) => $q->latest('visited_at')->limit(10),
                'fieldVisits.supervisor:id,name',
                'familyMembers',
            ])),
        ]);
    }

    public function store(StoreOrphanRequest $request, VerifyOrphan $action): JsonResponse
    {
        /**
         * السياسة كانت معرَّفة ولا تُستدعى.
         *
         * حراسة المسار `role:admin|accountant|field_supervisor` تمرّر المحاسبَ
         * إلى هنا، وهو لا يملك `orphans.create`. فكان بابٌ مفتوحاً على مصراعيه
         * خلف سياسةٍ مكتوبة لا تعمل — وهو أسوأ من غياب السياسة، لأنه يبدو
         * محروساً في المراجعة.
         */
        $family = Family::findOrFail($request->validated('family_id'));
        $this->authorize('create', [Orphan::class, $family]);

        $orphan = DB::transaction(function () use ($request): Orphan {
            $data = $request->safe()->except('photo');
            $data['reference_code'] = $this->nextReference();

            $orphan = Orphan::create($data);

            if ($request->hasFile('photo')) {
                $orphan->update([
                    'photo_path' => $request->file('photo')->store('orphans', 'public'),
                ]);
            }

            return $orphan;
        });

        return response()->json([
            'message' => 'أُنشئ الملف. ارفع مستندات التحقق ثم وثّقه لينشر.',
            'data' => new OrphanResource($orphan->load(['program', 'family'])),
        ], 201);
    }

    public function update(UpdateOrphanRequest $request, Orphan $orphan): JsonResponse
    {
        $this->authorize('update', $orphan);

        $data = $request->safe()->except('photo');

        if ($request->hasFile('photo')) {
            // الصورة القديمة تُحذف: تركها يراكم ملفات يتيمة على القرص
            if ($orphan->photo_path) {
                Storage::disk('public')->delete($orphan->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('orphans', 'public');
        }

        $orphan->update($data);

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new OrphanResource($orphan->fresh()->load(['program', 'family'])),
        ]);
    }

    public function verify(Orphan $orphan, VerifyOrphan $action): JsonResponse
    {
        $this->authorize('verify', $orphan);

        return response()->json([
            'message' => 'وُثّق الملف.',
            'data' => new OrphanResource($action->verify($orphan, request()->user())),
        ]);
    }

    public function publish(Orphan $orphan, VerifyOrphan $action): JsonResponse
    {
        $this->authorize('publish', $orphan);

        return response()->json([
            'message' => 'نُشر الملف على الموقع.',
            'data' => new OrphanResource($action->publish($orphan)),
        ]);
    }

    public function unpublish(Orphan $orphan, VerifyOrphan $action): JsonResponse
    {
        $this->authorize('publish', $orphan);

        return response()->json([
            'message' => 'أُخفي الملف عن الموقع.',
            'data' => new OrphanResource($action->unpublish($orphan)),
        ]);
    }

    /** أرشفة ناعمة — الحذف الصلب يكسر كل تقرير مالي وتاريخي مرتبط. */
    public function destroy(Orphan $orphan): JsonResponse
    {
        $this->authorize('delete', $orphan);

        $orphan->update(['status' => OrphanStatus::Withdrawn, 'published_at' => null]);
        $orphan->delete();

        return response()->json(['message' => 'أُرشف الملف. السجل المالي المرتبط به باقٍ كما هو.']);
    }

    private function nextReference(): string
    {
        $last = Orphan::withTrashed()->lockForUpdate()->max('reference_code');
        $next = $last ? ((int) substr($last, 4)) + 1 : 1042;

        return 'KFL-'.max(1042, $next);
    }
}

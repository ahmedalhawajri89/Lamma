<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\HousingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FamilyResource;
use App\Models\Country;
use App\Models\Family;
use App\Models\Guardian;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FamilyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('families.manage') || $request->user()->can('orphans.view'), 403);

        $query = Family::query()
            ->with(['country', 'city:id,name_ar', 'guardian'])
            ->withCount('orphans');

        if (($countryId = $request->user()->assignedCountryId()) !== null && ! $request->user()->isAdmin()) {
            $query->where('country_id', $countryId);
        }

        $families = $query
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = $request->string('q')->toString();
                $w->where('family_code', 'like', "%{$term}%")
                    ->orWhereHas('guardian', fn ($g) => $g->where('full_name', 'like', "%{$term}%"));
            }))
            ->when($request->filled('country_id'), fn ($q) => $q->where('country_id', $request->integer('country_id')))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => FamilyResource::collection($families->items()),
            'meta' => ['total' => $families->total(), 'current_page' => $families->currentPage(), 'last_page' => $families->lastPage()],
        ]);
    }

    public function show(Family $family): JsonResponse
    {
        return response()->json([
            'data' => new FamilyResource($family->load(['country', 'city', 'guardian', 'orphans.program'])),
        ]);
    }

    /**
     * الأسرة والوصي يُنشآن معاً في معاملة واحدة.
     * أسرةٌ بلا وصي تخرق المفتاح الأجنبي، ووصيٌّ بلا أسرة صفٌّ يتيم لا يُشار إليه.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('families.manage'), 403);

        $validated = $request->validate([
            'guardian.full_name' => ['required', 'string', 'min:3', 'max:120'],
            'guardian.relation' => ['required', Rule::in(GuardianRelation::values())],
            'guardian.phone' => ['nullable', 'string', 'max:24'],
            'guardian.national_id' => ['nullable', 'string', 'max:32'],
            'guardian.occupation' => ['nullable', 'string', 'max:80'],
            'guardian.monthly_income' => ['nullable', 'numeric', 'min:0', 'max:100000'],

            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:200'],
            'members_count' => ['required', 'integer', 'min:1', 'max:30'],
            'monthly_income' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'housing_status' => ['required', Rule::in(HousingStatus::values())],
        ], [
            'guardian.full_name.required' => 'اسم الوصي مطلوب.',
            'guardian.relation.required' => 'صلة الوصي بالطفل مطلوبة.',
            'country_id.required' => 'الدولة مطلوبة.',
        ]);

        $family = DB::transaction(function () use ($validated): Family {
            $guardian = new Guardian($validated['guardian']);

            // التجزئة داخل الموديل — الرقم الخام لا يصل قاعدة البيانات أبداً
            $guardian->setNationalId($validated['guardian']['national_id'] ?? null);
            $guardian->save();

            return Family::create([
                ...collect($validated)->except('guardian')->all(),
                'guardian_id' => $guardian->id,
                'family_code' => $this->nextCode(),
            ]);
        });

        return response()->json([
            'message' => 'أُنشئت الأسرة.',
            'data' => new FamilyResource($family->load(['country', 'city', 'guardian'])),
        ], 201);
    }

    public function update(Request $request, Family $family): JsonResponse
    {
        abort_unless($request->user()->can('families.manage'), 403);

        $validated = $request->validate([
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'city_id' => ['sometimes', 'nullable', 'integer', 'exists:cities,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:200'],
            'members_count' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'monthly_income' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'housing_status' => ['sometimes', Rule::in(HousingStatus::values())],
            'guardian.full_name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'guardian.relation' => ['sometimes', Rule::in(GuardianRelation::values())],
            'guardian.phone' => ['sometimes', 'nullable', 'string', 'max:24'],
            'guardian.occupation' => ['sometimes', 'nullable', 'string', 'max:80'],
            'guardian.monthly_income' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $family): void {
            $family->update(collect($validated)->except('guardian')->all());

            if (isset($validated['guardian'])) {
                $family->guardian->update($validated['guardian']);
            }
        });

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new FamilyResource($family->fresh()->load(['country', 'city', 'guardian'])),
        ]);
    }

    /**
     * بيانات مرجعية للنماذج: الدول ومدنها والبرامج.
     *
     * طلب واحد بدل ثلاثة، والواجهة تخزّنه: هذه بيانات تتغيّر مرات معدودة
     * في السنة، ولا معنى لجلبها عند كل فتح لنموذج.
     */
    public function references(): JsonResponse
    {
        return response()->json([
            'data' => [
                'countries' => Country::operational()
                    ->with(['cities:id,country_id,name_ar'])
                    ->get()
                    ->map(fn (Country $c): array => [
                        'id' => $c->id,
                        'name' => $c->name_ar,
                        'cities' => $c->cities->map(fn ($city): array => [
                            'id' => $city->id,
                            'name' => $city->name_ar,
                        ])->all(),
                    ]),

                'programs' => Program::active()->get()->map(fn (Program $p): array => [
                    'id' => $p->id,
                    'name' => $p->name_ar,
                    'price' => (float) $p->monthly_price,
                ]),

                'relations' => GuardianRelation::options(),
                'housing' => HousingStatus::options(),
                'genders' => Gender::options(),
                'document_types' => DocumentType::options(),
            ],
        ]);
    }

    private function nextCode(): string
    {
        $last = Family::query()->lockForUpdate()->max('family_code');
        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'FAM-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}

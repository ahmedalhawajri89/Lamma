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
use Illuminate\Validation\ValidationException;

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
            ->paginate($this->perPage($request, 20));

        return response()->json([
            'data' => FamilyResource::collection($families->items()),
            'meta' => ['total' => $families->total(), 'current_page' => $families->currentPage(), 'last_page' => $families->lastPage()],
        ]);
    }

    public function show(Request $request, Family $family): JsonResponse
    {
        abort_unless($request->user()->can('families.manage') || $request->user()->can('orphans.view'), 403);

        // المشرف الميداني لا يرى أسرة خارج بلده، وفيها بيانات الوصي وهاتفه.
        // 404 لا 403: لا نؤكّد وجود سجل لمن لا يحقّ له رؤيته
        abort_unless($this->inScope($request, $family->country_id), 404);

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
            // المدينة من الدولة المختارة: كان يُقبل حفظ أسرة في غزة بمدينة القاهرة
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('country_id', $request->integer('country_id'))],
            'address' => ['nullable', 'string', 'max:200'],
            'members_count' => ['required', 'integer', 'min:1', 'max:30'],
            'monthly_income' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'housing_status' => ['required', Rule::in(HousingStatus::values())],
        ], [
            'guardian.full_name.required' => 'اسم الوصي مطلوب.',
            'guardian.relation.required' => 'صلة الوصي بالطفل مطلوبة.',
            'country_id.required' => 'الدولة مطلوبة.',
            'city_id.exists' => 'هذه المدينة ليست في الدولة المختارة.',
        ]);

        $this->ensureCountryAllowed($request, (int) $validated['country_id']);

        /*
         * كشف التكرار الذي يَعِد به النموذج: الوصيّ نفسه برقم هويته مسجَّل في
         * أسرة أخرى. بلا هذا تُسجَّل الأسرة مرتين وتُموَّل مرتين.
         */
        if (! empty($validated['guardian']['national_id'])) {
            $probe = new Guardian();
            $probe->setNationalId($validated['guardian']['national_id']);
            $existing = Family::whereHas('guardian', fn ($g) => $g->where('national_id_hash', $probe->national_id_hash))->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'guardian.national_id' => "رقم الهوية هذا مسجَّل لوصيّ الأسرة {$existing->family_code}. افتحها بدل إنشاء أسرة جديدة.",
                ]);
            }
        }

        $family = DB::transaction(function () use ($validated): Family {
            /**
             * `national_id` يُستثنى من الإسناد الجماعي بقصد.
             *
             * الحقل ليس في `$fillable` لأن الرقم الخام لا يُخزَّن أبداً؛
             * `setNationalId` وحدها تكتب البصمة المُملَّحة. وتمريره في
             * المُنشئ كان يُسقط الطلب بـ`MassAssignmentException` تحت الوضع
             * الصارم — لكن فقط حين يرسله العميل، ولم يكن أي عميل يرسله قبل
             * أن يوجد هذا النموذج في اللوحة.
             */
            $guardian = new Guardian(collect($validated['guardian'])->except('national_id')->all());
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
        abort_unless($this->inScope($request, $family->country_id), 404);

        $validated = $request->validate([
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'city_id' => ['sometimes', 'nullable', 'integer', Rule::exists('cities', 'id')->where('country_id', $request->integer('country_id', $family->country_id))],
            'address' => ['sometimes', 'nullable', 'string', 'max:200'],
            'members_count' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'monthly_income' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'housing_status' => ['sometimes', Rule::in(HousingStatus::values())],
            'guardian.full_name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'guardian.relation' => ['sometimes', Rule::in(GuardianRelation::values())],
            'guardian.phone' => ['sometimes', 'nullable', 'string', 'max:24'],
            'guardian.occupation' => ['sometimes', 'nullable', 'string', 'max:80'],
            'guardian.monthly_income' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
        ], [
            'city_id.exists' => 'هذه المدينة ليست في الدولة المختارة.',
        ]);

        if (isset($validated['country_id'])) {
            $this->ensureCountryAllowed($request, (int) $validated['country_id']);
        }

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
        // الأكبر رقماً لا نصّاً، كي لا يتكرر الرمز بعد FAM-9999
        $next = (int) Family::query()->lockForUpdate()->max(DB::raw('CAST(SUBSTRING(family_code, 5) AS UNSIGNED)')) + 1;

        return 'FAM-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * المشرف الميداني مقيَّد ببلده المُسنَد، كما في `OrphanPolicy`.
     * المدير والمحاسب بلا بلد مُسنَد، فيريان الجميع.
     */
    private function inScope(Request $request, ?int $countryId): bool
    {
        $user = $request->user();
        $assigned = $user->assignedCountryId();

        return $assigned === null || $user->isAdmin() || $countryId === $assigned;
    }

    /** لا يُنشئ المشرف أسرة في بلد آخر، ولا ينقل أسرة إليه. */
    private function ensureCountryAllowed(Request $request, int $countryId): void
    {
        if (! $this->inScope($request, $countryId)) {
            throw ValidationException::withMessages([
                'country_id' => 'لا يمكنك إضافة أسرة أو نقلها إلى بلد خارج منطقتك.',
            ]);
        }
    }
}

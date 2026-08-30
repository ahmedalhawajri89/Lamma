<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicOrphanResource;
use App\Models\Orphan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الأيتام كما يراها الزائر.
 *
 * الفلترة والترتيب هنا تطابق ما تفعله `features.js` في المتصفح اليوم، لكن
 * على قاعدة البيانات: البحث في ثمانية صفوف في الذاكرة كان مقبولاً، وفي آلاف
 * الصفوف يعني تحميلها كلها إلى المتصفح لعرض عشرين منها.
 *
 * كل حقل فرز مُقيَّد بقائمة بيضاء: تمرير `sort` من الطلب مباشرة إلى
 * `orderBy` ثغرة حقن SQL كلاسيكية.
 */
class OrphanController extends Controller
{
    private const SORTS = [
        'urgent' => ['is_urgent', 'desc'],
        'funded_asc' => ['funded_percent', 'asc'],
        'funded_desc' => ['funded_percent', 'desc'],
        'age_asc' => ['date_of_birth', 'desc'],
        'age_desc' => ['date_of_birth', 'asc'],
        'newest' => ['published_at', 'desc'],
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'program' => ['nullable', 'string', 'exists:programs,slug'],
            'country' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', 'in:male,female'],
            'urgent' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('kafala.pagination.max')],
        ]);

        $query = Orphan::query()
            ->public()
            ->with(['program:id,slug', 'family:id,country_id,city_id', 'family.country:id,name_ar', 'family.city:id,name_ar'])
            ->search($validated['q'] ?? null);

        if (! empty($validated['program'])) {
            $query->whereHas('program', fn ($q) => $q->where('slug', $validated['program']));
        }

        if (! empty($validated['country'])) {
            $query->whereHas('family.country', fn ($q) => $q->where('name_ar', $validated['country']));
        }

        if (! empty($validated['gender'])) {
            $query->where('gender', $validated['gender']);
        }

        if ($request->boolean('urgent')) {
            $query->where('is_urgent', true);
        }

        [$column, $direction] = self::SORTS[$validated['sort'] ?? 'urgent'];
        $query->orderBy($column, $direction);

        // الترتيب الثانوي يجعل الصفحات متسقة: بلا مفتاح فاصل يقفز صفٌّ بين
        // صفحتين حين تتساوى قيمة الفرز، فيظهر مرتين أو يختفي
        $query->orderBy('id');

        $orphans = $query->paginate(
            $validated['per_page'] ?? config('kafala.pagination.default'),
        )->withQueryString();

        return response()->json([
            'data' => PublicOrphanResource::collection($orphans->items()),
            'meta' => [
                'total' => $orphans->total(),
                'per_page' => $orphans->perPage(),
                'current_page' => $orphans->currentPage(),
                'last_page' => $orphans->lastPage(),
            ],
        ]);
    }

    /** يُطلب بالمعرّف العلني `KFL-1042` لا بالمفتاح الرقمي. */
    public function show(string $reference): JsonResponse
    {
        $orphan = Orphan::query()
            ->public()
            ->with([
                'program:id,slug,name_ar,monthly_price,icon,accent',
                'family:id,country_id,city_id',
                'family.country:id,name_ar',
                'family.city:id,name_ar',
            ])
            ->where('reference_code', $reference)
            ->firstOrFail();

        return response()->json(['data' => new PublicOrphanResource($orphan)]);
    }
}

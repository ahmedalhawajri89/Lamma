<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicOrphanResource;
use App\Models\Association;
use App\Models\Country;
use App\Models\Orphan;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
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
        'urgent' => [['is_urgent', 'desc'], ['funded_percent', 'asc']],
        'funded_asc' => [['funded_percent', 'asc']],
        'funded_desc' => [['funded_percent', 'desc']],
        'monthly_asc' => [['monthly_need', 'asc']],
        'monthly_desc' => [['monthly_need', 'desc']],
        'age_asc' => [['date_of_birth', 'desc']],
        'age_desc' => [['date_of_birth', 'asc']],
        'newest' => [['published_at', 'desc']],
    ];

    /** فئات العمر كما يختار بها الكافل: [من، إلى] بالسنوات */
    private const AGES = ['0-5' => [0, 5], '6-10' => [6, 10], '11-18' => [11, 18]];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'program' => ['nullable', 'string', 'exists:programs,slug'],
            // أطفال جمعية بعينها (صفحة الجمعية /partners/{slug})
            'association' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', 'in:male,female'],
            'age' => ['nullable', 'in:'.implode(',', array_keys(self::AGES))],
            'urgent' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('kafala.pagination.max')],
            'facets' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'boolean'],
        ]);

        $query = $this->filtered($request)
            ->withSponsorCount()
            ->with(['program:id,slug', 'family:id,country_id,city_id,association_id,care_arrangement', 'family.association:id,slug,name_ar,name_en,status,verified_at', 'family.country:id,name_ar,name_en', 'family.city:id,name_ar,name_en']);

        foreach (self::SORTS[$validated['sort'] ?? 'urgent'] as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

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
                // أعداد كل خيار تحت الفلاتر الأخرى: «بنات (12)» قبل أن يُضغط
                ...($request->boolean('facets') ? ['facets' => $this->facets($request)] : []),
            ],
        ]);
    }

    /**
     * الأطفال المعروضون بفلاترهم، إلا ما في `$except`: أساس القائمة وأعداد
     * كل فلتر (عدد «سوريا» يُحسب تحت البرنامج والعمر المختارين لا تحت الدولة).
     *
     * @param  array<int, string>  $except
     * @return Builder<Orphan>
     */
    private function filtered(Request $request, array $except = []): Builder
    {
        $has = fn (string $k) => ! in_array($k, $except, true) && $request->filled($k);

        return Orphan::query()
            // من يقبل كفالة الآن وحده: المكفول بالكامل لا يُعرض بزرّ «اكفل» يُرفض
            ->seekingSponsor()
            ->publicSearch($has('q') ? $request->string('q')->toString() : null)
            ->when($has('program'), fn ($q) => $q->whereHas('program', fn ($p) => $p->where('slug', $request->string('program'))))
            ->when($has('association'), fn ($q) => $q->whereHas('family.association', function ($a) use ($request): void {
                /** @var Builder<Association> $a */
                $a->verified()->where('slug', $request->string('association'));
            }))
            ->when($has('country'), fn ($q) => $q->whereHas('family.country', fn ($c) => $c->where('name_ar', $request->string('country'))))
            ->when($has('gender'), fn ($q) => $q->where('gender', $request->string('gender')))
            ->when($has('age'), function ($q) use ($request) {
                [$min, $max] = self::AGES[$request->string('age')->toString()];
                // عمرٌ بين min وmax سنة كاملة: وُلد بعد (اليوم − max − 1) وقبل (اليوم − min)
                $q->where('date_of_birth', '>', now()->subYears($max + 1)->toDateString())
                    ->where('date_of_birth', '<=', now()->subYears($min)->toDateString());
            })
            ->when(! in_array('urgent', $except, true) && $request->boolean('urgent'), fn ($q) => $q->where('is_urgent', true))
            // لصدر الصفحة: من لهم صورة وحدهم
            ->when($request->boolean('photo'), fn ($q) => $q->whereNotNull('photo_path'));
    }

    /** @return array<string, mixed> */
    private function facets(Request $request): array
    {
        $programs = $this->filtered($request, ['program'])
            ->join('programs', 'programs.id', '=', 'orphans.program_id')
            ->selectRaw('programs.slug as k, count(*) as n')->groupBy('programs.slug')->pluck('n', 'k');

        $countries = $this->filtered($request, ['country'])
            ->join('families', 'families.id', '=', 'orphans.family_id')
            ->join('countries', 'countries.id', '=', 'families.country_id')
            ->selectRaw('countries.name_ar as k, count(*) as n')->groupBy('countries.name_ar')->orderByDesc('n')->pluck('n', 'k');

        $genders = $this->filtered($request, ['gender'])->selectRaw('gender as k, count(*) as n')->groupBy('gender')->pluck('n', 'k');

        $ages = collect(self::AGES)->map(function ($range, $key) use ($request) {
            [$min, $max] = $range;

            return $this->filtered($request, ['age'])
                ->where('date_of_birth', '>', now()->subYears($max + 1)->toDateString())
                ->where('date_of_birth', '<=', now()->subYears($min)->toDateString())
                ->count();
        });

        return [
            'program' => $programs,
            'country' => $countries,
            // قيمة الفلتر الاسم العربي (ثابتة لكل اللغات)، والمعروض بلغة الصفحة
            'country_labels' => Country::whereIn('name_ar', $countries->keys())->get(['name_ar', 'name_en'])
                ->mapWithKeys(fn ($c) => [$c->name_ar => Locale::pick($c->name_ar, $c->name_en)]),
            'gender' => $genders,
            'age' => $ages,
            'urgent' => $this->filtered($request, ['urgent'])->where('is_urgent', true)->count(),
            'all' => Orphan::query()->seekingSponsor()->count(),
        ];
    }

    /** يُطلب بالمعرّف العلني `KFL-1042` لا بالمفتاح الرقمي. */
    public function show(string $reference): JsonResponse
    {
        // public لا seekingSponsor: رابط طفلٍ اكتملت كفالته يفتح صفحته وتقول ذلك
        $orphan = Orphan::query()
            ->public()
            ->withSponsorCount()
            ->with([
                'program:id,slug,name_ar,name_en,monthly_price,icon,accent',
                'family:id,country_id,city_id,association_id,care_arrangement', 'family.association:id,slug,name_ar,name_en,status,verified_at',
                'family.country:id,name_ar,name_en',
                'family.city:id,name_ar,name_en',
            ])
            ->where('reference_code', $reference)
            ->firstOrFail();

        return response()->json(['data' => new PublicOrphanResource($orphan)]);
    }
}

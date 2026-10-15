<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ContactStatus;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إدارة المحتوى التحريري.
 *
 * كل ما كان مصفوفة في data.js — المقالات والأسئلة والشهادات والشركاء —
 * صار قابلاً للتحرير هنا. الفرق العملي: تصحيح خطأ إملائي في سؤال شائع
 * كان يحتاج تعديل ملف ونشراً، وصار يحتاج ضغطة زر.
 */
class ContentController extends Controller
{
    public function posts(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $base = Post::query()
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                $w->where('title', 'like', "%{$term}%")->orWhere('excerpt', 'like', "%{$term}%");
            }));

        $posts = (clone $base)
            ->with(['category:id,name_ar', 'author:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('created_at')
            ->paginate($this->perPage($request, 20));

        $counts = (clone $base)->toBase()->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as n'))
            ->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'total' => $posts->total(), 'current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(),
                'status_counts' => $counts,
                'all_count' => $counts->sum(),
                // للقائمة المنسدلة في المحرّر، والحالات من الـ enum نفسه
                'categories' => \App\Models\PostCategory::orderBy('sort_order')->get(['id', 'name_ar'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name_ar]),
                'statuses' => PostStatus::options(),
            ],
        ]);
    }

    /** مقالٌ واحد بنصّه كاملاً، لصفحة المحرّر */
    public function showPost(Request $request, Post $post): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        return response()->json([
            'data' => new PostResource($post->load(['category:id,name_ar', 'author:id,name'])),
            'meta' => [
                'categories' => \App\Models\PostCategory::orderBy('sort_order')->get(['id', 'name_ar'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name_ar]),
                'statuses' => PostStatus::options(),
            ],
        ]);
    }

    public function storePost(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:600'],
            'body' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:post_categories,id'],
            'read_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'status' => ['required', Rule::in(PostStatus::values())],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.config('kafala.uploads.max_size_kb')],
        ]);

        $post = Post::create([
            ...$this->withPublishDate(collect($validated)->except('cover')->all()),
            'slug' => $this->uniqueSlug($validated['title']),
            'author_id' => $request->user()->id,
            'cover_path' => $request->hasFile('cover')
                ? $request->file('cover')->store('posts', 'public')
                : null,
        ]);

        $this->audit($request, $post, 'created', 'أنشأ مقالاً', ['title' => $post->title, 'status' => $post->status->value]);

        return response()->json([
            'message' => 'أُنشئ المقال.',
            'data' => new PostResource($post->load('category')),
        ], 201);
    }

    public function updatePost(Request $request, Post $post): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:600'],
            'body' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:post_categories,id'],
            'read_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'status' => ['sometimes', Rule::in(PostStatus::values())],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],
            'cover' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.config('kafala.uploads.max_size_kb')],
        ]);

        $before = ['title' => $post->title, 'status' => $post->status->value, 'category_id' => $post->category_id];

        // استبدال الغلاف: كان يُضبط عند الإنشاء وحده. والقديم يُحذف كي لا تتراكم ملفات بلا صاحب
        if ($request->hasFile('cover')) {
            if ($post->cover_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($post->cover_path);
            }
            $post->cover_path = $request->file('cover')->store('posts', 'public');
        }
        unset($validated['cover']);

        // النشر لاحقاً من مسوّدة يمرّ من هنا لا من الإنشاء
        $post->update($this->withPublishDate([
            ...$validated,
            'status' => $validated['status'] ?? $post->status->value,
            'published_at' => $validated['published_at'] ?? $post->published_at,
        ]));

        $after = ['title' => $post->title, 'status' => $post->status->value, 'category_id' => $post->category_id];
        $this->audit($request, $post, 'updated', 'عدّل مقالاً', array_diff_assoc($after, $before), array_intersect_key($before, array_diff_assoc($after, $before)));

        return response()->json([
            'message' => 'حُفظت التعديلات.',
            'data' => new PostResource($post->fresh()->load('category')),
        ]);
    }

    /** الأسئلة والشهادات والشركاء — ثلاثة جداول بواجهة واحدة. */
    public function simple(Request $request, string $type): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        return response()->json(['data' => $this->modelFor($type)::query()->orderBy('sort_order')->get()]);
    }

    public function storeSimple(Request $request, string $type): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $model = $this->modelFor($type);
        $validated = $request->validate($this->rulesFor($type));

        $item = $model::create($validated);
        $this->audit($request, $item, 'created', 'أضاف عنصر محتوى', $validated);
        $this->flushPublic();

        return response()->json([
            'message' => 'أُضيف العنصر.',
            'data' => $item,
        ], 201);
    }

    public function updateSimple(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $model = $this->modelFor($type);
        $item = $model::findOrFail($id);
        $validated = $request->validate($this->rulesFor($type, partial: true));
        $before = $item->only(array_keys($validated));
        $item->update($validated);
        $this->audit($request, $item, 'updated', 'عدّل عنصر محتوى', $item->only(array_keys($validated)), $before);
        $this->flushPublic();

        return response()->json(['message' => 'حُفظت التعديلات.', 'data' => $item->fresh()]);
    }

    /**
     * ترتيب العناصر بالسحب أو بالأسهم: تُرسل المعرّفات بترتيبها الجديد.
     * كان الترتيب رقماً يُكتب في كل عنصر على حدة، فتبديل اثنين يعني
     * تحرير الاثنين وتخمين رقمين لا يتصادمان مع غيرهما.
     */
    public function reorderSimple(Request $request, string $type): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $model = $this->modelFor($type);
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($model, $validated): void {
            foreach ($validated['ids'] as $position => $id) {
                $model::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        activity()->causedBy($request->user())->event('reordered')
            ->withProperties(['type' => $type, 'ids' => $validated['ids']])
            ->log('أعاد ترتيب عناصر محتوى');
        $this->flushPublic();

        return response()->json([
            'message' => 'حُفظ الترتيب.',
            'data' => $model::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function destroySimple(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $item = $this->modelFor($type)::findOrFail($id);
        $snapshot = $item->toArray();
        $item->delete();

        // المحذوف يبقى أثره: ما كان نصّه ومن حذفه
        $this->audit($request, $item, 'deleted', 'حذف عنصر محتوى', [], $snapshot);
        $this->flushPublic();

        return response()->json(['message' => 'حُذف العنصر.']);
    }

    public function contacts(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $messages = ContactMessage::query()
            ->with('handler:id,name')
            ->when($request->boolean('open_only'), fn ($q) => $q->open())
            // المفتوحة الأقدم أولاً: هي الأقرب إلى تجاوز وعد الرد خلال 72 ساعة.
            // كانت الأحدث أولاً، فيغرق ما يوشك أن يتأخر في آخر القائمة
            ->when($request->boolean('open_only'), fn ($q) => $q->oldest(), fn ($q) => $q->latest())
            ->paginate($this->perPage($request, 25));

        return response()->json([
            'data' => $messages->items(),
            'meta' => ['total' => $messages->total(), 'current_page' => $messages->currentPage(), 'last_page' => $messages->lastPage()],
        ]);
    }

    /**
     * تغيير حالة رسالة تواصل.
     *
     * `handled_by` و`handled_at` يُسندان من الخادم لا من الطلب: من عالج
     * الرسالة هو من أرسل هذا الطلب، لا من يدّعي ذلك في جسمه.
     */
    public function updateContact(Request $request, ContactMessage $contact): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(ContactStatus::values())],
        ]);

        $closing = $validated['status'] !== ContactStatus::New->value;
        $old = $contact->status;

        $contact->update([
            'status' => $validated['status'],
            'handled_by' => $closing ? $request->user()->id : null,
            'handled_at' => $closing ? now() : null,
        ]);

        // كل انتقال يُسجَّل: إعادة الفتح كانت تمسح من عالجها، فيضيع من أغلقها
        $this->audit($request, $contact, 'status_changed', 'غيّر حالة رسالة تواصل',
            ['status' => $validated['status']], ['status' => $old instanceof \BackedEnum ? $old->value : $old]);

        return response()->json([
            'message' => 'حُدّثت حالة الرسالة.',
            'data' => $contact->fresh()->load('handler:id,name'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $before
     */
    private function audit(Request $request, \Illuminate\Database\Eloquent\Model $subject, string $event, string $text, array $after = [], array $before = []): void
    {
        activity()->performedOn($subject)->causedBy($request->user())->event($event)
            ->withProperties(['attributes' => $after, 'old' => $before])
            ->log($text);
    }

    /** الأسئلة والشهادات والشركاء في ذاكرة الموقع العام؛ كانت تبقى قديمة عشر دقائق */
    private function flushPublic(): void
    {
        Cache::forget('public.bootstrap');
    }

    public function settings(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return response()->json(['data' => Setting::cached()]);
    }

    /**
     * المفاتيح المسموحة ونوع كل منها وقواعده.
     *
     * كان الخادم يقبل أي مفتاح وأي قيمة: نسبة تشغيل 500، وسنة تأسيس «abc»
     * تصير صفراً، ومفاتيح جديدة بلا نوع فيُقرأ «false» نصّاً صحيحاً. والقائمة
     * هنا لا في الواجهة: أي عميل يستطيع الإرسال.
     *
     * @return array<string, array{type: string, rules: array<int, mixed>}>
     */
    private function settingsSchema(): array
    {
        return [
            'site.name' => ['type' => 'string', 'rules' => ['required', 'string', 'min:2', 'max:60']],
            'site.tagline' => ['type' => 'string', 'rules' => ['nullable', 'string', 'max:120']],
            'site.email' => ['type' => 'string', 'rules' => ['nullable', 'email:rfc', 'max:120']],
            'site.phone' => ['type' => 'string', 'rules' => ['nullable', 'string', 'max:30']],
            'site.address' => ['type' => 'string', 'rules' => ['nullable', 'string', 'max:160']],
            'site.start_year' => ['type' => 'number', 'rules' => ['nullable', 'integer', 'between:1950,'.now()->year]],
            // عملة الأساس تُثبَّت في الإعداد لا هنا: كل `amount_base` مخزَّن بها،
            // وتغييرها من شاشة يجعل الأرقام القديمة والجديدة بعملتين تحت اسم واحد
            'finance.base_currency' => ['type' => 'string', 'rules' => ['required', Rule::in([config('kafala.base_currency')])]],
            'finance.overhead_target_percent' => ['type' => 'number', 'rules' => ['required', 'integer', 'between:0,30']],
            'policy.moderate_messages' => ['type' => 'boolean', 'rules' => ['required', 'boolean']],
            'policy.report_cadence_months' => ['type' => 'number', 'rules' => ['required', 'integer', 'between:1,12']],
        ];
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $schema = $this->settingsSchema();

        $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.group' => ['required', 'string'],
            'settings.*.key' => ['required', 'string'],
        ]);

        // مفتاح لا تعرفه القائمة يُرفض، لا يُنشأ
        $rows = collect($request->input('settings'))->keyBy(fn ($r) => $r['group'].'.'.$r['key']);
        $unknown = $rows->keys()->diff(array_keys($schema));
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages(['settings' => 'إعداد غير معروف: '.$unknown->implode('، ')]);
        }

        // القواعد على القيم نفسها، بأسماء حقول تعرفها الواجهة (`site.email`)
        // النصّان «true»/«false» منطقيّان كما ترسلهما خانة الاختيار
        $values = $rows->map(fn ($r, $name) => ($schema[$name]['type'] === 'boolean' && is_string($r['value'] ?? null))
            ? filter_var($r['value'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : ($r['value'] ?? null))->all();
        $rules = collect($schema)->only(array_keys($values))->map(fn ($d) => $d['rules'])->all();
        validator(
            collect($values)->mapWithKeys(fn ($v, $k) => [str_replace('.', '__', $k) => $v])->all(),
            collect($rules)->mapWithKeys(fn ($r, $k) => [str_replace('.', '__', $k) => $r])->all(),
        )->setAttributeNames(collect($rules)->mapWithKeys(fn ($r, $k) => [str_replace('.', '__', $k) => $k])->all())
            ->validate();

        $before = Setting::cached();

        foreach ($values as $name => $value) {
            [$group, $key] = explode('.', $name, 2);
            $type = $schema[$name]['type'];

            Setting::updateOrCreate(['group' => $group, 'key' => $key], [
                'value' => match ($type) {
                    'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                    default => $value === null ? null : (string) $value,
                },
                'type' => $type,
            ]);
        }

        Cache::forget('public.bootstrap');
        Cache::forget('transparency.proof');

        $after = Setting::cached();
        $changed = collect($values)->keys()->filter(fn ($k) => ($before[$k] ?? null) !== ($after[$k] ?? null))->values();

        if ($changed->isNotEmpty()) {
            activity()->causedBy($request->user())->event('settings_updated')
                ->withProperties([
                    'old' => $changed->mapWithKeys(fn ($k) => [$k => $before[$k] ?? null])->all(),
                    'attributes' => $changed->mapWithKeys(fn ($k) => [$k => $after[$k] ?? null])->all(),
                ])
                ->log('عدّل الإعدادات');
        }

        return response()->json(['message' => 'حُفظت الإعدادات.', 'data' => $after]);
    }

    /**
     * «منشور» بلا تاريخ نشر يعني الآن.
     *
     * `scopePublished` تشترط `published_at <= now()`، و`NULL` لا تجتاز
     * المقارنة. فمقالٌ حالته «منشور» وتاريخه فارغ يختفي عن المدونة إلى
     * الأبد — ويرى المحرّر في اللوحة أنه منشور فلا يشكّ في شيء.
     *
     * والثابتة هنا لا في الواجهة: أي عميل يستطيع النشر، والواجهة التي
     * تُصلح ما يكسره الخادم تُصلحه لنفسها وحدها.
     */
    private function withPublishDate(array $data): array
    {
        if (($data['status'] ?? null) === PostStatus::Published->value
            && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /** @return class-string */
    private function modelFor(string $type): string
    {
        return match ($type) {
            'faqs' => Faq::class,
            'testimonials' => Testimonial::class,
            'partners' => Partner::class,
            default => abort(404, 'نوع محتوى غير معروف.'),
        };
    }

    /** @return array<string, mixed> */
    private function rulesFor(string $type, bool $partial = false): array
    {
        $p = $partial ? 'sometimes' : 'required';

        return match ($type) {
            'faqs' => [
                'question' => [$p, 'string', 'max:255'],
                'answer' => [$p, 'string', 'max:4000'],
                'category' => ['sometimes', 'nullable', 'string', 'max:60'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
                'is_published' => ['sometimes', 'boolean'],
            ],
            'testimonials' => [
                'author_name' => [$p, 'string', 'max:120'],
                'quote' => [$p, 'string', 'max:1000'],
                'role' => ['sometimes', 'nullable', 'string', 'max:120'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
                'is_published' => ['sometimes', 'boolean'],
            ],
            'partners' => [
                'name' => [$p, 'string', 'max:120'],
                'icon' => ['sometimes', 'nullable', 'string', 'max:60'],
                'url' => ['sometimes', 'nullable', 'url', 'max:255'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            default => [],
        };
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title, '-', 'ar') ?: 'post-'.now()->timestamp;
        $slug = $base;
        $i = 2;

        while (Post::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}

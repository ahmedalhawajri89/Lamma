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

        $posts = Post::query()
            ->with(['category:id,name_ar', 'author:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => ['total' => $posts->total(), 'current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage()],
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
            ...collect($validated)->except('cover')->all(),
            'slug' => $this->uniqueSlug($validated['title']),
            'author_id' => $request->user()->id,
            'cover_path' => $request->hasFile('cover')
                ? $request->file('cover')->store('posts', 'public')
                : null,
        ]);

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
        ]);

        $post->update($validated);

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

        return response()->json([
            'message' => 'أُضيف العنصر.',
            'data' => $model::create($validated),
        ], 201);
    }

    public function updateSimple(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $model = $this->modelFor($type);
        $item = $model::findOrFail($id);
        $item->update($request->validate($this->rulesFor($type, partial: true)));

        return response()->json(['message' => 'حُفظت التعديلات.', 'data' => $item->fresh()]);
    }

    public function destroySimple(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $this->modelFor($type)::findOrFail($id)->delete();

        return response()->json(['message' => 'حُذف العنصر.']);
    }

    public function contacts(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $messages = ContactMessage::query()
            ->with('handler:id,name')
            ->when($request->boolean('open_only'), fn ($q) => $q->open())
            ->latest()
            ->paginate($request->integer('per_page', 25));

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

        $contact->update([
            'status' => $validated['status'],
            'handled_by' => $closing ? $request->user()->id : null,
            'handled_at' => $closing ? now() : null,
        ]);

        return response()->json([
            'message' => 'حُدّثت حالة الرسالة.',
            'data' => $contact->fresh()->load('handler:id,name'),
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return response()->json(['data' => Setting::cached()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.group' => ['required', 'string', 'max:40'],
            'settings.*.key' => ['required', 'string', 'max:80'],
            'settings.*.value' => ['nullable'],
        ]);

        foreach ($validated['settings'] as $row) {
            Setting::updateOrCreate(
                ['group' => $row['group'], 'key' => $row['key']],
                ['value' => is_array($row['value']) ? json_encode($row['value']) : $row['value']],
            );
        }

        Cache::forget('public.bootstrap');

        return response()->json(['message' => 'حُفظت الإعدادات.', 'data' => Setting::cached()]);
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\PostCategory;
use App\Support\Locale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * مقالات المدونة: بتصنيف أو بحث، ومقالٌ يُستثنى (المميّز المعروض فوق
     * الشبكة لا يتكرر فيها). و`facets=1` يُرجع التصنيفات بأعداد المنشور
     * والمقال المميّز، لصدر الصفحة وشريطها.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'exclude' => ['nullable', 'integer'],
        ]);

        $posts = Post::query()
            ->published()
            ->with('category:id,name_ar,name_en,slug')
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category',
                fn ($c) => $c->where('slug', $request->string('category')->toString()),
            ))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('excerpt', 'like', "%{$term}%"));
            })
            ->when($request->filled('exclude'), fn ($q) => $q->whereKeyNot($request->integer('exclude')))
            ->paginate($this->perPage($request, 9));

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'total' => $posts->total(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                ...($request->boolean('facets') ? $this->facets() : []),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function facets(): array
    {
        $categories = PostCategory::query()
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($c) => $c->posts_count > 0)
            ->map(fn ($c) => ['slug' => $c->slug, 'name' => Locale::pick($c->name_ar, $c->name_en), 'count' => $c->posts_count])
            ->values();

        // المميّز: أحدث مقال منشور معلَّم، وإلا أحدث مقال
        $featured = Post::query()->published()->with('category:id,name_ar,name_en,slug')->where('is_featured', true)->first()
            ?? Post::query()->published()->with('category:id,name_ar,name_en,slug')->first();

        return [
            'categories' => $categories,
            'all_count' => Post::query()->published()->count(),
            'featured' => $featured ? (new PostResource($featured))->resolve() : null,
        ];
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::query()
            ->published()
            ->with(['category:id,name_ar,name_en', 'author:id,name'])
            ->where('slug', $slug)
            ->firstOrFail();

        /**
         * عدّاد القراءات يزيد باستعلام مباشر لا عبر الموديل.
         *
         * `incrementQuietly` محمية (protected) في لارافل ١٢ فالنداء عليها من
         * خارج الموديل خطأ قاتل. و`increment()` العادية تلمس `updated_at`
         * وتُطلق الأحداث — وقراءة زائر لا تستحق أن تُسجَّل كتعديل على المقال
         * ولا أن تدخل سجل النشاط.
         */
        Post::whereKey($post->getKey())->toBase()->increment('views_count');

        return response()->json(['data' => new PostResource($post)]);
    }
}

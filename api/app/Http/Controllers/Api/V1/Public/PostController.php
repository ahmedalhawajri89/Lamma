<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->published()
            ->with('category:id,name_ar')
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category',
                fn ($c) => $c->where('slug', $request->string('category')->toString()),
            ))
            ->paginate($request->integer('per_page', 9));

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'total' => $posts->total(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::query()
            ->published()
            ->with(['category:id,name_ar', 'author:id,name'])
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

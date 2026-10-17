<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * صفحة المقال، مرسومةً من الخادم.
 *
 * كانت قالباً فارغاً («جارٍ تحميل المقال…») يملؤه JS، فرابط المقال المُشارك
 * يصل واتساب بلا عنوان ولا صورة، ومحركات البحث ترى صفحة بلا نصّ، والمقال
 * أكثر ما يُشارَك في الموقع. الآن يصل بعنوانه وصورته ونصّه ووسوم مشاركته،
 * ومعه وصفٌ منظَّم (Article) لمحركات البحث.
 */
class PostPageController extends Controller
{
    private const RELATED = 3;

    public function __invoke(Request $request, RichText $rich): Response|RedirectResponse
    {
        $slug = $request->string('slug')->trim()->toString();

        if ($slug === '') {
            return redirect('/blog');
        }

        $post = Post::query()
            ->published()
            ->with(['category:id,name_ar,slug', 'author:id,name'])
            ->where('slug', $slug)
            ->first();

        if ($post === null) {
            return response()->view('site.pages.post', ['post' => null], 404);
        }

        // كما في الـ API: استعلامٌ مباشر، فقراءة زائر ليست تعديلاً ولا تدخل سجل النشاط
        Post::whereKey($post->getKey())->toBase()->increment('views_count');

        /*
         * النصّ نظّفه الخادم عند الحفظ (RichText)، ويُنظَّف هنا مرة أخرى قبل
         * طباعته بلا تهريب: صفٌّ كُتب في القاعدة من غير طريق المحرّر (استيراد،
         * أو بيانات قديمة) لا يصل الصفحة إلا بالوسوم المسموحة. والنصّ القديم
         * العادي يصير فقراتٍ مهرَّبة.
         */
        $body = RichText::isHtml($post->body)
            ? $rich->sanitize($post->body)
            : $rich->fromPlain($post->body ?: $post->excerpt);

        // من التصنيف نفسه أولاً، ثم الأحدث، بلا المقال نفسه
        $related = Post::query()->published()->with('category:id,name_ar,slug')
            ->whereKeyNot($post->getKey())
            // published() يرتّب بالتاريخ أولاً؛ التصنيف يسبقه هنا
            ->reorder()
            ->when($post->category_id, fn ($q) => $q->orderByRaw('category_id = ? desc', [$post->category_id]))
            ->latest('published_at')
            ->limit(self::RELATED)
            ->get();

        return response()->view('site.pages.post', [
            'post' => $post,
            'body' => $body,
            'related' => $related,
        ]);
    }
}

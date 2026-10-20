<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Post;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /*
         * الموقع العام يأخذ النصّ بلغة الصفحة (translations) والعربي إن لم
         * يُترجم، واللوحة تأخذ الأصل العربي دائماً: نافذة التحرير تُملأ من
         * الصفّ وتُرسله كما هو، فترجمةٌ فيه تحلّ محلّ العربي عند الحفظ.
         */
        $public = ! $request->routeIs('api.v1.admin.*');
        $text = fn (string $field) => $public ? $this->resource->translated($field) : $this->{$field};

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $text('title'),
            'excerpt' => $text('excerpt'),
            // لغة النصّ فعلاً: مقالٌ لم يُترجم يُعرض عربياً (lang/dir) في صفحةٍ إنجليزية
            'lang' => $this->when($public, fn () => self::contentLocale($this->resource)),
            /**
             * النصّ في صفحة المقال، وفي قوائم اللوحة.
             *
             * كان محصوراً بـ`*.show` وحدها فلا يصل قائمةَ اللوحة — ونافذة
             * التحرير تُملأ من الصفّ، فتُرسل `body` فارغاً وتمحو نصّ المقال:
             * من فتح مقالاً ليغيّر حالته فقد نصّه كلّه بلا إنذار.
             *
             * وقوائم الموقع العام تبقى نحيفة كما كانت — لا داعي لاثني عشر
             * نصّاً كاملاً لعرض اثنتي عشرة بطاقة.
             */
            'body' => $this->when(
                $request->routeIs('*.show') || $request->routeIs('api.v1.admin.posts.*'),
                fn () => $text('body'),
            ),
            'category' => $this->whenLoaded('category', fn () => $public && $this->category
                ? Locale::pick($this->category->name_ar, $this->category->getAttributes()['name_en'] ?? null)
                : $this->category?->name_ar),
            // نافذة التحرير تختار التصنيف برقمه؛ كان غائباً فيُرسَل فارغاً مع
            // كل تعديل ويُمسح تصنيف المقال
            'category_id' => $this->category_id,
            // لروابط تصنيف المدونة (?cat=): الاسم للعرض والـ slug للفلتر
            // يُقرأ إن حُمِّل وحده: نقاط أخرى تحمّل `category:id,name_ar`، والوضع الصارم
            // يرمي على سمةٍ لم تُجلب (كسر صفحة المقال حين أُضيف الحقل)
            'category_slug' => $this->whenLoaded('category', fn () => $this->category
                && array_key_exists('slug', $this->category->getAttributes()) ? $this->category->slug : null),
            'image' => $this->cover_path ? asset('storage/'.$this->cover_path) : null,
            'readMin' => $this->read_minutes,
            'featured' => $this->is_featured,
            // «مجدول» منشورٌ بموعدٍ قادم (Post::displayStatus)
            'status' => $this->displayStatus()->value,
            'status_label' => $this->displayStatus()->label(),
            'date' => $this->published_at?->toDateString(),
            'dateLabel' => $this->published_at?->translatedFormat('j F Y'),
            'views_count' => $this->views_count,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
        ];
    }

    /** لغة نصّ المقال في هذه الصفحة: لغتها إن تُرجم عنوانه إليها، وإلا العربية. */
    public static function contentLocale(Post $post): string
    {
        $locale = Locale::current();

        return ! Locale::isDefault($locale) && filled($post->translations[$locale]['title'] ?? null)
            ? $locale
            : Locale::default();
    }
}

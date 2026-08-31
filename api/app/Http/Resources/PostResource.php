<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
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
                fn () => $this->body,
            ),
            'category' => $this->whenLoaded('category', fn () => $this->category?->name_ar),
            'image' => $this->cover_path ? asset('storage/'.$this->cover_path) : null,
            'readMin' => $this->read_minutes,
            'featured' => $this->is_featured,
            'status' => $this->status->value,
            'date' => $this->published_at?->toDateString(),
            'dateLabel' => $this->published_at?->translatedFormat('j F Y'),
            'views_count' => $this->views_count,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
        ];
    }
}

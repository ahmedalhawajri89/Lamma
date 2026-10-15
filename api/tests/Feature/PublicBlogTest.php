<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostCategory;

/**
 * المدونة العامة: بحثٌ، ومقالٌ يُستثنى (المميّز فوق الشبكة)، والتصنيفات
 * بأعداد المنشور، والمميّز نفسه.
 */
it('يبحث ويستثني ويعيد التصنيفات بأعداد المنشور والمقال المميّز', function () {
    $news = PostCategory::create(['slug' => 'news', 'name_ar' => 'أخبار', 'sort_order' => 1]);
    $empty = PostCategory::create(['slug' => 'empty', 'name_ar' => 'فارغ', 'sort_order' => 2]);

    $make = fn (string $title, array $extra = []) => Post::factory()->create([
        'title' => $title, 'status' => PostStatus::Published, 'published_at' => now()->subDay(), 'category_id' => $news->id, ...$extra,
    ]);
    $featured = $make('مقال مميّز عن الزيارات', ['is_featured' => true]);
    $make('كيف نتحقق من الحالات');
    Post::factory()->create(['title' => 'مسوّدة لا تُعدّ', 'status' => PostStatus::Draft, 'category_id' => $empty->id]);

    $res = $this->getJson('/api/v1/public/posts?facets=1&exclude='.$featured->id)->assertOk();
    $slugs = collect($res->json('meta.categories'))->pluck('slug');

    expect($res->json('meta.total'))->toBe(1)
        ->and($res->json('data.0.category_slug'))->toBe('news')
        ->and($res->json('meta.featured.id'))->toBe($featured->id)
        // التصنيف الذي لا منشور فيه لا يظهر
        ->and($slugs->all())->toContain('news')->not->toContain('empty');

    $this->getJson('/api/v1/public/posts?q=نتحقق')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'كيف نتحقق من الحالات');
    $this->getJson('/api/v1/public/posts?q=لا-شيء-يطابق')->assertJsonPath('meta.total', 0);

    // صفحة المقال تحمّل التصنيف بلا slug: الحقل الجديد لا يكسرها (الوضع الصارم)
    $this->getJson('/api/v1/public/posts/'.rawurlencode($featured->slug))->assertOk()->assertJsonPath('data.category_slug', null);
});

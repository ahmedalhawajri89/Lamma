<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Faq;
use App\Models\Post;
use App\Models\User;

/**
 * محتوى الموقع: أعداد حالات المقالات والبحث، والمقال الواحد للمحرّر،
 * وترتيب العناصر بإرسال ترتيبها.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

it('يعدّ حالات المقالات ويبحث في العنوان، ويعطي المقال كاملاً للمحرّر', function (): void {
    $before = Post::count();
    $post = Post::create(['title' => 'نافذة على قرية الأمل', 'slug' => 'qaryat-alamal', 'body' => "فقرة أولى\n\nفقرة ثانية", 'status' => PostStatus::Draft, 'read_minutes' => 2]);

    $meta = $this->getJson('/api/v1/admin/posts?status=draft')->assertOk()->json('meta');
    expect($meta['status_counts']['draft'] ?? 0)->toBeGreaterThanOrEqual(1)
        ->and($meta['all_count'])->toBe($before + 1);

    expect($this->getJson('/api/v1/admin/posts?q='.urlencode('قرية الأمل'))->json('meta.total'))->toBe(1);

    $this->getJson("/api/v1/admin/posts/{$post->id}")->assertOk()
        ->assertJsonPath('data.body', "فقرة أولى\n\nفقرة ثانية")
        ->assertJsonStructure(['meta' => ['categories', 'statuses']]);
});

it('يرتّب الأسئلة الشائعة بالترتيب المرسَل', function (): void {
    Faq::query()->delete();
    $a = Faq::create(['question' => 'سؤال أ', 'answer' => 'جواب', 'sort_order' => 1, 'is_published' => true]);
    $b = Faq::create(['question' => 'سؤال ب', 'answer' => 'جواب', 'sort_order' => 2, 'is_published' => true]);
    $c = Faq::create(['question' => 'سؤال ج', 'answer' => 'جواب', 'sort_order' => 3, 'is_published' => true]);

    $ids = collect($this->postJson('/api/v1/admin/content/faqs/reorder', ['ids' => [$c->id, $a->id, $b->id]])->assertOk()->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$c->id, $a->id, $b->id]);
});

/* ------------------------------------------------------------ النشر والجدولة */

/**
 * «مجدول» كان حالةً تُحفظ ولا شيء يحوّلها إلى منشور، فيبقى المقال مخفياً
 * إلى الأبد. الآن المجدول منشورٌ بموعدٍ قادم، يظهر وحده يوم يحلّ.
 */
it('النشر بتاريخ قادم جدولةٌ تظهر على الموقع يوم يحلّ موعدها', function (): void {
    $day = now()->addDays(5)->toDateString();

    $res = $this->postJson('/api/v1/admin/posts', ['title' => 'مقال مجدول', 'status' => 'published', 'published_at' => $day])
        ->assertCreated()
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.status_label', 'مجدول');
    expect($res->json('message'))->toContain('جُدول');

    $post = Post::find($res->json('data.id'));
    expect($post->status)->toBe(PostStatus::Published)
        ->and(Post::published()->whereKey($post->id)->exists())->toBeFalse();

    $meta = $this->getJson('/api/v1/admin/posts?status=scheduled')->json('meta');
    expect($meta['status_counts']['scheduled'] ?? 0)->toBe(1)->and($meta['total'])->toBe(1);

    $this->travel(6)->days();
    expect(Post::published()->whereKey($post->id)->exists())->toBeTrue();
});

it('«مجدول» صريحاً يشترط تاريخاً قادماً، ويُحفظ منشوراً به', function (): void {
    $this->postJson('/api/v1/admin/posts', ['title' => 'بلا موعد', 'status' => 'scheduled'])
        ->assertUnprocessable()->assertJsonValidationErrors('published_at');

    $id = $this->postJson('/api/v1/admin/posts', ['title' => 'بموعد', 'status' => 'scheduled', 'published_at' => now()->addWeek()->toDateString()])
        ->assertCreated()->json('data.id');
    expect(Post::find($id)->status)->toBe(PostStatus::Published);
});

it('رسالة الحفظ تسمّي ما حدث: مسوّدة، ونشر، وإلغاء نشر', function (): void {
    $id = $this->postJson('/api/v1/admin/posts', ['title' => 'مقال', 'status' => 'draft'])->assertCreated()->json('data.id');

    expect($this->patchJson("/api/v1/admin/posts/{$id}", ['status' => 'draft', 'title' => 'مقال معدّل'])->json('message'))->toContain('المسوّدة')
        ->and($this->patchJson("/api/v1/admin/posts/{$id}", ['status' => 'published'])->json('message'))->toContain('نُشر')
        ->and($this->patchJson("/api/v1/admin/posts/{$id}", ['status' => 'published', 'title' => 'بعد النشر'])->json('message'))->toBe('حُفظت التعديلات.')
        ->and($this->patchJson("/api/v1/admin/posts/{$id}", ['status' => 'draft'])->json('message'))->toContain('أُلغي النشر');
});

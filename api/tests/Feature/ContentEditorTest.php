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

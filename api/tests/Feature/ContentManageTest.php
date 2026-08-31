<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Faq;
use App\Models\Post;
use App\Models\User;

/**
 * إدارة محتوى الموقع العام.
 *
 * لم تكن لهذه النقاط شاشة، فكل ما يقرأه الزائر — المدونة والأسئلة والشهادات
 * والشركاء — كان يُكتب في البذرة وحدها.
 */
beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * أهمّ اختبار هنا.
 *
 * `scopePublished` تشترط `published_at <= now()`، و`NULL` لا تجتاز المقارنة.
 * فمقالٌ حالته «منشور» وتاريخه فارغ يختفي عن المدونة إلى الأبد — ويرى
 * المحرّر في اللوحة أنه منشور فلا يشكّ في شيء.
 */
it('يملأ تاريخ النشر حين يُنشر المقال بلا تاريخ', function (): void {
    $id = $this->postJson('/api/v1/admin/posts', [
        'title' => 'مقال اختباري',
        'excerpt' => 'مقتطف',
        'body' => 'نصّ المقال.',
        'status' => PostStatus::Published->value,
    ])->assertCreated()->json('data.id');

    expect(Post::findOrFail($id)->published_at)->not->toBeNull();

    // والدليل العملي: يظهر على نقطة الموقع العام
    $this->getJson('/api/v1/public/posts?per_page=50')
        ->assertOk()
        ->assertJsonFragment(['title' => 'مقال اختباري']);
});

it('يبقي المسوّدة بلا تاريخ نشر وخارج الموقع', function (): void {
    $id = $this->postJson('/api/v1/admin/posts', [
        'title' => 'مسوّدة اختبارية',
        'status' => PostStatus::Draft->value,
    ])->assertCreated()->json('data.id');

    expect(Post::findOrFail($id)->published_at)->toBeNull();

    $this->getJson('/api/v1/public/posts?per_page=50')
        ->assertOk()
        ->assertJsonMissing(['title' => 'مسوّدة اختبارية']);
});

/** النشر لاحقاً من مسوّدة يمرّ بالتعديل لا بالإنشاء. */
it('يملأ التاريخ حين تُنشر مسوّدة قائمة', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Draft,
        'published_at' => null,
    ]);

    $this->patchJson("/api/v1/admin/posts/{$post->id}", ['status' => PostStatus::Published->value])
        ->assertOk();

    expect($post->fresh()->published_at)->not->toBeNull();
});

it('لا يطمس تاريخ نشرٍ قائماً عند تعديل العنوان', function (): void {
    $post = Post::factory()->create([
        'status' => PostStatus::Published,
        'published_at' => now()->subMonth(),
    ]);
    $original = $post->published_at;

    $this->patchJson("/api/v1/admin/posts/{$post->id}", ['title' => 'عنوان جديد'])->assertOk();

    expect($post->fresh()->published_at->toDateString())->toBe($original->toDateString());
});

it('ينشئ سؤالاً شائعاً ويحذفه', function (): void {
    $id = $this->postJson('/api/v1/admin/content/faqs', [
        'question' => 'سؤال اختباري؟',
        'answer' => 'جواب اختباري.',
    ])->assertCreated()->json('data.id');

    $this->deleteJson("/api/v1/admin/content/faqs/{$id}")->assertOk();

    expect(Faq::find($id))->toBeNull();
});

it('يرفض نوع محتوى غير معروف', function (): void {
    $this->getJson('/api/v1/admin/content/anything')->assertNotFound();
});

it('يمنع المشرف الميداني من تحرير المحتوى', function (): void {
    $this->actingAs(User::factory()->supervisor()->create());

    $this->postJson('/api/v1/admin/content/faqs', [
        'question' => 'سؤال', 'answer' => 'جواب',
    ])->assertForbidden();
});

/**
 * قائمة اللوحة تحمل نصّ المقال.
 *
 * نافذة التحرير تُملأ من الصفّ، فبلا `body` فيه تُرسل الحقل فارغاً — ومن
 * فتح مقالاً ليغيّر حالته فقد نصّه كلّه بلا إنذار. أما قوائم الموقع العام
 * فتبقى نحيفة: لا داعي لاثني عشر نصّاً كاملاً لعرض اثنتي عشرة بطاقة.
 */
it('يُرجع نصّ المقال في قائمة اللوحة لا في قائمة الموقع', function (): void {
    Post::factory()->create(['body' => 'نصّ المقال كاملاً.']);

    $this->getJson('/api/v1/admin/posts?per_page=50')
        ->assertOk()
        ->assertJsonFragment(['body' => 'نصّ المقال كاملاً.']);

    $public = $this->getJson('/api/v1/public/posts?per_page=50')->assertOk();

    expect($public->json('data.0'))->not->toHaveKey('body');
});

it('لا يمحو النصّ حين يُنشر المقال من نافذة التحرير', function (): void {
    $post = Post::factory()->draft()->create(['body' => 'نصّ لا يجوز أن يضيع.']);

    // ما ترسله النافذة: القيم كلها كما قرأتها من الصفّ
    $row = collect($this->getJson('/api/v1/admin/posts?per_page=50')->json('data'))
        ->firstWhere('id', $post->id);

    $this->patchJson("/api/v1/admin/posts/{$post->id}", [
        'title' => $row['title'],
        'excerpt' => $row['excerpt'],
        'body' => $row['body'],
        'status' => 'published',
    ])->assertOk();

    expect($post->fresh()->body)->toBe('نصّ لا يجوز أن يضيع.');
});

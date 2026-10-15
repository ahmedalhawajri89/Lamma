<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Services\RichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * نصّ المقال المنسّق: يبقى التنسيق، ويُحذف كل ما يُنفَّذ.
 */
it('يبقي التنسيق ويحذف السكربت والأحداث والروابط الخطرة', function (): void {
    $dirty = '<h2>عنوان</h2><p>نص <strong>مهم</strong> <a href="javascript:alert(1)">رابط</a>'
        .'<img src="x" onerror="alert(1)"></p><script>alert(1)</script>'
        .'<iframe src="https://evil.example"></iframe><p onclick="x()">فقرة</p>'
        .'<ul><li>بند</li></ul><blockquote>اقتباس</blockquote><a href="https://kafala.org">موقعنا</a>';

    $clean = app(RichText::class)->sanitize($dirty);

    expect($clean)
        ->toContain('<h2>عنوان</h2>', '<strong>مهم</strong>', '<ul><li>بند</li></ul>', '<blockquote>اقتباس</blockquote>')
        ->toContain('href="https://kafala.org"', 'rel="noopener noreferrer nofollow"')
        ->not->toContain('<script', 'javascript:', 'onerror', 'onclick', '<iframe');
});

it('النصّ القديم يصير فقرات مهرَّبة، فلا يُفسَّر ما فيه وسماً', function (): void {
    $html = app(RichText::class)->fromPlain("سطر <b>ليس وسماً</b>\n\nفقرة ثانية");

    expect($html)->toBe('<p>سطر &lt;b&gt;ليس وسماً&lt;/b&gt;</p><p>فقرة ثانية</p>');
});

it('حفظ المقال من اللوحة ينظّف نصّه، والموقع يتلقّاه نظيفاً', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $id = $this->postJson('/api/v1/admin/posts', [
        'title' => 'مقال منسّق', 'status' => PostStatus::Published->value,
        'body' => '<p>مرحباً</p><script>steal()</script><h3 onmouseover="x">قسم</h3>',
    ])->assertCreated()->json('data.id');

    expect(Post::find($id)->body)->toBe('<p>مرحباً</p><h3>قسم</h3>');

    $slug = Post::find($id)->slug;
    expect($this->getJson("/api/v1/public/posts/{$slug}")->json('data.body'))->not->toContain('script');
});

it('صورة داخل المقال تُرفع وتُرجع رابطها، ولا تُقبل SVG', function (): void {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    $url = $this->post('/api/v1/admin/posts/images', ['image' => UploadedFile::fake()->image('a.jpg', 800, 500)], ['Accept' => 'application/json'])
        ->assertCreated()->json('data.url');
    expect($url)->toContain('/storage/posts/inline/');

    $this->post('/api/v1/admin/posts/images', ['image' => UploadedFile::fake()->create('x.svg', 2, 'image/svg+xml')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

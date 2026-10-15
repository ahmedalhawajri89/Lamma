<?php

declare(strict_types=1);

use App\Models\TransparencyDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * وثائق الشفافية: تُرفع من اللوحة وتُنشر على الصفحة العامة. كانت أزرار
 * «تحميل PDF» تشير إلى `#`.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Cache::flush();
});

it('يُرفع PDF فيُنشر بالصفحة العامة، والرفع الثاني لنفس النوع والسنة يستبدله', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/api/v1/admin/transparency-documents', [
        'kind' => 'audited_budget', 'year' => 2025, 'file' => UploadedFile::fake()->create('b.pdf', 120, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $first = TransparencyDocument::firstOrFail();
    Storage::disk('public')->assertExists($first->file_path);

    $this->actingAs($admin)->post('/api/v1/admin/transparency-documents', [
        'kind' => 'audited_budget', 'year' => 2025, 'file' => UploadedFile::fake()->create('b2.pdf', 80, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated();

    expect(TransparencyDocument::count())->toBe(1);
    Storage::disk('public')->assertMissing($first->file_path);

    $docs = $this->getJson('/api/v1/public/transparency')->assertOk()->json('data.documents');
    expect($docs)->toHaveCount(1)
        ->and($docs[0]['kind'])->toBe('audited_budget')
        ->and($docs[0]['url'])->toContain('/storage/transparency/')
        ->and($docs[0]['title'])->toBe('الميزانية المدقّقة 2025');
});

it('التراخيص بلا سنة لا تتكرر، وغير PDF يُرفض، وغير المدير ممنوع', function () {
    $admin = User::factory()->admin()->create();
    $post = fn ($file, $user = null) => $this->actingAs($user ?? $admin)->post('/api/v1/admin/transparency-documents',
        ['kind' => 'licenses', 'file' => $file], ['Accept' => 'application/json']);

    $post(UploadedFile::fake()->create('l.pdf', 50, 'application/pdf'))->assertCreated();
    $post(UploadedFile::fake()->create('l2.pdf', 50, 'application/pdf'))->assertCreated();
    expect(TransparencyDocument::where('kind', 'licenses')->count())->toBe(1);

    $post(UploadedFile::fake()->image('x.png'))->assertUnprocessable()->assertJsonValidationErrors('file');
    $post(UploadedFile::fake()->create('l.pdf', 50, 'application/pdf'), User::factory()->accountant()->create())->assertForbidden();

    $this->actingAs($admin)->postJson('/api/v1/admin/transparency-documents', ['kind' => 'ghost', 'file' => UploadedFile::fake()->create('g.pdf', 5, 'application/pdf')])
        ->assertUnprocessable();
});

it('الحمولة العامة تحمل الشهور ونسبة التشغيل المحسوبة والسنوات', function () {
    $this->getJson('/api/v1/public/transparency')
        ->assertOk()
        ->assertJsonCount(12, 'data.monthly')
        ->assertJsonStructure(['data' => ['overhead' => ['actual', 'target', 'year'], 'years', 'documents', 'spending', 'proof', 'annual']]);
});

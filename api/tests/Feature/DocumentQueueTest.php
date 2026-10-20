<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Enums\OrphanStatus;
use App\Models\Orphan;
use App\Models\OrphanDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * طابور التحقق: الرفض بسبب، والعرض داخل اللوحة، وإعلان اكتمال الملف.
 */
function pendingDoc(Orphan $orphan, DocumentType $type, User $by, array $extra = []): OrphanDocument
{
    return OrphanDocument::create([
        'orphan_id' => $orphan->id, 'type' => $type, 'path' => "documents/{$orphan->id}/{$type->value}.pdf",
        'original_name' => "{$type->value}.pdf", 'mime' => 'application/pdf', 'size_bytes' => 10, 'uploaded_by' => $by->id, ...$extra,
    ]);
}

it('الرفض بسبب يُخرج المستند من الطابور ويبقيه في الملف، ولا قرار ثانياً عليه', function (): void {
    $admin = User::factory()->admin()->create();
    $orphan = Orphan::factory()->create();
    $doc = pendingDoc($orphan, DocumentType::BirthCertificate, User::factory()->create());

    $this->actingAs($admin)->postJson("/api/v1/admin/documents/{$doc->id}/reject", ['reason' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->actingAs($admin)->postJson("/api/v1/admin/documents/{$doc->id}/reject", ['reason' => 'الصورة غير مقروءة'])->assertOk();

    expect($this->actingAs($admin)->getJson('/api/v1/admin/documents')->json('meta.all_count'))->toBe(0);
    $inFile = $this->actingAs($admin)->getJson("/api/v1/admin/orphans/{$orphan->id}/documents")->json('data.0');
    expect($inFile)->is_rejected->toBeTrue()->rejection_reason->toBe('الصورة غير مقروءة');

    $this->actingAs($admin)->postJson("/api/v1/admin/documents/{$doc->id}/verify")->assertStatus(409);
    $this->actingAs($admin)->postJson("/api/v1/admin/documents/{$doc->id}/reject", ['reason' => 'مرة ثانية'])->assertStatus(409);
});

it('الطابور يعدّ الأنواع والملفات، ويعطي ما يُطابَق به', function (): void {
    $admin = User::factory()->admin()->create();
    $uploader = User::factory()->create();
    $a = Orphan::factory()->create(['first_name' => 'سميرة']);
    $b = Orphan::factory()->create();
    pendingDoc($a, DocumentType::BirthCertificate, $uploader);
    pendingDoc($a, DocumentType::DeathCertificate, $uploader);
    pendingDoc($b, DocumentType::BirthCertificate, $uploader);

    $res = $this->actingAs($admin)->getJson('/api/v1/admin/documents?type=death_certificate')->assertOk();
    expect($res->json('meta'))
        ->total->toBe(1)
        ->all_count->toBe(3)
        ->orphans_count->toBe(2)
        ->and($res->json('meta.type_counts.birth_certificate'))->toBe(2)
        ->and($res->json('data.0.orphan'))->toHaveKeys(['full_name', 'date_of_birth', 'guardian', 'family_code'])
        ->and($res->json('data.0.orphan.required'))->toBe(['birth_certificate' => 'pending', 'death_certificate' => 'pending', 'guardianship_proof' => 'missing'])
        ->and($res->json('meta.decided_today'))->toBe(0);

    expect($this->actingAs($admin)->getJson('/api/v1/admin/documents?q=سمير')->json('meta.total'))->toBe(2);
});

it('التحقق من آخر المستندات الثلاثة يقول إن الملف جاهز للتوثيق', function (): void {
    $admin = User::factory()->admin()->create();
    $uploader = User::factory()->create();
    $orphan = Orphan::factory()->create(['status' => OrphanStatus::PendingVerification, 'verified_at' => null]);
    pendingDoc($orphan, DocumentType::BirthCertificate, $uploader, ['verified_at' => now(), 'verified_by' => $admin->id]);
    pendingDoc($orphan, DocumentType::DeathCertificate, $uploader, ['verified_at' => now(), 'verified_by' => $admin->id]);
    $last = pendingDoc($orphan, DocumentType::GuardianshipProof, $uploader);

    $this->actingAs($admin)->postJson("/api/v1/admin/documents/{$last->id}/verify")
        ->assertOk()->assertJsonPath('orphan_ready', true);
});

it('العرض داخل اللوحة بنوع الملف ومعزولاً، والتنزيل مرفق', function (): void {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $orphan = Orphan::factory()->create();
    Storage::disk('local')->put("documents/{$orphan->id}/birth_certificate.pdf", '%PDF-1.4 test');
    $doc = pendingDoc($orphan, DocumentType::BirthCertificate, User::factory()->create());

    $inline = $this->actingAs($admin)->get("/api/v1/admin/documents/{$doc->id}/download?inline=1")->assertOk();
    expect($inline->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($inline->headers->get('Content-Security-Policy'))->toBe('sandbox')
        ->and($inline->headers->get('Content-Disposition'))->toStartWith('inline');

    $download = $this->actingAs($admin)->get("/api/v1/admin/documents/{$doc->id}/download")->assertOk();
    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment');
});

<?php

declare(strict_types=1);

namespace App\Actions\Orphans;

use App\Enums\DocumentType;
use App\Enums\OrphanStatus;
use App\Models\Orphan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * توثيق ملف يتيم ثم نشره.
 *
 * ادّعاء الموقع أن كل حالة تمرّ بثلاث مراحل تحقّق يصير هنا شرطاً قابلاً للفشل:
 * لا توثيق بلا شهادة ميلاد وشهادة وفاة وإثبات وصاية، كلها مرفوعة ومُتحقَّق منها.
 * بلا هذا الفحص يبقى الادّعاء نصاً تسويقياً.
 */
class VerifyOrphan
{
    private const REQUIRED_DOCUMENTS = [
        DocumentType::BirthCertificate,
        DocumentType::DeathCertificate,
        DocumentType::GuardianshipProof,
    ];

    public function verify(Orphan $orphan, User $verifier): Orphan
    {
        $present = $orphan->documents()
            ->verified()
            ->pluck('type')
            ->map(fn ($t) => $t instanceof DocumentType ? $t->value : (string) $t)
            ->all();

        $missing = array_filter(
            self::REQUIRED_DOCUMENTS,
            static fn (DocumentType $type): bool => ! in_array($type->value, $present, true),
        );

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'documents' => 'ينقص الملف: '.implode('، ', array_map(
                    static fn (DocumentType $t): string => $t->label(),
                    $missing,
                )),
            ]);
        }

        return DB::transaction(function () use ($orphan, $verifier): Orphan {
            $orphan->update([
                'verified_at' => now(),
                'verified_by' => $verifier->id,
                'status' => $orphan->status === OrphanStatus::PendingVerification
                    ? OrphanStatus::Active
                    : $orphan->status,
            ]);

            return $orphan->refresh();
        });
    }

    /**
     * النشر للكفالة. الشروط كلها في Orphan::publishBlockers وتُعاد أسبابها
     * بلغة القارئ، لا 403 صامتة. وهي هنا لا في السياسة: الإخفاء (unpublish)
     * يمرّ بالصلاحية نفسها، ولا يُمنع أحدٌ من إخفاء طفل.
     */
    public function publish(Orphan $orphan): Orphan
    {
        $blockers = $orphan->publishBlockers();
        if ($blockers !== []) {
            throw ValidationException::withMessages(['status' => $blockers]);
        }

        $orphan->update([
            'published_at' => $orphan->published_at ?? now(),
            'status' => $orphan->status === OrphanStatus::Draft ? OrphanStatus::Active : $orphan->status,
        ]);

        return $orphan->refresh();
    }

    public function unpublish(Orphan $orphan): Orphan
    {
        $orphan->update(['published_at' => null]);

        return $orphan->refresh();
    }
}

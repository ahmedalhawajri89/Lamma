<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DocumentType;
use App\Enums\OrphanStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrphanDocumentResource;
use App\Models\Orphan;
use App\Models\OrphanDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مستندات التحقق.
 *
 * ثلاثة قرارات أمنية هنا:
 *  1. القرص `local` لا `public` — الملفات خارج جذر الخادم، فلا يصلها أحد
 *     بتخمين الرابط.
 *  2. التنزيل عبر مسار محمي بصلاحية، فيُسجَّل من نزّل ماذا.
 *  3. التحقق من النوع الحقيقي للملف (mime) لا من امتداده: ملف `.pdf` قد
 *     يكون سكربتاً، والامتداد يكذب بينما محتوى الملف لا.
 */
class DocumentController extends Controller
{
    public function index(Request $request, Orphan $orphan): JsonResponse
    {
        $this->authorize('view', $orphan);

        return response()->json([
            'data' => OrphanDocumentResource::collection($orphan->documents()->latest()->get()),
        ]);
    }

    /**
     * طابور التحقق: المستندات المرفوعة التي لم يُتحقَّق منها بعد، من كل
     * الملفات معاً، فلا يُراجعها الموظف يتيماً يتيماً. الأقدم أولاً، فلا
     * يبقى مستند في آخر الطابور أسابيع.
     */
    public function queue(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('orphans.verify'), 403);

        $base = $this->pending($request);

        $documents = (clone $base)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->with([
                'orphan:id,reference_code,first_name,last_name,gender,date_of_birth,photo_path,status,family_id',
                'orphan.family:id,family_code,guardian_id',
                'orphan.family.guardian:id,full_name,relation',
                'orphan.documents:id,orphan_id,type,verified_at,rejected_at',
                'uploader:id,name',
            ])
            // ملف الطفل معاً، والأقدم أولاً: مستندات طفل واحد تُراجَع متتالية
            ->orderBy(OrphanDocument::query()->from('orphan_documents as first')->selectRaw('MIN(first.created_at)')
                ->whereColumn('first.orphan_id', 'orphan_documents.orphan_id')->whereNull('first.verified_at')->whereNull('first.rejected_at'))
            ->orderBy('orphan_id')
            ->oldest()
            ->paginate($this->perPage($request, 30));

        return response()->json([
            'data' => collect($documents->items())->map(fn (OrphanDocument $d) => [
                ...(new OrphanDocumentResource($d))->toArray($request),
                'uploader' => $d->uploader?->name,
                // ما يُطابَق به المستند، في الصف نفسه بلا فتح الملف
                'orphan' => [
                    'id' => $d->orphan->id,
                    'reference_code' => $d->orphan->reference_code,
                    'name' => $d->orphan->first_name,
                    'full_name' => $d->orphan->full_name,
                    'gender_label' => $d->orphan->gender?->label(),
                    'date_of_birth' => $d->orphan->date_of_birth?->toDateString(),
                    'photo_url' => $d->orphan->photo_path ? asset('storage/'.$d->orphan->photo_path) : null,
                    'status' => $d->orphan->status->value,
                    'status_label' => $d->orphan->status->label(),
                    'family_code' => $d->orphan->family?->family_code,
                    'guardian' => $d->orphan->family?->guardian?->full_name,
                    'guardian_relation' => $d->orphan->family?->guardian?->relation?->label(),
                    // حال المستندات الثلاثة المطلوبة: مؤشر الاكتمال في القائمة
                    'required' => $this->requiredState($d->orphan),
                ],
            ]),
            'meta' => [
                'total' => $documents->total(), 'current_page' => $documents->currentPage(), 'last_page' => $documents->lastPage(),
                'type_counts' => (clone $base)->toBase()->select('type', DB::raw('COUNT(*) as aggregate'))
                    ->groupBy('type')->pluck('aggregate', 'type')->map(fn ($n) => (int) $n),
                'all_count' => (clone $base)->count(),
                'orphans_count' => (clone $base)->distinct()->count('orphan_id'),
                // قرارات المراجع اليوم (بيوم المنظمة): عدّاد إنجازه
                'decided_today' => $this->decidedToday($request),
            ],
        ]);
    }

    /**
     * ما ينتظر قراراً: لا تحقّق ولا رفض، في نطاق من يرى، وبالبحث عن الطفل.
     * الأنواع تُعدّ على هذا، فتبويب النوع يقول كم فيه قبل أن يُضغط.
     */
    private function pending(Request $request): Builder
    {
        $user = $request->user();
        $country = $user->isAdmin() ? null : $user->assignedCountryId();

        return OrphanDocument::query()
            ->whereNull('verified_at')
            ->whereNull('rejected_at')
            ->when($country !== null, fn ($q) => $q->whereHas(
                'orphan.family', fn ($f) => $f->where('country_id', $country),
            ))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('orphan', fn ($o) => $o->search($request->string('q')->toString())));
    }

    public function store(Request $request, Orphan $orphan): JsonResponse
    {
        $this->authorize('update', $orphan);
        abort_unless($request->user()->can('documents.manage'), 403);

        $validated = $request->validate([
            'type' => ['required', Rule::in(DocumentType::values())],
            'file' => [
                'required', 'file',
                'mimetypes:'.implode(',', config('kafala.uploads.document_mimes')),
                'max:'.config('kafala.uploads.max_size_kb'),
            ],
        ], [
            'file.mimetypes' => 'الملف يجب أن يكون PDF أو صورة.',
            'file.max' => 'حجم الملف أكبر من المسموح.',
        ]);

        $file = $request->file('file');

        $document = $orphan->documents()->create([
            'type' => $validated['type'],
            'path' => $file->store("documents/{$orphan->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        /*
         * المسوّدة التي اكتملت مستنداتها الثلاثة تدخل طابور التوثيق وحدها.
         * كان الملف الجديد يبقى «مسوّدة» إلى الأبد، والطابور وشارته يعدّان
         * «بانتظار التوثيق» وحدها، فلا يعرف من يوثّق أن ملفاً ينتظره.
         */
        $required = array_map(fn (DocumentType $t) => $t->value, [DocumentType::BirthCertificate, DocumentType::DeathCertificate, DocumentType::GuardianshipProof]);
        $have = $orphan->documents()->whereNull('rejected_at')->pluck('type')->map(fn ($t) => $t instanceof DocumentType ? $t->value : $t)->unique()->all();
        $promoted = $orphan->status === OrphanStatus::Draft && array_diff($required, $have) === [];
        if ($promoted) {
            $orphan->forceFill(['status' => OrphanStatus::PendingVerification])->save();
        }

        return response()->json([
            'message' => $promoted
                ? 'رُفع المستند. اكتملت المستندات فدخل الملف طابور التوثيق.'
                : 'رُفع المستند. يحتاج تحقّقاً قبل أن يُحتسب في توثيق الملف.',
            'data' => new OrphanDocumentResource($document),
        ], 201);
    }

    public function verify(Request $request, OrphanDocument $document): JsonResponse
    {
        // مستندات بلد آخر لا يتحقق منها المشرف، كما لا يراها
        $this->authorize('view', $document->orphan);

        /*
         * التحقق جزء من التوثيق، فصلاحيته صلاحية التوثيق لا صلاحية الرفع.
         * ومن رفع المستند لا يتحقق منه: كان المشرف يرفع ويتحقق بنفسه، فيمرّر
         * شخص واحد الملف كله، وهو ما وُجد فصل المهام لمنعه.
         */
        abort_unless($request->user()->can('orphans.verify'), 403, 'التحقق من المستندات لمن يوثّق الملفات.');
        abort_if($document->uploaded_by === $request->user()->id, 422, 'رفعت هذا المستند بنفسك، فيتحقق منه غيرك.');
        abort_if($document->verified_at !== null, 409, 'تُحقِّق من هذا المستند من قبل.');
        abort_if($document->rejected_at !== null, 409, 'رُفض هذا المستند. يُرفع بدلاً منه مستند جديد.');

        $document->update([
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        activity()->performedOn($document->orphan)->causedBy($request->user())->event('document_verified')
            ->withProperties(['document_id' => $document->id, 'type' => $document->type->value])
            ->log('تحقّق من مستند');

        return response()->json([
            'message' => 'تُحقِّق من المستند.',
            'data' => new OrphanDocumentResource($document->fresh()),
            // اكتملت المستندات الثلاثة والملف لم يُوثَّق: الخطوة التالية التوثيق
            'orphan_ready' => $document->orphan->verified_at === null && $this->documentsComplete($document->orphan),
        ]);
    }

    /**
     * الرفض بسبب يقرؤه رافع المستند في ملف الطفل، فيرفع بدلاً منه. يخرج
     * من الطابور ولا يُحتسب في التوثيق، ويبقى أثره في الملف والسجل.
     */
    public function reject(Request $request, OrphanDocument $document): JsonResponse
    {
        $this->authorize('view', $document->orphan);
        abort_unless($request->user()->can('orphans.verify'), 403, 'رفض المستندات لمن يوثّق الملفات.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'reason.required' => 'اكتب سبب الرفض، يقرؤه من رفع المستند.',
            'reason.min' => 'وضّح السبب أكثر.',
            'reason.max' => 'اختصر السبب إلى 255 حرفاً.',
        ]);

        $updated = OrphanDocument::query()->whereKey($document->id)
            ->whereNull('verified_at')->whereNull('rejected_at')
            ->update(['rejected_at' => now(), 'rejected_by' => $request->user()->id, 'rejection_reason' => $validated['reason']]);
        abort_if($updated === 0, 409, 'سبق اتخاذ قرار في هذا المستند.');

        activity()->performedOn($document->orphan)->causedBy($request->user())->event('document_rejected')
            ->withProperties(['document_id' => $document->id, 'type' => $document->type->value, 'reason' => $validated['reason']])
            ->log('رفض مستنداً');

        return response()->json([
            'message' => 'رُفض المستند، ويظهر سببه في ملف الطفل.',
            'data' => new OrphanDocumentResource($document->fresh()),
        ]);
    }

    /** @return array<string, string> verified | pending | rejected | missing */
    private function requiredState(Orphan $orphan): array
    {
        $out = [];
        foreach ([DocumentType::BirthCertificate, DocumentType::DeathCertificate, DocumentType::GuardianshipProof] as $type) {
            $docs = $orphan->documents->filter(fn ($d) => $d->type === $type);
            $out[$type->value] = match (true) {
                $docs->contains(fn ($d) => $d->verified_at !== null) => 'verified',
                $docs->contains(fn ($d) => $d->rejected_at === null) => 'pending',
                $docs->isNotEmpty() => 'rejected',
                default => 'missing',
            };
        }

        return $out;
    }

    private function decidedToday(Request $request): int
    {
        $since = now(config('kafala.display_timezone'))->startOfDay()->utc();
        $id = $request->user()->id;

        return OrphanDocument::query()
            ->where(fn ($q) => $q->where('verified_by', $id)->where('verified_at', '>=', $since))
            ->orWhere(fn ($q) => $q->where('rejected_by', $id)->where('rejected_at', '>=', $since))
            ->count();
    }

    private function documentsComplete(Orphan $orphan): bool
    {
        $verified = $orphan->documents()->whereNotNull('verified_at')->pluck('type')
            ->map(fn ($t) => $t instanceof DocumentType ? $t->value : $t)->unique()->all();

        return array_diff([DocumentType::BirthCertificate->value, DocumentType::DeathCertificate->value, DocumentType::GuardianshipProof->value], $verified) === [];
    }

    public function download(Request $request, OrphanDocument $document): StreamedResponse
    {
        $this->authorize('view', $document->orphan);
        abort_unless($request->user()->can('documents.manage'), 403);

        abort_unless(Storage::disk('local')->exists($document->path), 404, 'الملف غير موجود على الخادم.');

        activity()
            ->performedOn($document->orphan)
            ->causedBy($request->user())
            ->withProperties(['document_id' => $document->id, 'type' => $document->type->value])
            ->log('نزّل مستند تحقق');

        /*
         * العرض داخل اللوحة (`?inline=1`): الملف بنوعه المحفوظ (PDF أو صورة،
         * فُحص نوعه الحقيقي عند الرفع)، ومعزولاً بـ CSP sandbox فلا يشغّل
         * سكربتاً على أصل اللوحة حتى لو احتوى PDF واحداً.
         */
        if ($request->boolean('inline')) {
            return Storage::disk('local')->response($document->path, $document->original_name, [
                'Content-Type' => $document->mime,
                'Content-Security-Policy' => 'sandbox',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, OrphanDocument $document): JsonResponse
    {
        $this->authorize('update', $document->orphan);
        abort_unless($request->user()->can('documents.manage'), 403);

        // مستند مُتحقَّق منه لا يُحذف: توثيق الملف قائم عليه
        abort_if($document->verified_at !== null, 422, 'لا يُحذف مستند مُتحقَّق منه — توثيق الملف قائم عليه.');

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'حُذف المستند.']);
    }
}

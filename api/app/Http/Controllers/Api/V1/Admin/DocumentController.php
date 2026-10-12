<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrphanDocumentResource;
use App\Models\Orphan;
use App\Models\OrphanDocument;
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
        abort_unless($request->user()->can('documents.manage'), 403);

        $user = $request->user();
        $country = $user->isAdmin() ? null : $user->assignedCountryId();

        $documents = OrphanDocument::query()
            ->whereNull('verified_at')
            ->when($country !== null, fn ($q) => $q->whereHas(
                'orphan.family', fn ($f) => $f->where('country_id', $country),
            ))
            ->with(['orphan:id,reference_code,first_name,photo_path,status', 'uploader:id,name'])
            ->oldest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($documents->items())->map(fn (OrphanDocument $d) => [
                ...(new OrphanDocumentResource($d))->toArray($request),
                'uploader' => $d->uploader?->name,
                'orphan' => [
                    'id' => $d->orphan->id,
                    'reference_code' => $d->orphan->reference_code,
                    'name' => $d->orphan->first_name,
                    'status' => $d->orphan->status->value,
                    'status_label' => $d->orphan->status->label(),
                ],
            ]),
            'meta' => ['total' => $documents->total(), 'current_page' => $documents->currentPage(), 'last_page' => $documents->lastPage()],
        ]);
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

        return response()->json([
            'message' => 'رُفع المستند. يحتاج تحقّقاً قبل أن يُحتسب في توثيق الملف.',
            'data' => new OrphanDocumentResource($document),
        ], 201);
    }

    public function verify(Request $request, OrphanDocument $document): JsonResponse
    {
        // مستندات بلد آخر لا يتحقق منها المشرف، كما لا يراها
        $this->authorize('view', $document->orphan);
        abort_unless($request->user()->can('documents.manage'), 403);

        $document->update([
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'تُحقِّق من المستند.',
            'data' => new OrphanDocumentResource($document->fresh()),
        ]);
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

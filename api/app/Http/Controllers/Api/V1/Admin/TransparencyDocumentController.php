<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransparencyDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * وثائق الشفافية: يرفعها المدير، وتُنشر على صفحة الشفافية فوراً.
 *
 * PDF وحده ويُفحص نوعه الحقيقي (`mimes` لا الامتداد). ورفعٌ لنوعٍ وسنةٍ
 * موجودين يستبدل الملف القديم ويحذفه، فلا تتراكم نسخ ولا تبقى روابط ميتة.
 */
class TransparencyDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        return response()->json([
            'data' => TransparencyDocument::query()->with('uploader:id,name')->orderBy('kind')->orderByDesc('year')->get()
                ->map(fn (TransparencyDocument $d) => [
                    'id' => $d->id,
                    ...$d->toPublic(),
                    'uploaded_by' => $d->uploader?->name,
                ]),
            'meta' => [
                'kinds' => collect(TransparencyDocument::KINDS)->map(fn ($k, $key) => ['value' => $key, 'label' => $k['label'], 'yearly' => $k['yearly']])->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        $validated = $request->validate([
            'kind' => ['required', Rule::in(array_keys(TransparencyDocument::KINDS))],
            'year' => ['nullable', 'integer', 'between:2000,'.(now()->year + 1)],
            'title' => ['nullable', 'string', 'max:160'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'file.mimes' => 'الوثيقة PDF فقط.',
            'file.max' => 'الملف أكبر من 20 ميغابايت.',
        ]);

        $kind = TransparencyDocument::KINDS[$validated['kind']];
        $year = $kind['yearly'] ? ($validated['year'] ?? null) : null;
        abort_if($kind['yearly'] && $year === null, 422, 'اختر سنة الوثيقة.');

        $file = $request->file('file');
        $path = $file->store('transparency', 'public');

        $doc = DB::transaction(function () use ($validated, $year, $kind, $path, $file, $request) {
            // السنة الفارغة (التراخيص) بـ whereNull: `year = NULL` لا يطابق شيئاً، والفهرس
            // الفريد لا يمنع تكرار NULL، فكان كل رفعٍ يُنشئ صفاً جديداً
            $existing = TransparencyDocument::where('kind', $validated['kind'])
                ->when($year === null, fn ($q) => $q->whereNull('year'), fn ($q) => $q->where('year', $year))
                ->lockForUpdate()->first();
            $old = $existing?->file_path;

            $attributes = [
                'title' => ($validated['title'] ?? null) ?: $kind['label'].($year ? " {$year}" : ''),
                'file_path' => $path,
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
                'published_at' => now(),
            ];
            $doc = $existing
                ? tap($existing)->update($attributes)
                : TransparencyDocument::create(['kind' => $validated['kind'], 'year' => $year, ...$attributes]);

            if ($old && $old !== $path) {
                Storage::disk('public')->delete($old);
            }

            return $doc;
        });

        Cache::forget('transparency.documents');
        activity()->performedOn($doc)->causedBy($request->user())->event('published')
            ->withProperties(['attributes' => ['title' => $doc->title]])
            ->log('نشر وثيقة شفافية');

        return response()->json(['message' => 'نُشرت الوثيقة على صفحة الشفافية.', 'data' => $doc->toPublic() + ['id' => $doc->id]], 201);
    }

    public function destroy(Request $request, TransparencyDocument $document): JsonResponse
    {
        abort_unless($request->user()->can('content.manage'), 403);

        Storage::disk('public')->delete($document->file_path);
        $title = $document->title;
        $document->delete();

        Cache::forget('transparency.documents');
        activity()->causedBy($request->user())->event('deleted')
            ->withProperties(['old' => ['title' => $title]])
            ->log('حذف وثيقة شفافية');

        return response()->json(['message' => 'حُذفت الوثيقة من صفحة الشفافية.']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\MessageSafety;
use Illuminate\Validation\Rule;

/**
 * مراجعة الرسائل بين الكفلاء والأطفال.
 *
 * هذا المتحكّم هو التطبيق العملي لسياسة حماية الطفل المعلنة في الأسئلة
 * الشائعة: «رسائل تمرّ بمشرف ميداني». بلا هذه الشاشة يبقى الوعد بلا آلية.
 */
class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('messages.review'), 403);

        $status = $request->string('status')->toString();
        $pendingView = $status === MessageStatus::PendingReview->value || $request->boolean('queue');

        $messages = $this->filtered($request)
            ->with([
                'sponsorship:id,reference_code,orphan_id,sponsor_id',
                'sponsorship.orphan:id,first_name,last_name,reference_code,photo_path',
                'sponsorship.sponsor:id,user_id,sponsor_code',
                'sponsorship.sponsor.user:id,name',
                'reviewer:id,name',
            ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($request->boolean('queue'), fn ($q) => $q->where('status', MessageStatus::PendingReview))
            // الطابور الأقدم أولاً: من انتظر أطول يُراجَع قبل غيره؛ وغيره الأحدث أولاً
            ->when($pendingView, fn ($q) => $q->oldest('created_at'), fn ($q) => $q->latest('created_at'))
            ->orderBy('id')
            ->paginate($this->perPage($request, 30));

        $base = $this->filtered($request);
        $counts = (clone $base)->toBase()->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);

        return response()->json([
            'data' => MessageResource::collection($messages->items()),
            'meta' => [
                'total' => $messages->total(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'status_counts' => $counts,
                'all_count' => $counts->sum(),
                'pending' => Message::where('status', MessageStatus::PendingReview)->count(),
                // ما ينتظر المراجعة وفيه ما يستدعي النظر
                'flagged_pending' => (clone $base)->where('status', MessageStatus::PendingReview)
                    ->whereRaw('body REGEXP ?', [MessageSafety::sqlPattern()])->count(),
            ],
        ]);
    }

    /** الفلاتر المشتركة بين الطابور وعدّاداته، عدا الحالة */
    private function filtered(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return Message::query()
            ->when(in_array($request->string('direction')->toString(), ['sponsor', 'orphan'], true),
                fn ($q) => $q->where('sender_type', $request->string('direction')->toString()))
            ->when($request->boolean('flagged'), fn ($q) => $q->whereRaw('body REGEXP ?', [MessageSafety::sqlPattern()]))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('sponsorship', function ($s) use ($request): void {
                $term = addcslashes($request->string('q')->toString(), '%_\\');
                $s->where('reference_code', 'like', "%{$term}%")
                    ->orWhereHas('orphan', fn ($o) => $o->where('first_name', 'like', "{$term}%")->orWhere('reference_code', 'like', "%{$term}%"))
                    ->orWhereHas('sponsor.user', fn ($u) => $u->where('name', 'like', "%{$term}%"));
            }));
    }

    /**
     * سياق الرسالة: آخر ما دار بين الكافل والطفل في الكفالة نفسها. ردٌّ لا
     * يُفهم وحده («نعم، وأنا كذلك») يُفهم حين يُرى ما قبله.
     */
    public function thread(Request $request, Message $message): JsonResponse
    {
        abort_unless($request->user()->can('messages.review'), 403);

        $thread = Message::query()
            ->where('sponsorship_id', $message->sponsorship_id)
            ->where('created_at', '<=', $message->created_at)
            ->with('reviewer:id,name')
            ->latest('created_at')->latest('id')
            ->limit(12)->get()->reverse()->values();

        return response()->json(['data' => MessageResource::collection($thread)]);
    }

    /**
     * «وصلت للطفل»: رسالة الكافل المعتمدة سُلّمت في زيارة. بوابة الكافل تقول
     * له «تصل الطفل في الزيارة القادمة»، ولم يكن في اللوحة ما يسجّل وصولها.
     */
    public function deliver(Request $request, Message $message): JsonResponse
    {
        abort_unless($request->user()->can('messages.review') || $request->user()->can('visits.record'), 403);

        $affected = Message::whereKey($message->id)
            ->where('status', MessageStatus::Approved)
            ->update(['status' => MessageStatus::Delivered]);

        abort_if($affected === 0, 409, 'لا تُسجَّل «وصلت» إلا لرسالة معتمدة لم تُسلَّم بعد.');

        activity()->performedOn($message)->causedBy($request->user())->event('delivered')->log('سلّم رسالة للطفل');

        return response()->json([
            'message' => 'سُجّل وصول الرسالة للطفل.',
            'data' => new MessageResource($message->fresh()),
        ]);
    }

    public function review(Request $request, Message $message): JsonResponse
    {
        abort_unless($request->user()->can('messages.review'), 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:255'],
        ], [
            'reason.required_if' => 'اذكر سبب الرفض — يراه الكافل ليعرف ما ينبغي تعديله.',
        ]);

        /*
         * القرار نهائي ويُتّخذ مرة واحدة. التحديث مشروط بأن الرسالة ما زالت
         * بانتظار المراجعة: رسالة اعتُمدت ووصلت الطفل لا تُرفض بعدها، ومراجعان
         * في اللحظة نفسها لا يكتب أحدهما فوق قرار الآخر، فالثاني يجد صفراً.
         * والكتابة عبر الاستعلام لا `update()`: الحالة محميّة من التعبئة الجماعية.
         */
        $decision = $validated['decision'] === 'approve' ? MessageStatus::Approved : MessageStatus::Rejected;

        $affected = Message::whereKey($message->id)
            ->where('status', MessageStatus::PendingReview)
            ->update([
                'status' => $decision,
                'rejection_reason' => $decision === MessageStatus::Rejected ? $validated['reason'] : null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

        abort_if($affected === 0, 409, 'رُوجعت هذه الرسالة من قبل.');

        activity()->performedOn($message)->causedBy($request->user())->event('reviewed')
            ->withProperties(['decision' => $decision->value, 'reason' => $validated['reason'] ?? null])
            ->log($decision === MessageStatus::Approved ? 'اعتمد رسالة' : 'رفض رسالة');

        return response()->json([
            'message' => $validated['decision'] === 'approve' ? 'اعتُمدت الرسالة.' : 'رُفضت الرسالة.',
            'data' => new MessageResource($message->fresh()),
        ]);
    }
}

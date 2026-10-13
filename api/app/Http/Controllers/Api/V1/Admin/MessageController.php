<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $messages = Message::query()
            ->with([
                'sponsorship:id,reference_code,orphan_id,sponsor_id',
                'sponsorship.orphan:id,first_name,reference_code',
                'reviewer:id,name',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('queue'), fn ($q) => $q->awaitingReview())
            ->latest('created_at')
            ->paginate($this->perPage($request, 25));

        return response()->json([
            'data' => MessageResource::collection($messages->items()),
            'meta' => [
                'total' => $messages->total(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'pending' => Message::where('status', MessageStatus::PendingReview)->count(),
            ],
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

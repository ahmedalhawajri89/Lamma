<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Sponsorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Enums\MessageStatus;
use App\Models\Setting;

/**
 * رسائل الكافل للطفل.
 *
 * لا يمرّر هذا المتحكّم حقل `status` إطلاقاً: القيمة الافتراضية في قاعدة
 * البيانات هي `pending_review`، فكل رسالة تدخل طابور المراجعة حتماً.
 * جعْل المراجعة قيداً في المخطّط لا سطراً في الكود يعني أنها لا تُنسى.
 */
class MessageController extends Controller
{
    public function index(Request $request, Sponsorship $sponsorship): JsonResponse
    {
        $this->authorize('message', $sponsorship);

        return response()->json([
            'data' => MessageResource::collection(
                // رسائله هو كلها، أما ما كتبه الطفل فبعد اعتماده وحده: رسالةٌ
                // بانتظار المراجعة أو مرفوضة قد تحمل عنواناً أو رقم هاتف
                $sponsorship->messages()
                    ->where(fn ($q) => $q->where('sender_type', 'sponsor')
                        ->orWhereIn('status', [MessageStatus::Approved->value, 'delivered']))
                    ->latest()->limit(50)->get(),
            ),
        ]);
    }

    public function store(Request $request, Sponsorship $sponsorship): JsonResponse
    {
        $this->authorize('message', $sponsorship);

        // المراسلة لكفالة قائمة مدفوعة: الكافل السابق أو الحاجز بلا دفع لا يراسل الطفل
        abort_unless(
            in_array($sponsorship->status, [\App\Enums\SponsorshipStatus::Active, \App\Enums\SponsorshipStatus::Paused], true),
            403,
            __('المراسلة متاحة للكفالة القائمة.'),
        );

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'body.required' => __('اكتب رسالتك أولاً.'),
            'body.max' => __('الرسالة طويلة. اجعلها أقصر من ألفي حرف.'),
        ]);

        $message = $sponsorship->messages()->create([
            'sender_type' => 'sponsor',
            'body' => $validated['body'],
        ]);

        /*
         * إعداد «مراجعة رسائل الكفلاء» في لوحة الإدارة. كان مفتاحاً لا يقرؤه
         * أحد: المراجعة دائمة أيّاً كانت قيمته. حين يُطفأ تُعتمد الرسالة فوراً،
         * والحالة تُكتب صراحةً لأنها محميّة من التعبئة الجماعية.
         */
        $moderated = (bool) (Setting::cached()['policy.moderate_messages'] ?? true);
        if (! $moderated) {
            $message->forceFill(['status' => MessageStatus::Approved, 'reviewed_at' => now()])->save();
        }

        return response()->json([
            'message' => $moderated
                ? __('وصلتنا رسالتك. يراجعها المشرف الميداني قبل إيصالها للطفل، حمايةً لخصوصيته.')
                : __('وصلت رسالتك.'),
            'data' => new MessageResource($message),
        ], 201);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Sponsorship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                $sponsorship->messages()->latest()->limit(50)->get(),
            ),
        ]);
    }

    public function store(Request $request, Sponsorship $sponsorship): JsonResponse
    {
        $this->authorize('message', $sponsorship);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'body.required' => 'اكتب رسالتك أولاً.',
            'body.max' => 'الرسالة طويلة. اجعلها أقصر من ألفي حرف.',
        ]);

        $message = $sponsorship->messages()->create([
            'sender_type' => 'sponsor',
            'body' => $validated['body'],
        ]);

        return response()->json([
            'message' => 'وصلتنا رسالتك. يراجعها المشرف الميداني قبل إيصالها للطفل، حمايةً لخصوصيته.',
            'data' => new MessageResource($message),
        ], 201);
    }
}

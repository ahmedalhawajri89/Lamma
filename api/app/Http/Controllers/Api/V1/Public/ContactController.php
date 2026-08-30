<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * نموذجا التواصل والنشرة.
 *
 * كلاهما مفتوح بلا مصادقة، فكلاهما هدفٌ للسبام. الحمايتان هنا:
 *  · تحديد المعدّل على المسار نفسه (في routes/api.php).
 *  · حقل `website` وهمي مخفي بصرياً — الروبوت يملأ كل حقل يجده، والبشر
 *    لا يرون هذا الحقل أصلاً. ملؤه يعني روبوتاً، فنردّ بنجاح كاذب ولا نحفظ:
 *    الردّ بخطأ يعلّم الروبوت كيف يتفادى الفخ.
 */
class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180'],
            'phone' => ['nullable', 'string', 'max:24'],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:10', 'max:4000'],

            // لا قاعدة تحقق على حقل المصيدة عمداً: قاعدة `size:0` كانت تردّ
            // 422 فتخبر الروبوت أن الحقل فخّ. الفحص يقع بعد التحقق ويردّ
            // نجاحاً كاذباً.
        ], [
            'name.required' => 'الاسم مطلوب.',
            'email.email' => 'صيغة البريد غير صحيحة.',
            'body.required' => 'الرسالة مطلوبة.',
            'body.min' => 'الرسالة قصيرة جداً.',
        ]);

        if ($request->filled('website')) {
            return response()->json(['message' => 'وصلتنا رسالتك. سنردّ خلال يوم عمل.']);
        }

        ContactMessage::create([
            ...collect($validated)->except('website')->all(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'وصلتنا رسالتك. سنردّ خلال يوم عمل.',
        ], 201);
    }

    /**
     * الاشتراك بتأكيد مزدوج: لا يُعدّ مشتركاً قبل أن يضغط الرابط في بريده.
     * بلا ذلك يستطيع أي شخص إغراق بريد غيره برسائلنا.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:180'],
            'source' => ['nullable', 'string', 'max:40'],
        ], [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد غير صحيحة.',
        ]);

        $subscriber = NewsletterSubscriber::firstOrNew([
            'email' => mb_strtolower($validated['email']),
        ]);

        if ($subscriber->exists && $subscriber->confirmed_at !== null && $subscriber->unsubscribed_at === null) {
            return response()->json(['message' => 'أنت مشترك معنا بالفعل.']);
        }

        $subscriber->fill([
            'confirm_token' => Str::random(48),
            'source' => $validated['source'] ?? 'footer',
        ])->save();

        // TODO: إرسال بريد التأكيد عند ضبط بوابة البريد

        return response()->json([
            'message' => 'أرسلنا رابط تأكيد إلى بريدك.',
        ], 201);
    }
}

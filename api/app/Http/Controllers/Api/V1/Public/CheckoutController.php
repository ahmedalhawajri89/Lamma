<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Payments\RecordPayment;
use App\Actions\Sponsors\RegisterSponsor;
use App\Actions\Sponsorships\CreateSponsorship;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SponsorshipStatus;
use App\Http\Controllers\Controller;
use App\Models\Orphan;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * الكفالة من صفحة الموقع.
 *
 * كانت الصفحة محاكاةً كاملة: زرّ الدفع لا يصل الخادم، ثم تقول صفحة الشكر
 * «تمّت الكفالة وأرسلنا إيصالاً». هنا تُنشأ الكفالة فعلاً وتُدفع:
 *
 *  · الزائر يكفل باسمه وبريده دون أن يغادر الصفحة: يُنشأ حسابه ويدخل، ويصله
 *    رابط تعيين كلمة مروره. وإن كان بريده مسجَّلاً يُطلب منه الدخول أولاً
 *    (لا تُنسب كفالة إلى حساب لم يُثبت صاحبه أنه هو).
 *  · البطاقة: رمزٌ من البوابة لا رقم البطاقة. نجاح الخصم يفعّل الكفالة، ورفضه
 *    يلغي الحجز فوراً فلا يُمسك الطفل بلا دفع.
 *  · التحويل البنكي: حجزٌ بانتظار التحويل حتى أيام السماح، مع تعليماته ومرجعه.
 */
class CheckoutController extends Controller
{
    /** حجوزات التحويل البنكي من العنوان نفسه في اليوم، أياً كان الحساب (فوق حدّ الكافل الواحد: 3) */
    public const DAILY_HOLDS_PER_IP = 5;

    /** ما تحتاجه الصفحة قبل الدفع: وضع البوابة، وبيانات التحويل، والدورات */
    public function config(PaymentGateway $gateway): JsonResponse
    {
        return response()->json(['data' => [
            'demo' => $gateway->isDemo(),
            'intervals' => CreateSponsorship::INTERVALS,
            'grace_days' => (int) config('kafala.billing.grace_days'),
            'bank' => config('kafala.bank'),
        ]]);
    }

    public function store(Request $request, CreateSponsorship $create, RecordPayment $record, PaymentGateway $gateway, RegisterSponsor $register): JsonResponse
    {
        $user = $request->user();
        abort_if($user !== null && $user->sponsor === null, 403, 'هذا حساب فريق العمل. الكفالة من حساب كافل.');

        $validated = $request->validate([
            'orphan_reference' => ['required', 'string', 'exists:orphans,reference_code'],
            'monthly_amount' => ['nullable', 'numeric', 'min:1', 'max:5000'],
            'interval_months' => ['required', 'integer', Rule::in(CreateSponsorship::INTERVALS)],
            'is_zakat' => ['sometimes', 'boolean'],
            'method' => ['required', Rule::in([PaymentMethod::Card->value, PaymentMethod::BankTransfer->value])],

            // الزائر وحده يكتب اسمه وبريده
            'name' => [Rule::requiredIf($user === null), 'nullable', 'string', 'min:3', 'max:120'],
            'email' => [Rule::requiredIf($user === null), 'nullable', 'email:rfc', 'max:180'],
            'phone' => ['nullable', 'string', 'max:24'],

            // البطاقة رمزٌ من البوابة وآخر أربعة أرقام للعرض، لا رقمها
            'card_token' => ['required_if:method,card', 'nullable', 'string', 'max:120'],
            'card_brand' => ['nullable', 'string', 'max:20'],
            'card_last4' => ['nullable', 'digits:4'],
        ], [
            'orphan_reference.exists' => 'لم نجد هذه الحالة.',
            'name.required' => 'اكتب اسمك الكامل.',
            'email.required' => 'اكتب بريدك الإلكتروني، إليه يصل الإيصال.',
            'card_token.required_if' => 'أكمل بيانات البطاقة.',
        ]);

        // الزائر وبريده مسجَّل: يدخل أولاً (لا تُنسب كفالة إلى حساب لم يُثبت صاحبه أنه هو)
        if ($user === null && User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'لديك حساب بهذا البريد. سجّل الدخول لإكمال الكفالة، واختياراتك محفوظة.',
                'code' => 'login_required',
            ], 409);
        }

        /*
         * حجز التحويل البنكي يُمسك حصة الطفل أيام السماح بلا مال، فيظهر مكتملاً
         * ويختفي من الفهرس. وحسابٌ جديد ببريدٍ مختلَق لا يكلّف شيئاً، فحدّ
         * «ثلاثة حجوزات لكل كافل» وحده لا يمنع من يفتح عشرة حسابات. لذا حدٌّ
         * يومي للحجوزات من العنوان نفسه أياً كان الحساب. البطاقة لا تحتاجه:
         * لا كفالة بلا خصمٍ ناجح.
         */
        $holdKey = 'checkout-hold:'.$request->ip();
        if ($validated['method'] === PaymentMethod::BankTransfer->value
            && RateLimiter::tooManyAttempts($holdKey, self::DAILY_HOLDS_PER_IP)) {
            return response()->json([
                'message' => 'وصلت إلى حدّ حجوزات التحويل البنكي اليوم. ادفع بالبطاقة، أو أكمل تحويل حجزٍ قائم من حسابك.',
                'code' => 'hold_limit',
            ], 429);
        }

        $orphan = Orphan::where('reference_code', $validated['orphan_reference'])->firstOrFail();

        /*
         * الحساب الجديد والكفالة معاً أو لا شيء: كان الحساب يُنشأ ويدخل صاحبه
         * قبل فحص الكفالة، فإن رُفضت (اكتمل تمويل الطفل، مبلغ خارج الحدّ) بقي
         * حسابٌ وجلسةٌ لكفالةٍ لم تقع. الدخول بعد نجاحها وحده.
         */
        $created = $user === null;
        [$user, $sponsorship] = DB::transaction(function () use ($user, $validated, $register, $create, $orphan): array {
            $user ??= $register->handle($validated['name'], $validated['email'], $validated['phone'] ?? null);

            return [$user, $create->handle(
                sponsor: $user->sponsor,
                orphan: $orphan,
                monthlyAmount: isset($validated['monthly_amount']) ? (float) $validated['monthly_amount'] : null,
                isZakat: (bool) ($validated['is_zakat'] ?? false),
                intervalMonths: (int) $validated['interval_months'],
                paymentMethod: $validated['method'],
            )];
        });
        $sponsor = $user->sponsor;

        if ($created) {
            Auth::login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }
        }

        if ($validated['method'] === PaymentMethod::BankTransfer->value) {
            RateLimiter::hit($holdKey, 86400);
        }

        $payment = null;

        if ($validated['method'] === PaymentMethod::Card->value) {
            // مفتاح التفرّد من العميل: نقرة ثانية أو إعادة إرسال لا تخصم مرتين
            $key = hash('sha256', 'checkout|'.$sponsorship->id.'|'.($request->header('Idempotency-Key') ?: Str::uuid()));
            $result = $gateway->charge($validated['card_token'], $sponsorship->cycleAmount(), $sponsorship->currency, $key);

            if (! $result->succeeded) {
                // الرفض يُحفظ للمتابعة، والحجز يُلغى فوراً فيعود الطفل لمن يكفله
                Payment::create([
                    'reference_code' => $record->nextReference(),
                    'sponsorship_id' => $sponsorship->id,
                    'sponsor_id' => $sponsor->id,
                    'amount' => $sponsorship->cycleAmount(),
                    'currency' => $sponsorship->currency,
                    'fx_rate' => 1,
                    'amount_base' => $sponsorship->cycleAmount(),
                    'method' => PaymentMethod::Card,
                    'gateway' => config('kafala.gateway'),
                    'status' => PaymentStatus::Failed,
                    'card_brand' => $validated['card_brand'] ?? null,
                    'card_last4' => $validated['card_last4'] ?? null,
                    'failure_reason' => $result->failureReason,
                    // مفتاح المحاولة الفاشلة غير مفتاح النجاح (والعمود 64 حرفاً)
                    'idempotency_key' => hash('sha256', $key.'|failed'),
                ]);
                $sponsorship->update([
                    'status' => SponsorshipStatus::Cancelled,
                    'cancellation_reason' => 'لم يتم الدفع عند الكفالة',
                    'ended_at' => now(),
                    'next_due_at' => null,
                ]);

                return response()->json([
                    'message' => $result->failureReason,
                    'code' => 'payment_failed',
                    'account_created' => $created,
                ], 402);
            }

            $payment = $record->handle(
                sponsorship: $sponsorship,
                idempotencyKey: $key,
                method: PaymentMethod::Card,
                cardBrand: $validated['card_brand'] ?? null,
                cardLast4: $validated['card_last4'] ?? null,
                gatewayReference: $result->reference,
            );
        }

        // الحساب الجديد يصله رابط تعيين كلمة مروره (هو رابط الاستعادة نفسه)
        if ($created) {
            Password::sendResetLink(['email' => $user->email]);
        }

        $sponsorship->refresh()->load('orphan');

        return response()->json([
            'message' => $payment !== null
                ? 'تمّت الكفالة. شكراً لك.'
                : 'حُجزت الكفالة بانتظار تحويلك.',
            'data' => [
                'reference' => $sponsorship->reference_code,
                'status' => $sponsorship->status->value,
                // الرسم لا الصورة، والجنس لصيغة الشكر («كفالتها»)
                'orphan' => [
                    'reference' => $orphan->reference_code,
                    'name' => $orphan->first_name,
                    'photo' => $orphan->illustrationUrl(),
                    'female' => $orphan->gender === \App\Enums\Gender::Female,
                ],
                'monthly_amount' => (float) $sponsorship->monthly_amount,
                'interval_months' => $sponsorship->interval_months,
                'cycle_amount' => $sponsorship->cycleAmount(),
                'currency' => $sponsorship->currency,
                'is_zakat' => $sponsorship->is_zakat,
                'method' => $validated['method'],
                'next_due_at' => $sponsorship->next_due_at?->toDateString(),
                'receipt_number' => $payment?->receipt_number,
                'card_last4' => $payment?->card_last4,
                'hold_expires_at' => $payment === null
                    ? now()->addDays((int) config('kafala.billing.grace_days'))->toDateString()
                    : null,
                'bank' => $payment === null ? config('kafala.bank') : null,
                'account_created' => $created,
                'demo' => $gateway->isDemo(),
            ],
        ], 201);
    }
}

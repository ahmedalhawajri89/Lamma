<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * بوابة الدفع.
 *
 * رقم البطاقة لا يصل خادمنا أبداً: الصفحة تحوّله عند البوابة إلى «رمز»
 * (token)، وهذا الرمز وحده ما يُرسَل هنا للخصم. ربط بوابة حقيقية (Stripe أو
 * Tap أو HyperPay) يعني تنفيذ هذه الواجهة وتغيير `PAYMENT_GATEWAY`، لا غير.
 */
interface PaymentGateway
{
    /** هل هي بوابة تجريبية لا تخصم مالاً حقيقياً؟ تُعلنها الصفحة بوضوح */
    public function isDemo(): bool;

    /**
     * الخصم الأول، والكافل حاضر. يحفظ البطاقة عند البوابة للأقساط القادمة
     * ويُعيد مرجعها في `GatewayResult::$paymentMethod`.
     */
    public function charge(string $token, float $amount, string $currency, string $idempotencyKey): GatewayResult;

    /**
     * قسطٌ دوري بالبطاقة المحفوظة، والكافل غائب (أمر الفوترة). مفتاح التفرّد
     * يمنع البوابة نفسها من الخصم مرتين لو أُعيد تشغيل الأمر.
     */
    public function chargeSaved(string $paymentMethod, float $amount, string $currency, string $idempotencyKey): GatewayResult;
}

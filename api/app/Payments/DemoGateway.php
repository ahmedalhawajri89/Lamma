<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * بوابة تجريبية: لا تخصم مالاً، وتحاكي ردود بوابة حقيقية برموز ثابتة.
 *
 *   tok_demo_ok        يُقبل   (بطاقة الاختبار 4242 4242 4242 4242)
 *   tok_demo_decline   يُرفض   (بطاقة الاختبار 4000 0000 0000 0002)
 *
 * والصفحة تُعلن «وضع تجريبي» ما دامت هذه البوابة مفعّلة، فلا يظن أحد أنه دفع.
 */
class DemoGateway implements PaymentGateway
{
    public function isDemo(): bool
    {
        return true;
    }

    public function charge(string $token, float $amount, string $currency, string $idempotencyKey): GatewayResult
    {
        return match ($token) {
            'tok_demo_ok' => GatewayResult::success('demo_ch_'.substr(hash('sha256', $idempotencyKey), 0, 16)),
            'tok_demo_decline' => GatewayResult::failure('رفض البنك العملية. جرّب بطاقة أخرى.'),
            default => GatewayResult::failure('بيانات البطاقة غير صالحة.'),
        };
    }
}

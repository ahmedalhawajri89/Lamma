<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * نتيجة محاولة خصم: نجحت بمرجع من البوابة، أو فشلت بسبب يُفهم.
 *
 * والخصم الأول بالبطاقة يُعيد معه `paymentMethod`: مرجعٌ عند البوابة يُخصم به
 * لاحقاً بلا الكافل (الأقساط الشهرية). رمز البطاقة نفسه لا يصلح إلا مرة.
 */
final class GatewayResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly ?string $reference,
        public readonly ?string $failureReason,
        public readonly ?string $paymentMethod = null,
    ) {}

    public static function success(string $reference, ?string $paymentMethod = null): self
    {
        return new self(true, $reference, null, $paymentMethod);
    }

    public static function failure(string $reason): self
    {
        return new self(false, null, $reason);
    }
}

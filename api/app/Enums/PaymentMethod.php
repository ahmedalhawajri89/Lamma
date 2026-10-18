<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * وسيلة الدفع. لا تُخزَّن أي بيانات بطاقة عدا العلامة وآخر أربعة أرقام.
 */
enum PaymentMethod: string
{
    use HasLabel;

    case Card = 'card';
    case ApplePay = 'apple_pay';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Card => __('بطاقة ائتمان'),
            self::ApplePay => 'Apple Pay',
            self::BankTransfer => __('تحويل بنكي'),
            self::Cash => __('نقداً'),
        };
    }
}

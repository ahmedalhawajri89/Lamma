<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * التحويل بين عملات الكفلاء وعملة الأساس.
 *
 * كل مبلغٍ يُخزَّن بعملته وبما يعادله بالأساس (amount_base)؛ وهذا مصدر
 * السعر الحاليّ لذلك. السعر المستعمل فعلاً يُحفظ على الدفعة، فتغيّر الأسعار
 * لاحقاً لا يعيد كتابة ما مضى.
 */
final class Fx
{
    private const CACHE_KEY = 'kafala.fx.rates';

    public static function base(): string
    {
        return (string) config('kafala.base_currency', 'USD');
    }

    /** عملات الكفلاء: ما يُعرض في صفحة الكفالة ويُخصم به. الأساس أولها. */
    public static function sponsorCurrencies(): array
    {
        return array_values(array_unique([self::base(), ...(array) config('kafala.sponsor_currencies', [])]));
    }

    public static function isSponsorCurrency(?string $currency): bool
    {
        return in_array(strtoupper((string) $currency), self::sponsorCurrencies(), true);
    }

    /** @return array<string, float> كم وحدة أساس تساوي وحدةٌ من كل عملة */
    public static function rates(): array
    {
        return Cache::remember(self::CACHE_KEY, 600, fn () => ExchangeRate::query()
            ->pluck('rate_to_base', 'currency')
            ->map(fn ($r) => (float) $r)
            ->put(self::base(), 1.0)
            ->all());
    }

    public static function rate(string $currency): float
    {
        $currency = strtoupper($currency);
        $rate = self::rates()[$currency] ?? null;

        if ($rate === null || $rate <= 0) {
            throw new InvalidArgumentException("لا سعر صرف للعملة {$currency}.");
        }

        return $rate;
    }

    public static function toBase(float $amount, string $currency): float
    {
        return round($amount * self::rate($currency), 2);
    }

    public static function fromBase(float $amountBase, string $currency): float
    {
        return round($amountBase / self::rate($currency), 2);
    }

    /**
     * مبلغ الأساس بعملة الكافل، مقرَّباً لأعلى إلى وحدةٍ كاملة: «كفالة كاملة
     * $65» تصير £52 لا £51.18. الزيادة كسرٌ من وحدة، ولصالح الطفل.
     */
    public static function friendly(float $amountBase, string $currency): float
    {
        if (strtoupper($currency) === self::base()) {
            return round($amountBase, 2);
        }

        return (float) ceil(round($amountBase / self::rate($currency), 4));
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

/**
 * تنبيهات حماية الطفل في الرسائل.
 *
 * المراجعة البشرية هي الحارس، وهذا يعينها: يُظهر في الرسالة ما ينبغي ألا
 * يمرّ بين كافل وطفل (وسيلة تواصل خارج المنصة، طلب مال، سؤال عن المسكن)،
 * فلا يفوت المراجعَ رقم هاتف في آخر سطر من رسالة طويلة. لا يرفض شيئاً
 * وحده: التنبيه سببٌ للنظر، لا حكم.
 */
class MessageSafety
{
    /** @var array<string, array{label: string, pattern: string}> */
    private const RULES = [
        'phone' => ['label' => 'رقم هاتف', 'pattern' => '/(?:\+|00)?\d[\d\s\-]{6,}\d/u'],
        'email' => ['label' => 'بريد إلكتروني', 'pattern' => '/[\w.+-]+@[\w-]+\.[\w.]+/u'],
        'link' => ['label' => 'رابط', 'pattern' => '/(?:https?:\/\/|www\.)\S+|\b\S+\.(?:com|net|org|me|ly)\b/iu'],
        'social' => [
            'label' => 'تواصل خارج المنصة',
            'pattern' => '/واتس(?:اب|آب)?|whats\s?app|تيلي?غرام|تلغرام|telegram|فيس\s?بوك|facebook|انستا(?:غرام|جرام)?|instagram|سناب|snapchat|تيك\s?توك|tiktok|ماسنجر|messenger/iu',
        ],
        'money' => [
            'label' => 'مال أو تحويل',
            'pattern' => '/حوال[ةه]|فلوس|ويسترن|western\s?union|paypal|باي\s?بال|رقم\s+حساب|iban|أرسل(?:ي)?\s+(?:لي|لك)\s+(?:مال|مبلغ|فلوس)|هدي[ةه]\s+مالي[ةه]/iu',
        ],
        'address' => [
            'label' => 'سؤال عن المسكن',
            'pattern' => '/عنوان(?:ك|كم|ي|كن)|وين\s+(?:ساكن|تسكن|بيتكم)|أين\s+(?:تسكن|تعيش|بيتكم)|موقع\s+(?:بيتكم|البيت)/iu',
        ],
    ];

    /**
     * @return array<int, array{type: string, label: string, matches: array<int, string>}>
     */
    public function flags(?string $body): array
    {
        if ($body === null || trim($body) === '') {
            return [];
        }

        $out = [];
        foreach (self::RULES as $type => $rule) {
            if (preg_match_all($rule['pattern'], $body, $m) > 0) {
                $out[] = ['type' => $type, 'label' => $rule['label'], 'matches' => array_values(array_unique(array_map(
                    fn (string $hit) => $this->wholeWords($body, trim($hit)),
                    $m[0],
                )))];
            }
        }

        return $out;
    }

    /**
     * المطابقة ممتدّة إلى الكلمة كاملة: «وين ساكن» من «وين ساكنين» كانت تُظلَّل
     * نصفَ كلمة، فينكسر وصل الحروف العربية عند حدّ التظليل.
     */
    private function wholeWords(string $body, string $hit): string
    {
        return preg_match('/\S*'.preg_quote($hit, '/').'\S*/u', $body, $m) === 1 ? $m[0] : $hit;
    }

    /**
     * التعبير نفسه لقاعدة البيانات (فلتر «فيها تنبيه»)، بصيغة REGEXP في MySQL.
     * أبسط من نسخة PHP عمداً: فلترٌ يُقرّب، والتنبيه في الصفّ هو الدقيق.
     */
    public static function sqlPattern(): string
    {
        return implode('|', [
            '[0-9][0-9 -]{6,}[0-9]',
            '@[[:alnum:]-]+\\.',
            'https?://|www\\.',
            'واتس|whats ?app|تلغرام|تيليغرام|telegram|فيسبوك|facebook|انستا|instagram|سناب|snapchat|تيك ?توك|tiktok',
            'حوالة|حواله|فلوس|ويسترن|western ?union|paypal|رقم حساب|iban',
            'عنوانك|عنوانكم|عنواني|وين ساكن|أين تسكن|بيتكم',
        ]);
    }
}

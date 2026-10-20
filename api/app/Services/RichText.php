<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * نصّ المقال المنسّق.
 *
 * المحرّر يُخرج HTML، والموقع العام يعرضه كما هو. فكل ما يُحفظ يمرّ من هنا
 * أولاً: يبقى ما يحتاجه مقال (فقرات، عناوين، تأكيد، قوائم، اقتباس، رابط،
 * صورة) ويُحذف كل ما سواه، فلا يصل سكربتٌ أو سمةُ حدث أو إطارٌ مضمَّن إلى
 * زائر الموقع، أُدخل عمداً أو لُصق من صفحة أخرى.
 */
class RichText
{
    private const BLOCKS = ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'hr', 'br'];

    private const INLINE = ['strong', 'b', 'em', 'i', 'u', 's'];

    private ?HtmlSanitizer $sanitizer = null;

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $clean = trim($this->sanitizer()->sanitize($html));

        // فقرات فارغة من ضغطات Enter المتتالية لا معنى لها على الموقع
        return preg_replace('#(<p>\s*(<br\s*/?>)?\s*</p>\s*)+#u', '', $clean) ?: null;
    }

    /** النصّ المجرّد: لعدّ الكلمات وزمن القراءة والبحث */
    public function toText(?string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('#<(/p|br|/h[23]|/li|/blockquote)>#i', "$0\n", (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * النصّ القديم (فقرات تفصلها أسطر فارغة) إلى HTML مهرَّب: ما كُتب قبل
     * المحرّر المنسّق يبقى كما هو بفقراته، لا يُفسَّر شيءٌ فيه وسماً.
     */
    public function fromPlain(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }

        return collect(preg_split('/(?:\r?\n[ \t]*){2,}/u', $text))
            ->map(fn ($p) => trim($p))
            ->filter()
            ->map(fn ($p) => '<p>'.nl2br(htmlspecialchars($p, ENT_QUOTES | ENT_HTML5, 'UTF-8'), false).'</p>')
            ->implode('');
    }

    /** هل النصّ HTML من المحرّر، أم نصٌّ قديم عادي؟ */
    public static function isHtml(?string $value): bool
    {
        return $value !== null && preg_match('#^\s*<(p|h2|h3|ul|ol|blockquote|hr)\b#i', $value) === 1;
    }

    private function sanitizer(): HtmlSanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            // الروابط الخارجية لا تمنح الصفحة المفتوحة سلطة على صفحتنا
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->allowElement('a', ['href', 'title'])
            ->allowElement('img', ['src', 'alt']);

        foreach ([...self::BLOCKS, ...self::INLINE] as $tag) {
            $config = $config->allowElement($tag);
        }

        return $this->sanitizer = new HtmlSanitizer($config);
    }
}

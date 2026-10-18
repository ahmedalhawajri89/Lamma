<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Locale;

/**
 * نصوص السجلّ بلغاتٍ أخرى في عمود translations واحد:
 * {"en": {"quote": "…", "role": "…"}}. العربية في أعمدتها كما هي، ولغةٌ
 * جديدة تُضاف فيه بلا ترحيل.
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
    }

    /**
     * حقلٌ نصي بلغة الصفحة: ترجمته إن وُجدت، وإلا الأصل العربي.
     * $orphan->translated('story')
     */
    public function translated(string $field): ?string
    {
        $locale = Locale::current();

        return Locale::isDefault($locale)
            ? $this->{$field}
            : ($this->translations[$locale][$field] ?? $this->{$field});
    }
}

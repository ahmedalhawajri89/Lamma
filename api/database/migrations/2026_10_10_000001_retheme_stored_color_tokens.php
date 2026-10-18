<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * نقل الألوان المخزّنة إلى توكنز الهوية الجديدة.
 *
 * `programs.accent` و`disbursement_categories.color` لا تخزّن ألواناً بل
 * أسماء متغيرات CSS من tokens.css. هوية «الطين والعنبر» حُذفت سلالمها
 * (clay و amber و olive و sand)، فصارت القيم القديمة تُحلّ إلى لا شيء:
 * شرائح حلقة الشفافية وأشرطة البرامج تختفي بلا خطأ.
 *
 * والقيم الجديدة أدوار دلالية (`--chart-*` و`--program-*`) لا سلالم، فتتبع
 * الوضع الليلي ولا تحتاج نقلاً آخر عند أي تغيير لوني قادم.
 *
 * لا يُستبدل إلا ما يطابق القيمة القديمة حرفياً، فأي قيمة عدّلها أحدٌ يدوياً
 * تبقى كما هي.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> slug => [قديم => جديد] */
    private const PROGRAMS = [
        'comprehensive' => ['var(--gradient-brand)' => 'var(--program-full)'],
        'education' => ['linear-gradient(135deg, var(--teal-700), var(--teal-600))' => 'var(--program-education)'],
        'health' => ['linear-gradient(135deg, var(--rose-700), var(--rose-600))' => 'var(--program-health)'],
        'family' => ['linear-gradient(135deg, var(--olive-600), var(--olive-500))' => 'var(--program-family)'],
    ];

    /** @var array<string, array<string, string>> */
    private const CATEGORIES = [
        'living' => ['var(--clay-600)' => 'var(--chart-1)'],
        'education' => ['var(--amber-500)' => 'var(--chart-4)'],
        'health' => ['var(--olive-600)' => 'var(--chart-2)'],
        'psychosocial' => ['var(--teal-700)' => 'var(--chart-5)'],
        'overhead' => ['var(--sand-400)' => 'var(--chart-muted)'],
    ];

    public function up(): void
    {
        $this->swap('programs', 'accent', self::PROGRAMS, flip: false);
        $this->swap('disbursement_categories', 'color', self::CATEGORIES, flip: false);
    }

    public function down(): void
    {
        $this->swap('programs', 'accent', self::PROGRAMS, flip: true);
        $this->swap('disbursement_categories', 'color', self::CATEGORIES, flip: true);
    }

    /** @param array<string, array<string, string>> $map */
    private function swap(string $table, string $column, array $map, bool $flip): void
    {
        foreach ($map as $slug => $pair) {
            $from = $flip ? current($pair) : key($pair);
            $to = $flip ? key($pair) : current($pair);

            DB::table($table)
                ->where('slug', $slug)
                ->where($column, $from)
                ->update([$column => $to]);
        }
    }
};

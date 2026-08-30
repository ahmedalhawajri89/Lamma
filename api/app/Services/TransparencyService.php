<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Disbursement;
use App\Models\FieldVisit;
use App\Models\Orphan;
use App\Models\Setting;
use App\Models\Sponsorship;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * الأرقام التي كانت مكتوبة يدوياً في data.js.
 *
 * `SPENDING` و `PROOF.sponsored` و `PROOF.toChild` و `PROOF.visits` — كلها
 * صارت استعلامات. الفرق ليس تقنياً: موقع يعِد بالشفافية ويكتب نسبه بيده
 * يطلب من الزائر أن يثق بلا دليل. هذه الدوال تجعل الرقم قابلاً للتدقيق.
 *
 * الكاش عشر دقائق: الصفحة العامة تُطلب آلاف المرات في الساعة، والصرف
 * يتغيّر مرات معدودة في اليوم.
 */
class TransparencyService
{
    private const CACHE_TTL = 600;

    /** @return array<int, array{label: string, value: float, color: ?string}> */
    public function spendingBreakdown(?int $year = null): array
    {
        $year ??= (int) now()->year;

        return Cache::remember("transparency.spending.{$year}", self::CACHE_TTL, function () use ($year): array {
            $total = (float) Disbursement::approved()->inYear($year)->sum('amount_base');

            if ($total <= 0.0) {
                return [];
            }

            return Disbursement::query()
                ->approved()
                ->inYear($year)
                ->join('disbursement_categories as c', 'c.id', '=', 'disbursements.category_id')
                ->selectRaw('c.name_ar, c.color, c.sort_order, SUM(disbursements.amount_base) as total')
                ->groupBy('c.id', 'c.name_ar', 'c.color', 'c.sort_order')
                ->orderBy('c.sort_order')
                ->get()
                ->map(static fn ($row): array => [
                    'label' => $row->name_ar,
                    'value' => round((float) $row->total * 100 / $total, 1),
                    'color' => $row->color,
                ])
                ->all();
        });
    }

    /** لوحة الثقة على الصفحة الرئيسية — PROOF في data.js. */
    public function proof(): array
    {
        return Cache::remember('transparency.proof', self::CACHE_TTL, function (): array {
            $overhead = (float) Disbursement::approved()
                ->join('disbursement_categories as c', 'c.id', '=', 'disbursements.category_id')
                ->where('c.is_overhead', true)
                ->sum('disbursements.amount_base');

            $total = (float) Disbursement::approved()->sum('amount_base');

            return [
                'sponsored' => Orphan::whereHas('sponsorships', fn ($q) => $q->active())->count(),
                'perDot' => 40,
                'toChild' => $total > 0 ? (int) round(100 - $overhead * 100 / $total) : 93,
                'startYear' => (int) (Setting::cached()['site.start_year'] ?? 2012),
                'visits' => FieldVisit::count(),
                'countries' => DB::table('families')
                    ->join('orphans', 'orphans.family_id', '=', 'families.id')
                    ->distinct()
                    ->count('families.country_id'),
                'monthlyCommitment' => round((float) Sponsorship::active()->sum('monthly_amount'), 2),
            ];
        });
    }

    /** الميزانية السنوية المنشورة — صفحة الشفافية. */
    public function annual(int $year): array
    {
        $income = (float) DB::table('payments')
            ->where('status', 'succeeded')
            ->whereYear('paid_at', $year)
            ->sum('amount_base');

        $spent = (float) Disbursement::approved()->inYear($year)->sum('amount_base');

        return [
            'year' => $year,
            'income' => round($income, 2),
            'spent' => round($spent, 2),
            'balance' => round($income - $spent, 2),
            'breakdown' => $this->spendingBreakdown($year),
        ];
    }
}

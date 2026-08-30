<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Disbursement;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * اعتماد التقرير الربعي ونشره للكافل.
 *
 * عند الاعتماد نأخذ **لقطة** من الصرف الفعلي في ذلك الربع ونخزّنها في
 * `spending_breakdown`. لماذا لقطة لا استعلام حيّ؟ لأن التقرير وثيقة أُرسلت
 * للكافل في تاريخ معيّن، فلا يجوز أن تتغيّر أرقامها بأثر رجعي إن عُدِّل
 * صرفٌ لاحقاً. الحساب الحيّ للوحة، واللقطة للوثيقة.
 */
class PublishReport
{
    public function approve(Report $report, User $approver): Report
    {
        if ($report->status !== ReportStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'لا يُعتمد إلا تقرير مُرسَل للمراجعة.',
            ]);
        }

        return DB::transaction(function () use ($report, $approver): Report {
            $report->update([
                'status' => ReportStatus::Approved,
                'approved_by' => $approver->id,
                'spending_breakdown' => $this->snapshotSpending($report),
            ]);

            return $report->refresh();
        });
    }

    public function publish(Report $report): Report
    {
        if ($report->status !== ReportStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'لا يُنشر إلا تقرير معتمَد.',
            ]);
        }

        $report->update([
            'status' => ReportStatus::Published,
            'published_at' => now(),
        ]);

        return $report->refresh();
    }

    /** @return array<string, float> */
    private function snapshotSpending(Report $report): array
    {
        $start = Carbon::create($report->period_year, ($report->period_quarter - 1) * 3 + 1, 1);
        $end = $start->copy()->addMonths(3)->subDay();

        return Disbursement::query()
            ->approved()
            ->where('orphan_id', $report->orphan_id)
            ->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
            ->join('disbursement_categories as c', 'c.id', '=', 'disbursements.category_id')
            ->selectRaw('c.slug, SUM(disbursements.amount_base) as total')
            ->groupBy('c.slug')
            ->pluck('total', 'slug')
            ->map(static fn ($v): float => round((float) $v, 2))
            ->all();
    }
}

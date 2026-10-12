<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.view') || $user->sponsor !== null;
    }

    /**
     * الكافل يرى التقرير المنشور لطفلٍ يكفله فقط —
     * لا لطفل كفله سابقاً وألغى، ولا لتقرير لم يُعتمد بعد.
     */
    public function view(User $user, Report $report): bool
    {
        if ($user->can('reports.view')) {
            return $this->withinScope($user, $report);
        }

        if ($user->sponsor === null || $report->status !== ReportStatus::Published) {
            return false;
        }

        return $user->sponsor->sponsorships()
            ->where('orphan_id', $report->orphan_id)
            ->live()
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('reports.create');
    }

    public function update(User $user, Report $report): bool
    {
        // بعد الاعتماد لا يعدّله كاتبه — وإلا فقد الاعتماد معناه
        if (in_array($report->status, [ReportStatus::Approved, ReportStatus::Published], true)) {
            return $user->can('reports.approve');
        }

        return $user->can('reports.create');
    }

    public function approve(User $user, Report $report): bool
    {
        // ولا يعتمد المشرف تقريره بنفسه
        return $user->can('reports.approve') && $report->supervisor_id !== $user->id;
    }

    public function publish(User $user): bool
    {
        return $user->can('reports.publish');
    }

    /** الإرجاع للتعديل قرارُ مراجعة، فشرطه شرط الاعتماد نفسه. */
    public function return(User $user, Report $report): bool
    {
        return $this->approve($user, $report) && $this->withinScope($user, $report);
    }

    /**
     * المشرف الميداني يرى تقارير بلده المُسنَد وحده، كما في `OrphanPolicy`.
     * والمدير ومن لا بلد له يرون الكل.
     */
    private function withinScope(User $user, Report $report): bool
    {
        $assigned = $user->assignedCountryId();

        if ($assigned === null || $user->isAdmin()) {
            return true;
        }

        return $report->orphan()->whereHas('family', fn ($f) => $f->where('country_id', $assigned))->exists();
    }
}

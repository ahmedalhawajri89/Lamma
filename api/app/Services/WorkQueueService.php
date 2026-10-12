<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MessageStatus;
use App\Enums\OrphanStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReportStatus;
use App\Models\ContactMessage;
use App\Models\Disbursement;
use App\Models\FieldVisit;
use App\Models\Message;
use App\Models\Orphan;
use App\Models\OrphanDocument;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * «بانتظارك»: ما ينتظر قراراً من هذا الموظف بالذات اليوم.
 *
 * المهام تُبنى من الصلاحيات لا من أسماء الأدوار، فدورٌ جديد أو صلاحية
 * تُضاف لمحاسب تُظهر مهمتها تلقائياً. والمشرف الميداني مقيَّد ببلده
 * المُسنَد في كل ما يخصّ الأيتام، كما في `OrphanPolicy`.
 *
 * كل مهمة تحمل `to`: الشاشة مع فلترها، فيفتح الزر العناصر المقصودة مباشرة.
 * أسماء الفلاتر هي نفسها التي تقبلها المتحكمات وتقرؤها شاشات اللوحة.
 */
class WorkQueueService
{
    /** @return array<int, array<string, mixed>> */
    public function tasks(User $user): array
    {
        $country = $user->isAdmin() ? null : $user->assignedCountryId();

        // يقيّد أي استعلام يمرّ بالطفل ببلد المشرف
        $inCountry = fn (Builder $q, string $path = 'family') => $country === null
            ? $q
            : $q->whereHas($path, fn ($f) => $f->where('country_id', $country));

        $tasks = [];

        if ($user->can('orphans.verify')) {
            $tasks[] = $this->task(
                'orphans_verify',
                $inCountry(Orphan::where('status', OrphanStatus::PendingVerification))->count(),
                'ملفات بانتظار التوثيق',
                'راجع المستندات ووثّق الملف ليُنشر على الموقع.',
                '/orphans', ['status' => OrphanStatus::PendingVerification->value],
            );
        }

        // التحقق من المستندات قرارُ توثيق، فهو لمن يوثّق الملفات
        if ($user->can('orphans.verify') && $user->can('documents.manage')) {
            $tasks[] = $this->task(
                'documents_verify',
                $inCountry(OrphanDocument::whereNull('verified_at'), 'orphan.family')->count(),
                'مستندات بانتظار التحقق',
                'لا يُوثَّق ملف قبل التحقق من مستنداته.',
                '/documents', [],
            );
        }

        if ($user->can('reports.approve')) {
            $tasks[] = $this->task(
                'reports_approve',
                $inCountry(Report::where('status', ReportStatus::Submitted), 'orphan.family')->count(),
                'تقارير بانتظار اعتمادك',
                'أرسلها المشرفون الميدانيون للمراجعة.',
                '/reports', ['status' => ReportStatus::Submitted->value],
            );
        }

        if ($user->can('reports.publish')) {
            $tasks[] = $this->task(
                'reports_publish',
                $inCountry(Report::where('status', ReportStatus::Approved), 'orphan.family')->count(),
                'تقارير معتمدة لم تُنشر',
                'لن يراها الكافل قبل نشرها.',
                '/reports', ['status' => ReportStatus::Approved->value],
            );
        }

        if ($user->can('messages.review')) {
            $tasks[] = $this->task(
                'messages_review',
                Message::where('status', MessageStatus::PendingReview)->count(),
                'رسائل بانتظار المراجعة',
                'لا تصل رسالة إلى الطفل قبل مراجعتها.',
                '/messages', ['queue' => 1],
            );
        }

        if ($user->can('disbursements.approve')) {
            $tasks[] = $this->task(
                'disbursements_approve',
                // من سجّل الصرف لا يعتمده، فلا يُعدّ عليه ما سجّله بنفسه
                Disbursement::whereNull('approved_at')
                    ->where(fn ($q) => $q->whereNull('recorded_by')->orWhere('recorded_by', '!=', $user->id))
                    ->count(),
                'صرف بانتظار اعتمادك',
                'سجّله غيرك، ولا يُحسب في الشفافية قبل اعتماده.',
                '/disbursements', ['pending_only' => 1],
            );
        }

        if ($user->can('payments.view')) {
            $tasks[] = $this->task(
                'payments_failed',
                Payment::where('status', PaymentStatus::Failed)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
                'دفعات فشلت هذا الشهر',
                'تواصل مع الكافل قبل أن تنقطع الكفالة.',
                '/payments', ['status' => PaymentStatus::Failed->value],
                urgent: true,
            );

            if ($user->can('sponsorships.view')) {
                $tasks[] = $this->task(
                    'sponsorships_due',
                    Sponsorship::active()
                        ->whereBetween('next_due_at', [now()->toDateString(), now()->addDays(7)->toDateString()])
                        ->count(),
                    'أقساط مستحقة خلال 7 أيام',
                    'للمتابعة قبل موعد الخصم.',
                    '/sponsorships', ['due_soon' => 1],
                );
            }
        }

        if ($user->can('content.manage')) {
            $tasks[] = $this->task(
                'contacts_open',
                ContactMessage::open()->count(),
                'رسائل تواصل لم يُرد عليها',
                'وعدنا بالرد خلال 72 ساعة.',
                '/contacts', [],
            );
        }

        if ($user->can('visits.record')) {
            $tasks[] = $this->task(
                'visits_overdue',
                $inCountry(FieldVisit::overdue(), 'orphan.family')->count(),
                'زيارات ميدانية متأخرة',
                'تجاوزت موعد الزيارة القادمة.',
                '/visits', ['overdue' => 1],
                urgent: true,
            );
        }

        if ($user->can('reports.create')) {
            $tasks[] = $this->task(
                'reports_returned',
                Report::where('status', ReportStatus::Draft)
                    ->where('supervisor_id', $user->id)
                    ->whereNotNull('returned_at')
                    ->count(),
                'تقارير أُرجعت إليك للتعديل',
                'اقرأ ملاحظة المراجع، عدّل، ثم أرسل من جديد.',
                '/reports', ['status' => ReportStatus::Draft->value, 'returned' => 1],
                urgent: true,
            );

            $tasks[] = $this->task(
                'reports_drafts',
                // المُرجَعة لها مهمتها أعلاه، فلا تُعدّ مرتين
                Report::where('status', ReportStatus::Draft)->where('supervisor_id', $user->id)->whereNull('returned_at')->count(),
                'مسوّدات تقارير لم تُرسلها',
                'أرسلها للاعتماد كي تصل الكافل.',
                '/reports', ['status' => ReportStatus::Draft->value],
            );
        }

        // العاجل بلا تمويل يخصّ المشرف في بلده؛ المدير يراه في قسم الحالات العاجلة
        if ($country !== null && $user->can('orphans.view')) {
            $tasks[] = $this->task(
                'orphans_urgent',
                $inCountry(Orphan::urgentUnfunded()->where('status', OrphanStatus::Active))->count(),
                'حالات عاجلة بلا تمويل في بلدك',
                'تأكد من اكتمال ملفاتها وصورها.',
                '/orphans', ['urgent' => 1, 'unfunded' => 1],
                urgent: true,
            );
        }

        return array_values(array_filter($tasks, fn (array $t) => $t['count'] > 0));
    }

    /** @param  array<string, mixed>  $query */
    private function task(
        string $key,
        int $count,
        string $title,
        string $hint,
        string $path,
        array $query,
        bool $urgent = false,
    ): array {
        return [
            'key' => $key,
            'count' => $count,
            'title' => $title,
            'hint' => $hint,
            'tone' => $urgent ? 'urgent' : 'normal',
            'to' => ['path' => $path, 'query' => (object) $query],
        ];
    }
}

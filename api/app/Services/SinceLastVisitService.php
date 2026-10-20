<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReportStatus;
use App\Enums\SponsorshipStatus;
use App\Models\ContactMessage;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * «منذ آخر دخول»: ما جرى والموظف غائب، مما لا يدخل طابور مهامه.
 *
 * «بانتظارك» تقول ما على الموظف أن يفعله؛ وهذه تقول ما حدث: دفعات فشلت،
 * كفالات أُنهيت، كفلاء جدد، استفسارات وصلت، تقارير أُرجعت إليه، وعمليات
 * حساسة قام بها غيره. كلٌّ بحسب صلاحية الموظف، وبرابطٍ إلى ما يعرضه.
 *
 * «آخر دخول» هو الدخول السابق لهذا (previous_login_at): الحالي يُكتب
 * لحظة الدخول، فالمقارنة به لا تُظهر شيئاً أبداً.
 */
class SinceLastVisitService
{
    /** @return array{since: ?string, first_visit: bool, items: array<int, array<string, mixed>>} */
    public function for(User $user): array
    {
        $first = $user->previous_login_at === null;
        // أول دخول: الأسبوع الماضي، كي لا تبدأ اللوحة فارغة ولا تسرد تاريخ المنصة
        $since = $user->previous_login_at ?? now()->subWeek();
        $fromDay = $since->copy()->timezone(config('kafala.display_timezone'))->toDateString();

        $items = [];

        if ($user->can('payments.view')) {
            $failed = Payment::where('status', PaymentStatus::Failed)->where('created_at', '>', $since);
            $n = (clone $failed)->count();
            if ($n) {
                $items[] = $this->item('payments_failed', 'دفعات فشلت', $n, 'bad',
                    '/payments', ['status' => PaymentStatus::Failed->value, 'from' => $fromDay],
                    (clone $failed)->with('sponsor:id,sponsor_code')->latest()->limit(3)->get()
                        ->map(fn (Payment $p) => "{$p->sponsor?->sponsor_code} · {$p->amount} {$p->currency}".($p->failure_reason ? " · {$p->failure_reason}" : ''))->all());
            }

            $paid = Payment::where('status', PaymentStatus::Succeeded)->where('paid_at', '>', $since);
            $count = (clone $paid)->count();
            if ($count) {
                $sum = number_format((float) (clone $paid)->sum('amount_base'), 0);
                $items[] = $this->item('payments_paid', 'دفعات نجحت', $count, 'good',
                    '/payments', ['status' => PaymentStatus::Succeeded->value, 'from' => $fromDay], [], "مجموعها \${$sum}");
            }

            $cancelled = Sponsorship::where('status', SponsorshipStatus::Cancelled)->where('ended_at', '>', $since);
            $n = (clone $cancelled)->count();
            if ($n) {
                $items[] = $this->item('sponsorships_cancelled', 'كفالات أُنهيت', $n, 'warn',
                    '/sponsorships', ['status' => SponsorshipStatus::Cancelled->value],
                    (clone $cancelled)->with('orphan:id,first_name')->latest('ended_at')->limit(3)->get()
                        ->map(fn (Sponsorship $s) => trim("{$s->reference_code} · {$s->orphan?->first_name}".($s->cancellation_reason ? " · {$s->cancellation_reason}" : '')))->all());
            }

            $started = Sponsorship::where('created_at', '>', $since)->whereIn('status', [SponsorshipStatus::Active, SponsorshipStatus::Pending])->count();
            if ($started) {
                $items[] = $this->item('sponsorships_new', 'كفالات جديدة', $started, 'good', '/sponsorships', ['status' => SponsorshipStatus::Active->value]);
            }
        }

        if ($user->can('sponsors.view')) {
            $n = Sponsor::where('created_at', '>', $since)->count();
            if ($n) {
                $items[] = $this->item('sponsors_new', 'كفلاء جدد سجّلوا', $n, 'good', '/sponsors', []);
            }
        }

        if ($user->can('content.manage')) {
            $n = ContactMessage::where('created_at', '>', $since)->count();
            if ($n) {
                $open = ContactMessage::where('created_at', '>', $since)->where('status', ContactStatus::New)->count();
                $items[] = $this->item('contacts_new', 'استفسارات زوار', $n, $open ? 'warn' : 'neutral', '/contacts', [], [],
                    $open ? "{$open} منها لم يُفتح بعد" : 'كلها فُتحت');
            }
        }

        // المشرف: ما أُرجع إليه من تقاريره ليعدّله
        if ($user->can('reports.create')) {
            $returned = Report::where('supervisor_id', $user->id)->where('returned_at', '>', $since)->where('status', ReportStatus::Draft);
            $n = (clone $returned)->count();
            if ($n) {
                $items[] = $this->item('reports_returned', 'تقارير أُرجعت إليك', $n, 'warn',
                    '/reports', ['status' => ReportStatus::Draft->value, 'returned' => 1],
                    (clone $returned)->limit(3)->pluck('review_note')->filter()->map(fn ($n) => mb_strimwidth($n, 0, 90, '…'))->values()->all());
            }
        }

        // من يراجع السجل: ما فعله غيره مما يستحق نظرة ثانية
        if ($user->can('activity.view')) {
            $n = Activity::query()
                ->where('created_at', '>', $since)
                ->where(fn ($q) => $q->whereNull('causer_id')->orWhere('causer_id', '!=', $user->id))
                ->where(function ($w): void {
                    $w->whereIn('subject_type', array_map(fn ($t) => 'App\\Models\\'.$t, ActivityFormatter::SENSITIVE_TYPES))
                        ->orWhereIn('event', ActivityFormatter::SENSITIVE_EVENTS);
                    foreach (ActivityFormatter::EXPORT_PATTERN as $p) {
                        $w->orWhere('description', 'like', $p);
                    }
                })
                ->count();
            if ($n) {
                $items[] = $this->item('activity_sensitive', 'عمليات حساسة قام بها غيرك', $n, 'warn', '/activity', ['sensitive' => 1, 'from' => $fromDay]);
            }
        }

        return [
            'since' => $since->toIso8601String(),
            'first_visit' => $first,
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, string>  $samples
     * @return array<string, mixed>
     */
    private function item(string $key, string $title, int $count, string $tone, string $path, array $query, array $samples = [], ?string $hint = null): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'count' => $count,
            'tone' => $tone,
            'hint' => $hint,
            'samples' => array_values(array_filter($samples)),
            'to' => ['path' => $path, 'query' => (object) $query],
        ];
    }
}

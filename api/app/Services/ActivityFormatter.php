<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactStatus;
use App\Enums\MessageStatus;
use App\Enums\OrphanStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PostStatus;
use App\Enums\ReportStatus;
use App\Enums\SponsorshipStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Country;
use App\Models\DisbursementCategory;
use App\Models\PostCategory;
use App\Models\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * سجل النشاط بلغة من يقرؤه.
 *
 * كان يُعرض كما خُزّن: «هدى النجارعدّلكفالة#139» بكلمات ملتصقة، وتواريخ
 * `2027-08-23T00:00:00.000000Z`، و`Report` و`draft` و`false` و«البرنامج: 1».
 * هنا يصير جملة عربية، والسجلّ باسمه أو مرجعه لا برقمه، والقيم بتسمياتها.
 */
class ActivityFormatter
{
    /** @var array<string, array{label: string, path: callable(Model|null, int|null): ?string}> */
    private array $subjects;

    /** @var array<string, string> */
    private const FIELDS = [
        'status' => 'الحالة',
        'is_urgent' => 'عاجل',
        'monthly_need' => 'الحاجة الشهرية',
        'monthly_amount' => 'المبلغ الشهري',
        'program_id' => 'البرنامج',
        'verified_at' => 'التوثيق',
        'published_at' => 'النشر',
        'next_due_at' => 'القسط القادم',
        'share_percent' => 'نسبة الحصة',
        'cancellation_reason' => 'سبب الإلغاء',
        'amount' => 'المبلغ',
        'amount_base' => 'المبلغ بالدولار',
        'currency' => 'العملة',
        'category_id' => 'البند',
        'orphan_id' => 'الطفل',
        'spent_on' => 'تاريخ الصرف',
        'description' => 'البيان',
        'role' => 'الدور',
        'assigned_country_id' => 'البلد المُسنَد',
        'name' => 'الاسم',
        'phone' => 'الهاتف',
        'title' => 'العنوان',
        'method' => 'الوسيلة',
        'reference' => 'المرجع',
        'question' => 'السؤال',
        'answer' => 'الجواب',
        'is_published' => 'منشور',
        'is_active' => 'ظاهر',
        'sort_order' => 'الترتيب',
        'quote' => 'النص',
        'url' => 'الرابط',
    ];

    public function __construct()
    {
        $this->subjects = [
            'Orphan' => ['label' => 'ملف يتيم', 'path' => fn ($m, $id) => "/orphans/{$id}"],
            'Sponsorship' => ['label' => 'كفالة', 'path' => fn ($m, $id) => '/sponsorships?q='.urlencode((string) ($m?->reference_code ?? ''))],
            'Payment' => ['label' => 'دفعة', 'path' => fn ($m, $id) => '/payments?q='.urlencode((string) ($m?->reference_code ?? ''))],
            'Disbursement' => ['label' => 'صرف', 'path' => fn () => '/disbursements'],
            'Report' => ['label' => 'تقرير', 'path' => fn ($m, $id) => "/reports?review={$id}"],
            'Message' => ['label' => 'رسالة', 'path' => fn () => '/messages'],
            'User' => ['label' => 'حساب', 'path' => fn () => '/users'],
            'Post' => ['label' => 'مقال', 'path' => fn () => '/content'],
            'Faq' => ['label' => 'سؤال شائع', 'path' => fn () => '/content'],
            'Testimonial' => ['label' => 'شهادة كافل', 'path' => fn () => '/content'],
            'Partner' => ['label' => 'شريك', 'path' => fn () => '/content'],
            'ContactMessage' => ['label' => 'رسالة تواصل', 'path' => fn () => '/contacts'],
        ];
    }

    /** @return array<string, string> */
    public function subjectTypes(): array
    {
        return array_map(fn ($s) => $s['label'], $this->subjects);
    }

    /** @return array<string, mixed> */
    public function format(Activity $activity): array
    {
        $type = class_basename((string) $activity->subject_type);
        $subject = $activity->relationLoaded('subject') ? $activity->subject : null;
        $props = $activity->properties?->toArray() ?? [];
        $meta = $this->subjects[$type] ?? null;

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'actor' => $activity->causer?->name ?? 'النظام',
            'action' => $this->action($activity, $meta),
            'subject_type' => $type ?: null,
            'subject_label' => $meta['label'] ?? null,
            'subject_name' => $this->subjectName($type, $subject, $activity->subject_id),
            'subject_path' => $meta && $activity->subject_id ? ($meta['path'])($subject, $activity->subject_id) : ($type === '' && $activity->event === 'settings_updated' ? '/settings' : null),
            'changes' => $this->changes($type, $props['old'] ?? [], $props['attributes'] ?? []),
            'note' => $props['reason'] ?? $props['note'] ?? null,
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    /** الفعل: الوصف العربي المكتوب عند التسجيل، وإلا فعلٌ من الحدث ونوع السجل */
    private function action(Activity $activity, ?array $meta): string
    {
        $description = (string) $activity->description;

        // السجلات الآلية (LogsActivity) تكتب الحدث بالإنجليزية وصفاً
        if ($description !== '' && ! in_array($description, ['created', 'updated', 'deleted', 'restored'], true)) {
            return $description;
        }

        $verb = ['created' => 'أنشأ', 'updated' => 'عدّل', 'deleted' => 'حذف', 'restored' => 'استعاد'][$activity->event] ?? 'غيّر';

        return trim($verb.' '.($meta['label'] ?? 'سجلاً'));
    }

    private function subjectName(string $type, ?Model $subject, ?int $id): ?string
    {
        if ($subject === null) {
            return $id ? "رقم {$id}" : null;
        }

        return match ($type) {
            'Orphan' => trim("{$subject->first_name} · {$subject->reference_code}"),
            'Sponsorship', 'Payment' => $subject->reference_code,
            'Report' => $subject->title,
            'User' => $subject->name,
            'Post' => $subject->title,
            'Faq' => mb_strimwidth((string) $subject->question, 0, 60, '…'),
            'Testimonial' => $subject->author_name ?? "رقم {$id}",
            'Partner' => $subject->name ?? "رقم {$id}",
            'ContactMessage' => $subject->name,
            'Disbursement' => "{$subject->amount} {$subject->currency}",
            default => "رقم {$id}",
        };
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, array{field: string, label: string, before: ?string, after: ?string}>
     */
    private function changes(string $type, array $before, array $after): array
    {
        $out = [];

        foreach (array_unique([...array_keys($after), ...array_keys($before)]) as $field) {
            if (in_array($field, ['updated_at', 'created_at', 'id', 'password', 'remember_token'], true)) {
                continue;
            }

            $out[] = [
                'field' => (string) $field,
                'label' => self::FIELDS[$field] ?? $this->settingLabel((string) $field),
                'before' => array_key_exists($field, $before) ? $this->value($type, (string) $field, $before[$field]) : null,
                'after' => array_key_exists($field, $after) ? $this->value($type, (string) $field, $after[$field]) : null,
            ];
        }

        return $out;
    }

    private function value(string $type, string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'فارغ';
        }

        if (is_bool($value) || in_array($field, ['is_urgent', 'is_published', 'is_active', 'policy.moderate_messages'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'نعم' : 'لا';
        }

        $enum = match (true) {
            $field === 'status' => match ($type) {
                'Orphan' => OrphanStatus::class,
                'Sponsorship' => SponsorshipStatus::class,
                'Payment' => PaymentStatus::class,
                'Message' => MessageStatus::class,
                'User' => UserStatus::class,
                'Post' => PostStatus::class,
                'Report' => ReportStatus::class,
                'ContactMessage' => ContactStatus::class,
                default => null,
            },
            $field === 'role' => UserRole::class,
            $field === 'method' => PaymentMethod::class,
            default => null,
        };

        // «role» في شهادة الكافل نصّ حرّ (صفته)، لا دور مستخدم
        if ($type === 'Testimonial' && $field === 'role') {
            $enum = null;
        }

        if ($enum !== null && is_string($value) && ($case = $enum::tryFrom($value)) !== null) {
            return $case->label();
        }

        $named = match ($field) {
            'program_id' => Program::find($value)?->name_ar,
            'category_id' => ($type === 'Post' ? PostCategory::find($value) : DisbursementCategory::find($value))?->name_ar,
            'assigned_country_id' => Country::find($value)?->name_ar,
            'orphan_id' => ($o = \App\Models\Orphan::withTrashed()->find($value)) ? "{$o->first_name} · {$o->reference_code}" : null,
            default => null,
        };
        if ($named !== null) {
            return $named;
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            /*
             * التاريخ المجرّد (موعد قسط، تاريخ صرف) يُخزَّن منتصف ليل UTC، فتحويله
             * إلى توقيت غزة كان يُظهره «02:00». يُعرض يوماً بلا ساعة ولا تحويل،
             * أما اللحظة (توثيق، نشر) فتُحوَّل وتُعرض ساعتها.
             */
            $dateOnly = in_array($field, ['next_due_at', 'spent_on', 'date_of_birth', 'started_at', 'visit_date'], true)
                || ! preg_match('/[T ]\d{2}:\d{2}/', $value)
                || str_contains($value, 'T00:00:00.000000Z');

            return $dateOnly
                ? Carbon::parse(substr($value, 0, 10))->locale('ar')->translatedFormat('j F Y')
                : Carbon::parse($value)->timezone(config('kafala.display_timezone'))->locale('ar')->translatedFormat('j F Y، H:i');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return mb_strimwidth((string) $value, 0, 140, '…');
    }

    private function settingLabel(string $field): string
    {
        return [
            'site.name' => 'اسم المنصة',
            'site.tagline' => 'الوصف المختصر',
            'site.email' => 'بريد التواصل',
            'site.phone' => 'الهاتف',
            'site.address' => 'العنوان',
            'site.start_year' => 'سنة التأسيس',
            'finance.overhead_target_percent' => 'نسبة التشغيل المستهدفة',
            'policy.moderate_messages' => 'مراجعة رسائل الكفلاء',
            'policy.report_cadence_months' => 'دورية التقارير بالأشهر',
        ][$field] ?? $field;
    }
}

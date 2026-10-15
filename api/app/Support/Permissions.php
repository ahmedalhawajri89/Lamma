<?php

declare(strict_types=1);

namespace App\Support;

/**
 * الصلاحيات بأوصافها العربية ومجموعاتها.
 *
 * مصدرٌ واحد يقرؤه RoleSeeder حين يبني الأدوار، وتقرؤه شاشة المستخدمين
 * حين تشرح للمدير ما يقدر عليه كل موظف، فلا يختلف الشرح عمّا يُمنح فعلاً.
 */
final class Permissions
{
    /** @var array<string, array{0: string, 1: array<string, string>}> مجموعة => [اسمها، [صلاحية => وصفها]] */
    public const GROUPS = [
        'children' => ['الأطفال والأسر', [
            'orphans.view' => 'عرض ملفات الأيتام',
            'orphans.create' => 'إنشاء ملف يتيم',
            'orphans.update' => 'تعديل ملف يتيم',
            'orphans.verify' => 'توثيق ملف يتيم',
            'orphans.publish' => 'نشر ملف يتيم على الموقع',
            'orphans.delete' => 'أرشفة ملف يتيم',
            'families.manage' => 'إدارة الأسر والأوصياء',
            'documents.manage' => 'إدارة مستندات التحقق',
        ]],
        'field' => ['الميدان والتقارير', [
            'reports.view' => 'عرض التقارير الربعية',
            'reports.create' => 'كتابة تقرير ربعي',
            'reports.approve' => 'اعتماد تقرير',
            'reports.publish' => 'نشر تقرير للكافل',
            'visits.view' => 'عرض الزيارات الميدانية',
            'visits.record' => 'تسجيل زيارة ميدانية',
        ]],
        'money' => ['الكفالات والمال', [
            'sponsors.view' => 'عرض الكفلاء',
            'sponsors.manage' => 'إدارة حسابات الكفلاء',
            'sponsorships.view' => 'عرض الكفالات',
            'sponsorships.manage' => 'إنشاء وتعديل وإلغاء الكفالات',
            'payments.view' => 'عرض المدفوعات',
            'payments.record' => 'تسجيل دفعة يدوية',
            'payments.refund' => 'استرداد دفعة',
            'disbursements.view' => 'عرض الصرف',
            'disbursements.record' => 'تسجيل صرف',
            'disbursements.approve' => 'اعتماد صرف',
        ]],
        'outreach' => ['التواصل والموقع', [
            'messages.review' => 'مراجعة رسائل الكفلاء والأطفال',
            'content.manage' => 'إدارة المدونة والأسئلة والشركاء',
        ]],
        'system' => ['النظام', [
            'users.manage' => 'إدارة المستخدمين والأدوار',
            'settings.manage' => 'تعديل إعدادات المنصة',
            'analytics.view' => 'عرض لوحة التحليلات',
            'activity.view' => 'عرض سجل النشاط',
        ]],
    ];

    /** @return array<string, string> صلاحية => وصفها */
    public static function all(): array
    {
        return array_merge(...array_map(fn ($g) => $g[1], array_values(self::GROUPS)));
    }

    /**
     * ما يُمنح وما لا يُمنح، مجموعةً مجموعة.
     *
     * الممنوع يُعرض أيضاً: «المحاسب لا يعدّل ملفات الأطفال» معلومةٌ يحتاجها
     * المدير حين يختار الدور، بقدر ما يحتاج ما يقدر عليه.
     *
     * @param  array<int, string>  $granted
     * @return array<int, array{key: string, label: string, items: array<int, array{name: string, label: string, granted: bool}>}>
     */
    public static function describe(array $granted): array
    {
        $granted = array_flip($granted);

        return collect(self::GROUPS)->map(fn ($group, $key) => [
            'key' => $key,
            'label' => $group[0],
            'items' => collect($group[1])->map(fn ($label, $name) => [
                'name' => $name,
                'label' => $label,
                'granted' => isset($granted[$name]),
            ])->values()->all(),
        ])->values()->all();
    }
}

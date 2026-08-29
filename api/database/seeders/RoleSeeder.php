<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * الأدوار والصلاحيات.
 *
 * الصلاحية هنا فعلٌ لا شاشة: `orphans.verify` لا `orphans_page`.
 * ربطُها بالشاشات يجعل كل تعديل في الواجهة تعديلاً في الصلاحيات.
 */
class RoleSeeder extends Seeder
{
    /** @var array<string, string> صلاحية => وصفها العربي */
    private const PERMISSIONS = [
        'orphans.view' => 'عرض ملفات الأيتام',
        'orphans.create' => 'إنشاء ملف يتيم',
        'orphans.update' => 'تعديل ملف يتيم',
        'orphans.verify' => 'توثيق ملف يتيم',
        'orphans.publish' => 'نشر ملف يتيم على الموقع',
        'orphans.delete' => 'أرشفة ملف يتيم',
        'families.manage' => 'إدارة الأسر والأوصياء',
        'documents.manage' => 'إدارة مستندات التحقق',
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
        'reports.view' => 'عرض التقارير الربعية',
        'reports.create' => 'كتابة تقرير ربعي',
        'reports.approve' => 'اعتماد تقرير',
        'reports.publish' => 'نشر تقرير للكافل',
        'visits.view' => 'عرض الزيارات الميدانية',
        'visits.record' => 'تسجيل زيارة ميدانية',
        'messages.review' => 'مراجعة رسائل الكفلاء والأطفال',
        'content.manage' => 'إدارة المدونة والأسئلة والشركاء',
        'users.manage' => 'إدارة المستخدمين والأدوار',
        'settings.manage' => 'تعديل إعدادات المنصة',
        'analytics.view' => 'عرض لوحة التحليلات',
        'activity.view' => 'عرض سجل النشاط',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // المدير: كل شيء
        Role::findOrCreate(UserRole::Admin->value, 'web')
            ->syncPermissions(array_keys(self::PERMISSIONS));

        // المحاسب: المال وما يفسّره، بلا صلاحية تعديل على ملفات الأطفال
        Role::findOrCreate(UserRole::Accountant->value, 'web')->syncPermissions([
            'orphans.view', 'sponsors.view', 'sponsorships.view',
            'payments.view', 'payments.record', 'payments.refund',
            'disbursements.view', 'disbursements.record', 'disbursements.approve',
            'reports.view', 'analytics.view',
        ]);

        /**
         * المشرف الميداني: الملفات والتقارير والزيارات — ولا شيء مالي.
         * وتقيّده OrphanPolicy فوق ذلك ببلده المُسنَد، فلا يرى أيتام بلد آخر.
         */
        Role::findOrCreate(UserRole::FieldSupervisor->value, 'web')->syncPermissions([
            'orphans.view', 'orphans.create', 'orphans.update',
            'families.manage', 'documents.manage',
            'reports.view', 'reports.create',
            'visits.view', 'visits.record',
            'sponsorships.view',
        ]);

        // الكافل: لا صلاحية إدارية إطلاقاً — بوابته محكومة بالسياسات وحدها
        Role::findOrCreate(UserRole::Sponsor->value, 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

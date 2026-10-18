<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * الأدوار والصلاحيات.
 *
 * الصلاحية هنا فعلٌ لا شاشة: `orphans.verify` لا `orphans_page`.
 * ربطُها بالشاشات يجعل كل تعديل في الواجهة تعديلاً في الصلاحيات.
 * أوصافها ومجموعاتها في App\Support\Permissions.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(Permissions::all()) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // المدير: كل شيء
        Role::findOrCreate(UserRole::Admin->value, 'web')
            ->syncPermissions(array_keys(Permissions::all()));

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

<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * الترتيب مقصود: الأدوار ثم البيانات المرجعية ثم المحتوى ثم البيانات التجريبية.
     * البذور التجريبية وحدها هي ما يُحذف عند التحضير للإنتاج.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ReferenceSeeder::class,
            ContentSeeder::class,
        ]);

        // بيانات العرض للتطوير والاختبارات (ومنها بيئة E2E المنفصلة)، لا للإنتاج
        if (app()->environment(['local', 'testing', 'e2e'])) {
            $this->call(DemoSeeder::class);
        }
    }
}

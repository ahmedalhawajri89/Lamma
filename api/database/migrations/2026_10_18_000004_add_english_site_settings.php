<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * اسم المنصة وشعارها وعنوانها بالإنجليزية، للموقع تحت /en. لا يُكتب فوق
 * قيمةٍ موجودة: من كتبها من الإعدادات تبقى كما كتبها.
 */
return new class extends Migration
{
    private const ROWS = [
        'name_en' => 'Lamma',
        'tagline_en' => 'Every child in the circle of their family',
        'address_en' => 'London, United Kingdom',
    ];

    public function up(): void
    {
        foreach (self::ROWS as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'group' => 'site', 'key' => $key, 'value' => $value, 'type' => 'string',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Cache::forget('settings');
        Cache::forget('public.bootstrap');
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'site')->whereIn('key', array_keys(self::ROWS))->delete();
        Cache::forget('settings');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * الاسم الجديد: «لمّة».
 *
 * تُبدَّل القيم القديمة الافتراضية وحدها. ما غيّره المدير من الإعدادات
 * (اسمٌ أو بريدٌ آخر) يبقى كما هو، فالترحيل لا يكتب فوق قرارٍ بشري.
 */
return new class extends Migration
{
    private const CHANGES = [
        'name' => ['كفالة', 'لمّة'],
        'tagline' => ['منصة كفالة الأيتام', 'كل طفلٍ في لمّة أهله'],
        'email' => ['info@kafala.org', 'hello@lamma.org'],
        'phone' => ['+970 599 000 000', '+44 20 4000 0000'],
        'address' => ['غزة، فلسطين', 'لندن، المملكة المتحدة'],
    ];

    public function up(): void
    {
        foreach (self::CHANGES as $key => [$old, $new]) {
            DB::table('settings')->where('group', 'site')->where('key', $key)->where('value', $old)->update(['value' => $new]);
        }
        Cache::forget('settings');
        Cache::forget('public.bootstrap');
    }

    public function down(): void
    {
        foreach (self::CHANGES as $key => [$old, $new]) {
            DB::table('settings')->where('group', 'site')->where('key', $key)->where('value', $new)->update(['value' => $old]);
        }
        Cache::forget('settings');
        Cache::forget('public.bootstrap');
    }
};

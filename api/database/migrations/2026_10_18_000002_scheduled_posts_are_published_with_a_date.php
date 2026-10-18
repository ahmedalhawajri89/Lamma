<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «مجدول» لم يعد حالةً تُحفظ: المقال المجدول منشورٌ بموعدٍ قادم، يظهر على
 * الموقع يوم يحلّ. ما حُفظ «مجدولاً» من قبل لم يكن ليظهر أبداً (لا شيء كان
 * يحوّله)، فيصير منشوراً بموعده، ومن لا موعد له يعود مسوّدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')->where('status', 'scheduled')->whereNotNull('published_at')->update(['status' => 'published']);
        DB::table('posts')->where('status', 'scheduled')->update(['status' => 'draft']);
    }

    public function down(): void
    {
        DB::table('posts')->where('status', 'published')->where('published_at', '>', now())->update(['status' => 'scheduled']);
    }
};

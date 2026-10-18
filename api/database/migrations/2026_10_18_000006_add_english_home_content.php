<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بقية محتوى الرئيسية بالإنجليزية: التصنيفات أعمدة name_en بجانب name_ar،
 * والسجلات ذات الحقول النصية (الشهادات، والأسئلة، والمقالات، وشعارات الشركاء)
 * عمود translations كما في الأطفال (HasTranslations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_categories', fn (Blueprint $t) => $t->string('name_en', 120)->nullable()->after('name_ar'));
        Schema::table('post_categories', fn (Blueprint $t) => $t->string('name_en', 120)->nullable()->after('name_ar'));

        foreach (['testimonials', 'faqs', 'posts', 'partners'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->json('translations')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['testimonials', 'faqs', 'posts', 'partners'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('translations'));
        }
        Schema::table('post_categories', fn (Blueprint $t) => $t->dropColumn('name_en'));
        Schema::table('disbursement_categories', fn (Blueprint $t) => $t->dropColumn('name_en'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الجغرافيا — جداول مرجعية.
 *
 * تحلّ محلّ النص الحر 'فلسطين' المتكرر في data.js، فيستحيل أن تصبح
 * 'فلسطين' و' فلسطين' دولتين مختلفتين في التقارير.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3);
            $table->string('name_ar', 80);
            $table->string('name_en', 80);
            $table->string('flag_emoji', 16)->nullable();
            $table->char('currency_code', 3)->default('USD');

            // هل نعمل في هذا البلد فعلاً؟ الجدول يضمّ دولاً للعناوين أيضاً
            $table->boolean('is_operational')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_operational', 'sort_order']);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar', 80);
            $table->string('name_en', 80)->nullable();
            $table->timestamps();

            $table->unique(['country_id', 'name_ar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * برامج الكفالة — المصدر الوحيد للأسعار.
 *
 * السعر هنا مرجعٌ لحظة إنشاء الكفالة فقط؛ الكفالة تنسخه إلى
 * `sponsorships.monthly_amount`. فرفع السعر لاحقاً لا يغيّر التزام كافل قائم
 * (snapshot pricing) — معيار إلزامي في أي نظام اشتراكات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();                   // comprehensive, education…
            $table->string('name_ar', 80);
            $table->string('name_en', 80)->nullable();
            $table->string('icon', 60)->nullable();                 // fa-hands-holding-child
            $table->string('accent', 120)->nullable();              // متغيّر أو تدرّج من tokens.css
            $table->decimal('monthly_price', 10, 2);
            $table->char('currency', 3)->default('USD');
            $table->text('summary_ar')->nullable();
            $table->text('description_ar')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // مُطبَّع بدل مصفوفة JSON — ليعدّله المدير من اللوحة، ويُبحث ويُترجم
        Schema::create('program_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('label_ar', 160);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->index(['program_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_features');
        Schema::dropIfExists('programs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول التجميع (rollup) الذي يشغّل مخططات لوحة التحكم.
 *
 * لماذا لا نحسب المخططات من `payments` مباشرة؟
 * لأن مخطّط «نمو الكفالات آخر ١٢ شهراً» عندئذ يمسح ملايين الصفوف عند كل
 * فتح للوحة. والقراءة من جدول فيه 365 صفاً في العام تجعل الاستجابة دون 10ms.
 *
 * هذا هو الفرق بين لوحة تعمل ولوحة تتجمّد بعد سنة من الاستخدام.
 * تملؤه مهمة مجدولة كل ليلة: php artisan metrics:rollup
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->date('date')->primary();

            $table->unsignedInteger('sponsored_count')->default(0);       // أيتام مكفولون في ذلك اليوم
            $table->unsignedInteger('active_sponsors')->default(0);
            $table->unsignedInteger('new_sponsorships')->default(0);
            $table->unsignedInteger('cancelled_sponsorships')->default(0);

            $table->decimal('revenue_base', 14, 2)->default(0);           // مقبوضات اليوم بالعملة الأساس
            $table->decimal('disbursed_base', 14, 2)->default(0);         // مصروفات اليوم المعتمدة

            $table->unsignedInteger('pending_orphans')->default(0);       // بانتظار التحقق
            $table->unsignedInteger('urgent_unfunded')->default(0);       // عاجل وتمويله < 100%
            $table->unsignedInteger('pending_reports')->default(0);
            $table->unsignedInteger('pending_messages')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
    }
};

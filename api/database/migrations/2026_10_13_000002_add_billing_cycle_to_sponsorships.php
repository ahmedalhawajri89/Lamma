<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دورة الدفع ووسيلته على الكفالة نفسها.
 *
 * صفحة الكفالة تعرض «شهرية / كل 3 أشهر / سنوية» وتضرب المبلغ، ثم لا تحفظ
 * الاختيار: يُخصم شهرياً أيّاً كان ما اختاره الكافل، ومن دفع سنةً بتحويل
 * يصير متأخراً بعد شهر. و«الوسيلة» تُعرّف أمرَ الفوترة بمن يدفع ببطاقة
 * فيُخصم منه آلياً، ومن يحوّل فيتابعه المحاسب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->unsignedTinyInteger('interval_months')->default(1)->after('billing_day');
            $table->string('payment_method', 20)->nullable()->after('interval_months');
        });
    }

    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->dropColumn(['interval_months', 'payment_method']);
        });
    }
};

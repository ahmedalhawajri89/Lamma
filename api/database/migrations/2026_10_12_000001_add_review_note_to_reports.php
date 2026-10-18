<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ملاحظة المراجع حين يُرجع التقرير لكاتبه.
 *
 * بدونها كان أمام المراجع خياران: أن يعتمد تقريراً ناقصاً، أو أن يتركه
 * معلّقاً في الطابور ويشرح للمشرف خارج النظام. والملاحظة تبقى بعد إعادة
 * الإرسال، فيرى المراجع التالي ما طُلب وهل عولج.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->text('review_note')->nullable()->after('status');
            $table->timestamp('returned_at')->nullable()->after('review_note');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['review_note', 'returned_at']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * استعادة القسط المرفوض: متى بدأ التعثّر، وكم محاولة فشلت، ومتى المحاولة
 * التالية. كان billing:run يعيد الخصم كل يوم بلا نهاية ولا يعلم الكافل،
 * فينقطع مال الطفل بصمت.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->date('payment_issue_since')->nullable()->after('next_due_at');
            $table->unsignedTinyInteger('payment_failures')->default(0)->after('payment_issue_since');
            $table->date('next_retry_at')->nullable()->after('payment_failures');
        });
    }

    public function down(): void
    {
        Schema::table('sponsorships', fn (Blueprint $t) => $t->dropColumn(['payment_issue_since', 'payment_failures', 'next_retry_at']));
    }
};

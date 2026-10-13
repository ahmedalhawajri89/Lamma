<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * من فعل ماذا بالمال.
 *
 * الاسترداد كان يغيّر الحالة ويكتب سببه فوق `failure_reason`، بلا متى ولا من.
 * والدفعة اليدوية لا تعرف من سجّلها، والصرف لا يعرف من عدّل مبلغه آخر مرة،
 * فيستطيع من عدّل مبلغ غيره أن يعتمده بنفسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('idempotency_key')->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable()->after('recorded_by');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
            $table->string('refund_reason', 255)->nullable()->after('refunded_by');
            $table->index('gateway_reference');
        });

        Schema::table('disbursements', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->decimal('fx_rate', 12, 6)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway_reference']);
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['refunded_at', 'refund_reason']);
        });

        Schema::table('disbursements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('fx_rate');
        });
    }
};

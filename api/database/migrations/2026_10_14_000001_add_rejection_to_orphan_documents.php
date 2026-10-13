<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رفض المستند بسبب.
 *
 * كان للمستند قراران: يُتحقَّق منه أو يُحذف. فالمستند غير المقروء أو
 * الخاص بطفل آخر إما يبقى في الطابور إلى الأبد، أو يُحذف بلا أثر فلا يعرف
 * رافعه لماذا اختفى. الرفض يُخرجه من الطابور ويبقيه في الملف بسببه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orphan_documents', function (Blueprint $table): void {
            $table->timestamp('rejected_at')->nullable()->after('verified_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 255)->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('orphan_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 3: «ترى أين ذهب كل دولار».
 *
 * · نسبة التشغيل لقطةٌ على كل دفعة لحظة إنشائها (payments.platform_fee_percent):
 *   تغيير النسبة لاحقاً لا يعيد كتابة ما حُسب على دفعاتٍ مضت.
 * · التحويل الشهري للجمعية (association_transfers) وتفصيله لكل طفل
 *   (association_transfer_lines): ما وصل من كفلائه، ناقص التشغيل، يُحوَّل.
 *   مسوّدة ← أُرسل ← تأكّد الوصول. وما أُرسل لا يُعاد حسابه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('platform_fee_percent', 5, 2)->nullable()->after('amount_base');
        });

        // الدفعات السابقة بالنسبة المعلنة يومها (7٪، كما في الإعدادات وصفحة الشفافية)
        DB::table('payments')->whereNull('platform_fee_percent')->update(['platform_fee_percent' => 7]);

        Schema::create('association_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 24)->unique();              // TRF-2026-10-0007
            $table->foreignId('association_id')->constrained()->restrictOnDelete();
            $table->date('period');                                       // أول الشهر الذي جُمع فيه المال

            $table->char('currency', 3)->default('USD');
            $table->decimal('received_base', 12, 2)->default(0);          // ما دفعه الكفلاء
            $table->decimal('fee_base', 12, 2)->default(0);               // نسبة التشغيل منه
            $table->decimal('amount_base', 12, 2)->default(0);            // المحوَّل للجمعية

            $table->enum('status', ['draft', 'sent', 'confirmed'])->default('draft');
            $table->string('bank_reference', 80)->nullable();             // مرجع الحوالة
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamps();

            // تحويلٌ واحد لكل جمعية في كل شهر
            $table->unique(['association_id', 'period']);
            $table->index(['status', 'period']);
        });

        Schema::create('association_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('association_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('orphan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('payments_count')->default(0);
            $table->decimal('received_base', 12, 2)->default(0);
            $table->decimal('fee_base', 12, 2)->default(0);
            $table->decimal('amount_base', 12, 2)->default(0);
            $table->timestamps();

            // أسماء صريحة: الاسم المولَّد يتجاوز حدّ MySQL (64 حرفاً)
            $table->unique(['association_transfer_id', 'orphan_id'], 'transfer_lines_transfer_orphan_unique');
            $table->index('orphan_id', 'transfer_lines_orphan_index');
        });

        // الإعداد نفسه، فيُعدَّل من اللوحة (لا يُكتب فوق قيمة موجودة)
        DB::table('settings')->insertOrIgnore([
            'group' => 'finance', 'key' => 'platform_fee_percent', 'value' => '7', 'type' => 'number',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('association_transfer_lines');
        Schema::dropIfExists('association_transfers');
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('platform_fee_percent'));
        DB::table('settings')->where('group', 'finance')->where('key', 'platform_fee_percent')->delete();
    }
};

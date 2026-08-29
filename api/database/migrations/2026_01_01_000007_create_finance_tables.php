<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المال — الداخل والخارج.
 *
 * كل مبلغ DECIMAL لا FLOAT: الأخير يفقد السنتات في الجمع، وهو غير مقبول
 * في نظام تبرعات يُدقَّق حسابياً.
 *
 * ولا بيانات بطاقة حساسة إطلاقاً: العلامة التجارية وآخر أربعة أرقام فقط.
 * لا PAN، لا CVV، لا تاريخ انتهاء — البوابة تحتفظ بها لا نحن.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 24)->unique();

            $table->foreignId('sponsorship_id')->constrained()->restrictOnDelete();

            // مُكرَّر عمداً: كشف حساب الكافل أكثر الاستعلامات تكراراً، ولا يحتاج JOIN
            $table->foreignId('sponsor_id')->constrained()->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');

            // كافل في لندن يدفع بالجنيه وتقرير المؤسسة بالدولار
            $table->decimal('fx_rate', 12, 6)->default(1);
            $table->decimal('amount_base', 12, 2);

            $table->enum('method', ['card', 'apple_pay', 'bank_transfer', 'cash']);
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_reference', 120)->nullable();

            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed', 'refunded'])
                ->default('pending');

            $table->string('card_brand', 20)->nullable();
            $table->char('card_last4', 4)->nullable();

            // متى حدثت الدفعة فعلاً — لا متى أُدخل الصف
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->string('receipt_number', 24)->nullable()->unique();

            /**
             * ليس رفاهية: الكافل يضغط «ادفع» مرتين، أو تعيد البوابة إرسال
             * الـ webhook — وبلا هذا المفتاح يُخصم المبلغ مرتين. وضع UNIQUE
             * عليه يجعل الخصم المزدوج مستحيلاً على مستوى المحرّك لا الكود.
             */
            $table->string('idempotency_key', 64)->unique();

            $table->timestamps();

            $table->index(['status', 'paid_at']);
            $table->index(['sponsor_id', 'paid_at']);
            $table->index(['sponsorship_id', 'paid_at']);
        });

        // بنود الصرف — جدول لا ENUM، لأن المحاسب يضيف بنداً بلا مبرمج
        Schema::create('disbursement_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name_ar', 80);
            $table->string('color', 40)->nullable();                 // متغيّر من tokens.css
            $table->boolean('is_overhead')->default(false);          // يميّز «المصاريف الإدارية» في حساب نسبة الوصول
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /**
         * المال الخارج — هذا الجدول يحوّل حلقة الشفافية من نسب مكتوبة
         * يدوياً في data.js إلى استعلام تجميع على صرفٍ معتمَد.
         */
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();

            // NULL = مصروف إداري عام لا يخصّ طفلاً بعينه
            $table->foreignId('orphan_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained('disbursement_categories')->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->decimal('amount_base', 12, 2);

            $table->date('spent_on');
            $table->string('description', 255)->nullable();
            $table->string('receipt_path')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // لا يدخل في تقارير الشفافية إلا المعتمَد
            $table->index(['approved_at', 'spent_on']);
            $table->index(['category_id', 'spent_on']);
            $table->index(['orphan_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
        Schema::dropIfExists('disbursement_categories');
        Schema::dropIfExists('payments');
    }
};

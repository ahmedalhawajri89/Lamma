<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الكفالات — العلاقة المحورية في النظام.
 *
 * العلاقة بين كافل ويتيم ليست belongsToMany بسيطة: لها مبلغ، وحالة،
 * ودورة فوترة، وتاريخ بداية ونهاية وسبب إلغاء. لذا هي جدول محوري
 * مُثرى (enriched pivot) لا مجرد orphan_sponsor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 20)->unique();          // KFS-2026-00317

            $table->foreignId('sponsor_id')->constrained()->restrictOnDelete();
            $table->foreignId('orphan_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();

            // منسوخ لحظة الإنشاء من programs.monthly_price — لا يتبع تغيّر السعر لاحقاً
            $table->decimal('monthly_amount', 10, 2);
            $table->char('currency', 3)->default('USD');

            // كافلان قد يتقاسمان حالة واحدة: 60% + 40%
            $table->unsignedTinyInteger('share_percent')->default(100);

            $table->boolean('is_zakat')->default(false);

            $table->enum('status', ['pending', 'active', 'paused', 'cancelled', 'completed'])
                ->default('pending');

            // 1–28 فقط: يوم 31 لا يوجد في فبراير، ويوم 29 يظهر مرة كل أربع سنوات
            $table->unsignedTinyInteger('billing_day')->default(1);

            $table->date('started_at')->nullable();
            $table->date('next_due_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'next_due_at'], 'idx_sponsorships_due');   // أمر الفوترة اليومي
            $table->index(['sponsor_id', 'status']);
            $table->index(['orphan_id', 'status']);
        });

        /**
         * القيد الذي يمنع الازدواج — الجزء غير البديهي في المخطّط كله.
         *
         * لا يجوز لكافل أن يكفل الطفل نفسه مرتين في الوقت نفسه، لكنه يجوز
         * أن يعود لكفالته بعد إلغاء. و UNIQUE(sponsor_id, orphan_id) العادي
         * يمنع العودة — وهذا خطأ.
         *
         * الحل: عمود مُولَّد يساوي 1 للحالات الحيّة و NULL لغيرها. و NULL
         * لا يشارك في قيود UNIQUE في MySQL، فالصفوف الملغاة تُستثنى تلقائياً.
         * قيدٌ جزئي حقيقي على محرّك لا يدعم القيود الجزئية.
         */
        // TiDB (قاعدة النسخة المنشورة) لا يضيف عموداً مولَّداً STORED بـ ALTER،
        // ويقبل VIRTUAL ويبني عليه القيد الفريد نفسه؛ والنتيجة واحدة.
        $tidb = str_contains((string) DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION), 'TiDB');
        $storage = $tidb ? 'VIRTUAL' : 'STORED';

        DB::statement("
            ALTER TABLE sponsorships
            ADD COLUMN active_key TINYINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE WHEN status IN ('pending','active','paused') THEN 1 ELSE NULL END
                ) {$storage}
        ");

        DB::statement('
            ALTER TABLE sponsorships
            ADD UNIQUE KEY uniq_active_sponsorship (sponsor_id, orphan_id, active_key)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorships');
    }
};

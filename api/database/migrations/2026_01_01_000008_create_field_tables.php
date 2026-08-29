<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المتابعة الميدانية — التقارير والزيارات والرسائل.
 *
 * هنا تتحوّل وعود الموقع إلى قيود في المحرّك:
 *  · «تقرير كل ٣ أشهر»      → UNIQUE(orphan_id, year, quarter)
 *  · «رسائل تمرّ بمشرف»      → status افتراضيّها pending_review
 *  · «١١٤٢٠ زيارة موثّقة»   → COUNT على field_visits
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orphan_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_quarter');           // 1..4

            $table->string('title', 180);
            $table->text('summary')->nullable();
            $table->text('academic_note')->nullable();
            $table->text('health_note')->nullable();
            $table->text('psychosocial_note')->nullable();

            // لقطة من disbursements وقت الاعتماد — يبقى التقرير صادقاً ولو عُدّل الصرف لاحقاً
            $table->json('spending_breakdown')->nullable();

            $table->date('visit_date')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', ['draft', 'submitted', 'approved', 'published'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // يمنع تقريرين لنفس الربع — الوعد يصير قيداً
            $table->unique(['orphan_id', 'period_year', 'period_quarter'], 'uniq_report_period');
            $table->index(['status', 'published_at']);
        });

        Schema::create('report_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['image', 'video', 'document']);
            $table->string('path');
            $table->string('caption', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['report_id', 'sort_order']);
        });

        /**
         * ما يشغّل شارة «تقريران جديدان» في لوحة الكافل —
         * عبر LEFT JOIN ... WHERE viewed_at IS NULL، لا برقم مكتوب في HTML.
         */
        Schema::create('report_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['report_id', 'sponsor_id']);
            $table->index('sponsor_id');
        });

        Schema::create('field_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orphan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('visited_at');
            $table->text('findings')->nullable();
            $table->json('needs_flagged')->nullable();               // ['winter_clothes','glasses']
            $table->date('next_visit_due')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['orphan_id', 'visited_at']);
            $table->index('next_visit_due');
        });

        /**
         * رسائل الكافل ↔ الطفل، بوساطة إجبارية.
         * حالة pending_review افتراضية على مستوى المخطّط لا على مستوى النيّة —
         * تطبيقٌ لسياسة حماية الطفل المعلنة في الأسئلة الشائعة.
         */
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsorship_id')->constrained()->cascadeOnDelete();
            $table->enum('sender_type', ['sponsor', 'orphan']);
            $table->text('body')->nullable();
            $table->string('audio_path')->nullable();
            $table->enum('status', ['pending_review', 'approved', 'rejected', 'delivered'])
                ->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);                 // طابور المراجعة
            $table->index(['sponsorship_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('field_visits');
        Schema::dropIfExists('report_views');
        Schema::dropIfExists('report_media');
        Schema::dropIfExists('reports');
    }
};

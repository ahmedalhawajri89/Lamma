<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الأيتام — قلب النظام.
 *
 * ملاحظتان على التصميم:
 *  1) نخزّن `date_of_birth` لا `age`. العمر يتغيّر كل عام والتاريخ لا،
 *     فالعمر accessor مشتق لا عمود يحتاج تحديثاً سنوياً.
 *  2) `funded_percent` استثناءٌ مقصود لقاعدة «لا تخزين لما يمكن اشتقاقه»:
 *     حسابه يتطلب SUM على `sponsorships` لكل بطاقة في صفحة تعرض عشرين بطاقة.
 *     يحدّثه OrphanFundingObserver عند كل تغيّر في الكفالات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orphans', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 16)->unique();          // KFL-1042 — المعرّف العلني

            // مفصولان للفرز وللخصوصية: العرض العام بالاسم الأول فقط
            $table->string('first_name', 60);
            $table->string('last_name', 60);

            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth');

            $table->foreignId('family_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();

            $table->enum('status', [
                'draft', 'pending_verification', 'active',
                'fully_funded', 'graduated', 'withdrawn', 'deceased',
            ])->default('draft');

            $table->boolean('is_urgent')->default(false);
            $table->string('photo_path')->nullable();
            $table->string('grade_level', 60)->nullable();
            $table->string('health_status', 160)->nullable();
            $table->string('dream', 160)->nullable();
            $table->text('story')->nullable();

            // قد يفوق سعر البرنامج في حالة صحية خاصة
            $table->decimal('monthly_need', 10, 2);
            $table->unsignedTinyInteger('funded_percent')->default(0);

            // لا يُنشر يتيم بلا توثيق — تفرضه OrphanPolicy لا الواجهة
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // كل فهرس يخدم استعلاماً حقيقياً في orphans.html:
            $table->index(['status', 'is_urgent', 'funded_percent'], 'idx_orphans_grid');
            $table->index(['program_id', 'status'], 'idx_orphans_program');
            $table->index('family_id');
            $table->index('published_at');
        });

        // البحث النصي في شريط orphans.html
        Schema::table('orphans', function (Blueprint $table) {
            $table->fullText(['first_name', 'last_name', 'story'], 'ft_orphans_search');
        });

        /**
         * المستندات — هذا الجدول هو ما يجعل ادّعاء «ثلاث مراحل تحقّق»
         * قابلاً للإثبات بدل أن يكون نصاً تسويقياً في المدونة.
         */
        Schema::create('orphan_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orphan_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'birth_certificate', 'death_certificate', 'medical_report',
                'school_record', 'guardianship_proof', 'photo', 'other',
            ]);
            $table->string('path');
            $table->string('original_name', 180);
            $table->string('mime', 100);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['orphan_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orphan_documents');
        Schema::dropIfExists('orphans');
    }
};

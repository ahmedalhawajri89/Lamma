<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الأوصياء والأسر.
 *
 * طبقة `families` هي ما يجعل «كفالة الأسرة» ممكنة، وتربط الإخوة تلقائياً
 * بلا جدول self-join، وتنقل الدولة والمدينة والوصي من اليتيم إلى أسرته
 * حيث تنتمي فعلاً (يوسف وإخوته الأربعة يشتركون في العنوان نفسه).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 120);
            $table->enum('relation', [
                'mother', 'father', 'grandmother', 'grandfather',
                'uncle', 'aunt', 'sibling', 'other',
            ]);
            $table->string('phone', 24)->nullable();

            // مُجزّأ لا مخزّن: يكشف التكرار دون الاحتفاظ برقم هوية قابل للتسريب
            $table->char('national_id_hash', 64)->nullable()->index();

            $table->date('date_of_birth')->nullable();
            $table->string('occupation', 80)->nullable();
            $table->decimal('monthly_income', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('family_code', 16)->unique();            // FAM-0217
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address', 200)->nullable();
            $table->unsignedTinyInteger('members_count')->default(1);
            $table->decimal('monthly_income', 10, 2)->nullable();
            $table->enum('housing_status', ['owned', 'rented', 'hosted', 'displaced'])->default('rented');
            $table->timestamps();

            $table->index(['country_id', 'city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('families');
        Schema::dropIfExists('guardians');
    }
};

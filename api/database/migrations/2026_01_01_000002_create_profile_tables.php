<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ملفات تعريف المستخدمين — 1:1 مع `users`.
 *
 * لماذا جدولان منفصلان بدل حشو الأعمدة في `users`؟
 * لأن الكافل يحتاج عملة مفضّلة وتفضيل زكاة وكود كافل،
 * والموظف يحتاج كود وظيفي وبلداً مُسنداً — ولا معنى لأيٍّ منها في الآخر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sponsor_code', 16)->unique();          // SPN-00412
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city', 80)->nullable();
            $table->char('preferred_currency', 3)->default('USD');

            // يوجّه النظام لعرض الحالات المستحقة زكوياً أولاً
            $table->boolean('prefers_zakat')->default(false);
            $table->boolean('is_anonymous')->default(false);        // لا يُذكر اسمه في الشكر العلني

            $table->json('communication_prefs')->nullable();        // {email:true, sms:false, whatsapp:true}
            $table->date('sponsoring_since')->nullable();
            $table->text('internal_notes')->nullable();             // للإدارة فقط — لا يُعاد أبداً في API الكافل
            $table->timestamps();

            $table->index('sponsoring_since');
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code', 16)->unique();
            $table->string('title', 80)->nullable();

            // يقيّد المشرف الميداني ببلده — تُطبّقه الـ Policy لا الواجهة
            $table->foreignId('assigned_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->date('hired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('sponsors');
    }
};

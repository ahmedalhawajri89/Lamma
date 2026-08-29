<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المستخدمون — الجدول المركزي.
 * كل فاعل بشري في النظام مستخدم: الكافل، والمشرف الميداني، والمحاسب، والمدير.
 * ما يخصّ الكافل وحده يذهب إلى `sponsors`، وما يخصّ الموظف إلى `staff_profiles`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 180)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 24)->nullable();
            $table->string('avatar_path')->nullable();

            // يمهّد لـ i18n دون أن يفرضه الآن
            $table->char('locale', 2)->default('ar');
            $table->string('timezone', 64)->default('Asia/Gaza');

            $table->enum('status', ['pending', 'active', 'suspended'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // 2FA — السرّ مشفّر عبر cast في الموديل، لا يُخزَّن خاماً
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'last_login_at']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

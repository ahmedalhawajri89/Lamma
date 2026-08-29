<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المحتوى — ما كان ثابتاً في data.js يصبح قابلاً للتحرير من اللوحة:
 * POSTS و FAQS و STORIES و PARTNERS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name_ar', 60);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 180)->unique();
            $table->string('title', 200);
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('post_categories')->nullOnDelete();
            $table->string('cover_path')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('read_minutes')->default(3);
            $table->enum('status', ['draft', 'scheduled', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'published_at']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 255);
            $table->text('answer');
            $table->string('category', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name', 120);
            $table->string('role', 120)->nullable();
            $table->text('quote');
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('icon', 60)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('url', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40)->default('site');
            $table->string('key', 80);
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->timestamps();

            $table->unique(['group', 'key']);
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 180)->unique();
            $table->string('confirm_token', 64)->nullable();
            $table->timestamp('confirmed_at')->nullable();           // double opt-in
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('source', 40)->nullable();                // footer, checkout…
            $table->timestamps();

            $table->index('confirmed_at');
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 180);
            $table->string('phone', 24)->nullable();
            $table->string('subject', 180)->nullable();
            $table->text('body');
            $table->enum('status', ['new', 'in_progress', 'closed'])->default('new');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_categories');
    }
};

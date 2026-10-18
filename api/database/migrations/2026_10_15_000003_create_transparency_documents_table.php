<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وثائق الشفافية المنشورة: الميزانية المدقّقة، والتقرير السنوي، وتقرير
 * الزكاة، والتراخيص.
 *
 * كانت صفحة الشفافية تعرض أربعة أزرار «تحميل PDF» إلى `#`: أسوأ ما يجده
 * زائرٌ جاء ليتحقّق. الآن يرفعها المدير من اللوحة، ونوعٌ بلا ملف يُقال عنه
 * «يُنشر بعد اعتماد المدقق» لا زرّ لا يفعل شيئاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transparency_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 32);
            // التراخيص بلا سنة: وثيقة قائمة تُستبدل ولا تتراكم
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('title', 160);
            $table->string('file_path');
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            // نسخة واحدة لكل نوع وسنة: الرفع الثاني يستبدل لا يكرّر
            $table->unique(['kind', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transparency_documents');
    }
};

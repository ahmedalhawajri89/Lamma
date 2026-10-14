<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ردود الإدارة على رسائل «تواصل معنا».
 *
 * كان الرد بزرّ mailto يفتح بريد الموظف الشخصي: يخرج الرد من المنصة، ولا
 * يبقى أثرٌ لمن ردّ ولا بماذا. الرد يُرسل الآن من المنصة ويُحفظ هنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_replies');
    }
};

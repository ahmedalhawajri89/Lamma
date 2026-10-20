<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لغة الصفحة التي كتب منها الزائر: بها يصله الردّ (ContactReplyMail). زائرٌ
 * من الموقع الإنجليزي كان يصله ردٌّ عربي القالب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->char('locale', 2)->default('ar')->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', fn (Blueprint $t) => $t->dropColumn('locale'));
    }
};

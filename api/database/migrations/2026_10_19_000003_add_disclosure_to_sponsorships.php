<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الإفصاح قبل الكفالة: أيّ نصٍّ قرأه الكافل ووافق عليه، ومتى. يُحفظ رقم
 * نسخة النص (config kafala.disclosure_version) لا النص، فتغييره لاحقاً
 * يُعرف به من وافق على أيّ نسخة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->string('disclosure_version', 20)->nullable()->after('cancellation_reason');
            $table->timestamp('disclosure_accepted_at')->nullable()->after('disclosure_version');
        });
    }

    public function down(): void
    {
        Schema::table('sponsorships', fn (Blueprint $t) => $t->dropColumn(['disclosure_version', 'disclosure_accepted_at']));
    }
};

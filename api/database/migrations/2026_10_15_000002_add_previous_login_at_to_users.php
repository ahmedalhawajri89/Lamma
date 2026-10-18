<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الدخول السابق: «منذ آخر دخول» يقارن به. `last_login_at` يُكتب لحظة
 * الدخول الحالي، فالمقارنة به وحده لا تُظهر شيئاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('previous_login_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('previous_login_at');
        });
    }
};

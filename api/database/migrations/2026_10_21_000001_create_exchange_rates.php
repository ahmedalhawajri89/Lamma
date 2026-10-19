<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 5: الكافل بعملته.
 *
 * أسعار الصرف إلى عملة الأساس، سعرٌ واحد حاليّ لكل عملة يحدّثه
 * kafala:rates-refresh يومياً. تاريخ كل سعرٍ استُعمل محفوظٌ على الدفعة نفسها
 * (payments.fx_rate)، فلا حاجة لسجلّ أسعار هنا.
 *
 * تُزرع بأسعارٍ ابتدائية تقريبية (source=seed) كي لا يعمل النظام يوماً بلا
 * سعر، ويستبدلها أول تحديث.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('currency', 3)->unique();
            $table->decimal('rate_to_base', 18, 8);            // كم وحدة أساس تساوي وحدة واحدة من العملة
            $table->string('source', 40)->default('seed');      // seed | open.er-api.com | manual
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('exchange_rates')->insert(collect([
            'USD' => 1, 'EUR' => 1.08, 'GBP' => 1.27, 'SAR' => 0.2667, 'AED' => 0.2723, 'QAR' => 0.2747,
            'CAD' => 0.73, 'ILS' => 0.27, 'JOD' => 1.41, 'EGP' => 0.0205, 'TRY' => 0.029,
        ])->map(fn ($rate, $code) => [
            'currency' => $code, 'rate_to_base' => $rate, 'source' => 'seed', 'fetched_at' => null,
            'created_at' => $now, 'updated_at' => $now,
        ])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};

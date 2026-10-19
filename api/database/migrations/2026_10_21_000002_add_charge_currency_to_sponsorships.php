<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الكفالة بعملة الكافل: مبلغٌ شهري ثابت بعملته يُخصم كل دورة
 * (charge_amount بـ charge_currency)، وmonthly_amount يبقى ما يعادله بعملة
 * الأساس، فكل حساب تمويلٍ وتقرير يبقى كما هو. يعيد حسابه
 * SponsorshipRepricer مع كل تحديث للأسعار.
 *
 * فارغان = الكفالة بعملة الأساس، كما كانت كل الكفالات قبلها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->char('charge_currency', 3)->nullable()->after('currency');
            $table->decimal('charge_amount', 12, 2)->nullable()->after('charge_currency');
        });
    }

    public function down(): void
    {
        Schema::table('sponsorships', fn (Blueprint $t) => $t->dropColumn(['charge_currency', 'charge_amount']));
    }
};

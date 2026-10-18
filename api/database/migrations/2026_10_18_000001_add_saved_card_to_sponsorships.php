<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * البطاقة المحفوظة للخصم الشهري.
 *
 * رمز البطاقة الذي تُرسله صفحة الكفالة يُستعمل مرة واحدة. فكان أمر الفوترة
 * لا يملك ما يخصم به، ويسجّل الدفعة «ناجحة» بلا أن يمرّ بالبوابة أصلاً. البوابة
 * تُعيد عند أول خصم مرجعاً قابلاً لإعادة الاستعمال (pm_… عند Stripe مثلاً)،
 * يُحفظ هنا ويُخصم به كل شهر. ليس رقم البطاقة: لا يصلح لشيء خارج البوابة.
 *
 * وآخر أربعة أرقام ونوعها ليظهر في الإيصال «فيزا ••4242» على كل دفعة آلية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->string('gateway_payment_method', 191)->nullable()->after('payment_method');
            $table->string('card_brand', 20)->nullable()->after('gateway_payment_method');
            $table->char('card_last4', 4)->nullable()->after('card_brand');
        });
    }

    public function down(): void
    {
        Schema::table('sponsorships', function (Blueprint $table) {
            $table->dropColumn(['gateway_payment_method', 'card_brand', 'card_last4']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صندوق الاستمرارية: حين يتعثّر قسط كافلٍ ولا يُحلّ، يغطّي الصندوق شهر
 * الطفل بدل أن ينقطع ما يصله. دخله حصةٌ من نسبة التشغيل نفسها (لا رسمٌ
 * إضافي على أحد) وتبرّعاتٌ له، وخرجه تغطياتٌ يُحوَّل مالها مع تحويل
 * الجمعية الشهري، فيظهر في سطر الطفل وفي «أين ذهب مالك».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuity_fund_entries', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);                                    // share | donation | grant
            $table->decimal('amount_base', 12, 2);                         // موجبٌ دائماً، والاتجاه من النوع
            $table->date('period');                                        // أول الشهر
            $table->foreignId('sponsorship_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('orphan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('association_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'period'], 'fund_entries_kind_period_index');
            // تغطيةٌ واحدة للكفالة في الشهر، مهما تكرّر التشغيل
            $table->unique(['kind', 'sponsorship_id', 'period'], 'fund_entries_grant_unique');
        });

        Schema::table('association_transfers', function (Blueprint $table) {
            $table->decimal('fund_base', 12, 2)->default(0)->after('fee_base');
        });
        Schema::table('association_transfer_lines', function (Blueprint $table) {
            $table->decimal('fund_base', 12, 2)->default(0)->after('fee_base');
        });

        DB::table('settings')->insertOrIgnore([
            'group' => 'finance', 'key' => 'continuity_fund_percent', 'value' => '1', 'type' => 'number',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('association_transfer_lines', fn (Blueprint $t) => $t->dropColumn('fund_base'));
        Schema::table('association_transfers', fn (Blueprint $t) => $t->dropColumn('fund_base'));
        Schema::dropIfExists('continuity_fund_entries');
        DB::table('settings')->where('group', 'finance')->where('key', 'continuity_fund_percent')->delete();
    }
};

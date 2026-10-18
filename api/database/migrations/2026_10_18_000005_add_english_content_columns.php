<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المحتوى بالإنجليزية للموقع تحت /en.
 *
 * البرامج وبنودها: أعمدة *_en بجانب *_ar، فهي قليلة وثابتة الشكل.
 * والطفل: عمود translations واحد ({"en": {"first_name": …, "story": …}})، لأن
 * حقوله النصية كثيرة، ولغةٌ ثالثة تُضاف فيه بلا ترحيل. ما لم يُترجم يعود
 * للعربية (Locale::pick و Orphan::translated).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->text('summary_en')->nullable()->after('summary_ar');
            $table->text('description_en')->nullable()->after('description_ar');
        });

        Schema::table('program_features', function (Blueprint $table) {
            $table->string('label_en', 160)->nullable()->after('label_ar');
        });

        Schema::table('orphans', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('story');
        });
    }

    public function down(): void
    {
        Schema::table('orphans', fn (Blueprint $t) => $t->dropColumn('translations'));
        Schema::table('program_features', fn (Blueprint $t) => $t->dropColumn('label_en'));
        Schema::table('programs', fn (Blueprint $t) => $t->dropColumn(['summary_en', 'description_en']));
    }
};

<?php

declare(strict_types=1);

use App\Enums\CareArrangement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 2: الجمعيات الشريكة وترتيب الرعاية.
 *
 * «لمّة» شبكة جمعيات محلية موثّقة: كل أسرة تتبع جمعية تعرفها وتزورها، وكل
 * موظفٍ إما للمنصة (بلا جمعية) أو لجمعية بعينها. والجدول `associations` لا
 * `partners`: ذاك لشعارات شريط الثقة في الصفحة الرئيسية.
 *
 * وترتيب الرعاية على الأسرة: مع من يعيش الطفل، ومن أقرّ بذلك ومتى، ومتى
 * يُراجَع. الأسر الموجودة تأخذ تقديراً من صلة الوصي، بلا إقرار: الإقرار
 * تؤكّده زيارة ميدانية (field_visits.care_confirmed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('associations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name_ar', 160);
            $table->string('name_en', 160)->nullable();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();

            // التسجيل الرسمي: رقمه والجهة التي سجّلته
            $table->string('registration_number', 60)->nullable();
            $table->string('registration_authority', 160)->nullable();

            $table->enum('status', ['applied', 'under_review', 'verified', 'suspended'])->default('applied');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspension_reason', 300)->nullable();

            // ما وقّعته الجمعية: سياسة حماية الطفل، والإقرار بأن أطفالها في أسرهم
            $table->timestamp('safeguarding_signed_at')->nullable();
            $table->timestamp('family_care_attested_at')->nullable();

            $table->string('email', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website', 200)->nullable();
            $table->text('about_ar')->nullable();
            $table->text('about_en')->nullable();

            // بيانات التحويل مشفّرة (cast encrypted)، وعملته
            $table->text('transfer_details')->nullable();
            $table->char('transfer_currency', 3)->nullable();

            $table->timestamps();

            $table->index(['country_id', 'status']);
        });

        Schema::table('families', function (Blueprint $table) {
            $table->foreignId('association_id')->nullable()->after('country_id')->constrained()->restrictOnDelete();
            $table->enum('care_arrangement', array_column(CareArrangement::cases(), 'value'))->nullable()->after('housing_status');
            $table->timestamp('care_attested_at')->nullable()->after('care_arrangement');
            $table->foreignId('care_attested_by')->nullable()->after('care_attested_at')->constrained('users')->nullOnDelete();
            $table->date('care_review_due_at')->nullable()->after('care_attested_by');
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            // فارغ: موظف المنصة. وإلا فموظف هذه الجمعية، لا يرى غيرها
            $table->foreignId('association_id')->nullable()->after('assigned_country_id')->constrained()->nullOnDelete();
        });

        Schema::table('field_visits', function (Blueprint $table) {
            // هل أكّد المشرف في هذه الزيارة أن الطفل يعيش حيث يقول ملفه؟
            $table->boolean('care_confirmed')->nullable()->after('needs_flagged');
        });

        // تقدير أول لترتيب الرعاية من صلة الوصي، بلا إقرار
        DB::table('families')
            ->join('guardians', 'guardians.id', '=', 'families.guardian_id')
            ->select('families.id', 'guardians.relation')
            ->orderBy('families.id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $care = CareArrangement::fromGuardianRelation($row->relation);
                    if ($care !== null) {
                        DB::table('families')->where('id', $row->id)->update(['care_arrangement' => $care->value]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('field_visits', fn (Blueprint $t) => $t->dropColumn('care_confirmed'));
        Schema::table('staff_profiles', function (Blueprint $t) {
            $t->dropConstrainedForeignId('association_id');
        });
        Schema::table('families', function (Blueprint $t) {
            $t->dropConstrainedForeignId('care_attested_by');
            $t->dropConstrainedForeignId('association_id');
            $t->dropColumn(['care_arrangement', 'care_attested_at', 'care_review_due_at']);
        });
        Schema::dropIfExists('associations');
    }
};

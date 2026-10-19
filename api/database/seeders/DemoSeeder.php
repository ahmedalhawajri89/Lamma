<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AssociationStatus;
use App\Enums\CareArrangement;
use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\HousingStatus;
use App\Enums\MessageStatus;
use App\Enums\OrphanStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReportStatus;
use App\Enums\SponsorshipStatus;
use App\Enums\TransferStatus;
use App\Enums\UserRole;
use App\Models\Association;
use App\Models\AssociationTransfer;
use App\Models\City;
use App\Models\Country;
use App\Models\DisbursementCategory;
use App\Models\Family;
use App\Models\Orphan;
use App\Models\Program;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\TransferPreparer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * بيانات تجريبية واقعية.
 *
 * الثمانية الأوائل هم أيتام data.js أنفسهم بالأسماء والقصص نفسها، فيبقى
 * الموقع العام معروضاً بمحتواه المألوف بعد ربطه بالـ API. ثم نولّد بقية
 * الحالات والكفلاء وأربعاً وعشرين شهراً من المدفوعات والصرف، لأن لوحة
 * تحليلات على ثمانية صفوف لا تُظهر شيئاً.
 *
 * الإدراج بالجملة وبلا أحداث، ثم تُحسب نسب التمويل مرة واحدة في النهاية.
 */
class DemoSeeder extends Seeder
{
    private const MONTHS_OF_HISTORY = 24;

    private const FIRST_NAMES_MALE = [
        'أحمد', 'محمد', 'يوسف', 'خالد', 'سعيد', 'عمر', 'إبراهيم', 'مصطفى',
        'حمزة', 'زياد', 'كرم', 'أنس', 'باسل', 'رامي', 'طارق', 'نور الدين',
    ];

    private const FIRST_NAMES_FEMALE = [
        'أمنية', 'عائشة', 'مريم', 'ليان', 'سارة', 'هدى', 'رزان', 'جنى',
        'سلمى', 'لينا', 'رغد', 'دعاء', 'آية', 'ملك', 'تالا', 'شهد',
    ];

    private const LAST_NAMES = [
        'الحواجري', 'أبو ندى', 'الشوا', 'المصري', 'النجار', 'قاسم', 'الحاج',
        'عبد الله', 'البلعاوي', 'السقا', 'زيدان', 'الأغا', 'الفرا', 'دياب',
    ];

    private const DREAMS = [
        'أن تصبح معلمة', 'أن يصبح طبيباً', 'أن ترسم كل يوم', 'أن يصبح مهندساً',
        'أن تدرس الصيدلة', 'أن يلعب في نادٍ للكرة', 'أن تحفظ القرآن',
        'أن يقرأ بلا نظارة مكسورة', 'أن يصير طياراً', 'أن تكتب قصة',
    ];

    private const GRADES = [
        'الروضة', 'تمهيدي', 'الصف الأول', 'الصف الثاني', 'الصف الثالث',
        'الصف الرابع', 'الصف الخامس', 'الصف السادس', 'الصف السابع', 'الصف الثامن',
    ];

    public function run(): void
    {
        $this->command->info('  · المستخدمون والأدوار…');
        $staff = $this->staff();

        $this->command->info('  · الجمعيات الشريكة…');
        $this->associations($staff['admin']);

        $this->command->info('  · الأسر والأيتام…');
        $orphanIds = $this->orphans();

        $this->command->info('  · الكفلاء…');
        $sponsorIds = $this->sponsors();

        $this->command->info('  · الكفالات والمدفوعات…');
        $this->sponsorshipsAndPayments($orphanIds, $sponsorIds);

        $this->command->info('  · الصرف والتقارير والزيارات…');
        $this->disbursements($orphanIds, $staff);
        $this->reportsAndVisits($orphanIds, $staff, $sponsorIds);

        $this->command->info('  · التحويلات للجمعيات…');
        $this->transfers($staff);

        $this->command->info('  · سجل النشاط…');
        $this->call(ActivityDemoSeeder::class);

        $this->command->info('  · إعادة حساب نسب التمويل…');
        Orphan::query()->chunkById(200, function ($chunk): void {
            foreach ($chunk as $orphan) {
                $orphan->recalculateFunding();
            }
        });
    }

    /* ==================================================================== */

    /** @return array{admin:int, accountant:int, supervisors:array<int,int>} */
    private function staff(): array
    {
        $palestine = Country::where('iso2', 'PS')->value('id');

        $admin = User::updateOrCreate(['email' => 'admin@lamma.org'], [
            'name' => 'أحمد الحواجري',
            'password' => Hash::make('password'),
            'phone' => '+970599000001',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->syncRoles(UserRole::Admin->value);

        $accountant = User::updateOrCreate(['email' => 'accountant@lamma.org'], [
            'name' => 'هدى النجار',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $accountant->syncRoles(UserRole::Accountant->value);
        $accountant->staffProfile()->updateOrCreate([], [
            'employee_code' => 'EMP-0002',
            'title' => 'محاسبة',
            'hired_at' => now()->subYears(3),
        ]);

        $supervisors = [];
        foreach (Country::operational()->take(4)->get() as $i => $country) {
            $user = User::updateOrCreate(['email' => "supervisor{$i}@lamma.org"], [
                // اسمٌ لا مسمّى: البلد في ملف الموظف (المسمّى والبلد المُسنَد) لا في اسمه
                'name' => self::FIRST_NAMES_MALE[$i + 4].' '.self::LAST_NAMES[$i],
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $user->syncRoles(UserRole::FieldSupervisor->value);
            $user->staffProfile()->updateOrCreate([], [
                'employee_code' => 'EMP-'.str_pad((string) (10 + $i), 4, '0', STR_PAD_LEFT),
                'title' => 'مشرف ميداني في '.$country->name_ar,
                'assigned_country_id' => $country->id,
                'hired_at' => now()->subYears(random_int(1, 6)),
            ]);
            $supervisors[] = $user->id;
        }

        $admin->staffProfile()->updateOrCreate([], [
            'employee_code' => 'EMP-0001',
            'title' => 'مدير المنصة',
            'assigned_country_id' => $palestine,
            'hired_at' => now()->subYears(8),
        ]);

        return ['admin' => $admin->id, 'accountant' => $accountant->id, 'supervisors' => $supervisors];
    }

    /* ==================================================================== */

    /**
     * الثمانية الأوائل من data.js حرفياً، ثم مئة وأربعون حالة مولّدة.
     *
     * @return array<int, array{id:int, need:float, funded:int, country:int, program:int}>
     */
    private function orphans(): array
    {
        $programs = Program::pluck('monthly_price', 'slug')->all();
        $programIds = Program::pluck('id', 'slug')->all();
        $countries = Country::operational()->pluck('id', 'iso2')->all();
        $citiesByCountry = City::all()->groupBy('country_id');

        /** أيتام data.js: [code, name, age, gender, iso2, city, program, funded, urgent, grade, health, dream, story] */
        $seed = [
            ['KFL-1042', 'أمنية', 8, 'female', 'PS', 'غزة', 'education', 62, true, 'الصف الثالث', 'سليمة', 'أن تصبح معلمة', 'فقدت أمنية والدها في قصف على حيّها، فانتقلت مع أمها وأخويها إلى بيت أقارب. رغم ذلك لم تترك مدرستها يوماً، وتحتفظ بدفتر تكتب فيه ما تودّ تعليمه لطلابها يوماً ما.'],
            ['KFL-1043', 'أحمد', 10, 'male', 'YE', 'صنعاء', 'comprehensive', 24, true, 'الصف الخامس', 'يحتاج متابعة تغذية', 'أن يصبح طبيباً', 'يعيل أحمد نفسه وأخته الصغيرة بمساعدة جدته المسنّة بعد وفاة والديه. يمشي ساعةً كل صباح للوصول إلى مدرسته، ويقول إنه يريد أن يتعلم الطب ليعالج أهل قريته.'],
            ['KFL-1044', 'عائشة', 6, 'female', 'SY', 'إدلب', 'health', 78, false, 'الروضة', 'تحتاج جلسات علاج طبيعي', 'أن ترسم كل يوم', 'أصيبت عائشة في ساقها وهي في الثالثة، وتحتاج جلسات علاج طبيعي منتظمة لتستعيد قدرتها على المشي بلا ألم. ترسم كل يوم بيتاً بحديقة، وتقول إنه بيتهم القديم.'],
            ['KFL-1045', 'سعيد', 9, 'male', 'IQ', 'الموصل', 'comprehensive', 91, false, 'الصف الرابع', 'سليم', 'أن يصبح مهندساً', 'يحب سعيد تفكيك الأجهزة القديمة وإعادة تركيبها. أصلح مروحة جيرانه بنفسه، وحلمه أن يبني بيتاً جديداً لأمه بيديه.'],
            ['KFL-1046', 'مريم', 12, 'female', 'SD', 'أم درمان', 'education', 40, true, 'الصف السابع', 'سليمة', 'أن تدرس الصيدلة', 'انقطعت مريم عن الدراسة عاماً كاملاً بسبب النزوح، ثم عادت وتفوّقت على صفها. تحفظ أسماء النباتات الطبية التي كانت جدتها تستعملها، وتريد أن تدرس الصيدلة.'],
            ['KFL-1047', 'يوسف', 7, 'male', 'PS', 'الخليل', 'family', 15, true, 'الصف الثاني', 'سليم', 'أن يلعب في نادٍ للكرة', 'يوسف أصغر خمسة إخوة فقدوا والدهم في حادث عمل. تعمل أمه بالخياطة في البيت ولا يكفي دخلها، وكفالة الأسرة تبقيهم مجتمعين تحت سقف واحد.'],
            ['KFL-1048', 'ليان', 5, 'female', 'LB', 'طرابلس', 'comprehensive', 55, false, 'تمهيدي', 'سليمة', 'أن تحفظ القرآن', 'ليان أصغر من أن تفهم ما جرى، لكنها تسأل عن أبيها كل ليلة. تحفظ سوراً قصيرة وترددها بصوت عالٍ، وأمها تقول إن صوتها هو ما يبقيها صامدة.'],
            ['KFL-1049', 'خالد', 11, 'male', 'SO', 'مقديشو', 'health', 33, false, 'الصف السادس', 'يعاني من ضعف نظر شديد', 'أن يقرأ بلا نظارة مكسورة', 'يجلس خالد في المقعد الأول لأنه لا يرى اللوح من بعيد، ونظارته الوحيدة مكسورة من طرف. فحص نظر وعلاج بسيط قد يغيّران مسار تعليمه كله.'],
        ];

        $out = [];
        $familyCounter = 0;

        foreach ($seed as $row) {
            [$code, $name, $age, $gender, $iso2, $cityName, $programSlug, $funded, $urgent, $grade, $health, $dream, $story] = $row;

            $countryId = $countries[$iso2];
            $cityId = City::where('country_id', $countryId)->where('name_ar', $cityName)->value('id');

            // من يعيش معه الطفل كما تقول قصته المكتوبة، لا عشوائياً
            $relation = match ($code) {
                'KFL-1043' => GuardianRelation::Grandmother,   // «بمساعدة جدته المسنّة»
                'KFL-1049' => GuardianRelation::Uncle,
                default => GuardianRelation::Mother,           // «انتقلت مع أمها»، «تعمل أمه بالخياطة»…
            };
            $familyId = $this->makeFamily(++$familyCounter, $countryId, $cityId, $gender === 'male' ? 3 : 4, $relation);

            $orphanId = DB::table('orphans')->insertGetId([
                'reference_code' => $code,
                'first_name' => $name,
                'last_name' => self::LAST_NAMES[array_rand(self::LAST_NAMES)],
                'gender' => $gender,
                'date_of_birth' => now()->subYears($age)->subMonthsNoOverflow(random_int(0, 11))->toDateString(),
                'family_id' => $familyId,
                'program_id' => $programIds[$programSlug],
                'status' => OrphanStatus::Active->value,
                'is_urgent' => $urgent,
                'photo_path' => 'orphans/'.$code.'.webp',
                'grade_level' => $grade,
                'health_status' => $health,
                'dream' => $dream,
                'story' => $story,
                'monthly_need' => $programs[$programSlug],
                'funded_percent' => 0,
                'verified_at' => now()->subMonthsNoOverflow(random_int(2, 20)),
                'published_at' => now()->subMonthsNoOverflow(random_int(1, 18)),
                'created_at' => now()->subMonthsNoOverflow(random_int(2, 22)),
                'updated_at' => now(),
            ]);

            $this->makeDocuments($orphanId);

            $out[] = [
                'id' => $orphanId,
                'need' => (float) $programs[$programSlug],
                'funded' => $funded,
                'country' => $countryId,
                'program' => $programIds[$programSlug],
            ];
        }

        // حالات مولّدة — لتكون التحليلات ذات معنى
        $slugs = array_keys($programIds);
        $countryIds = array_values($countries);

        for ($i = 0; $i < 140; $i++) {
            $countryId = $countryIds[array_rand($countryIds)];
            $cityId = optional($citiesByCountry->get($countryId))->random()?->id;
            $gender = random_int(0, 1) === 1 ? 'male' : 'female';
            $slug = $slugs[array_rand($slugs)];
            $funded = $this->weightedFunded();
            $urgent = $funded < 50 && random_int(0, 100) < 55;

            $familyId = $this->makeFamily(++$familyCounter, $countryId, $cityId, random_int(2, 7));

            $status = match (true) {
                $i % 23 === 0 => OrphanStatus::PendingVerification,
                $i % 31 === 0 => OrphanStatus::Graduated,
                default => OrphanStatus::Active,
            };

            $published = $status === OrphanStatus::Active ? now()->subDays(random_int(10, 700)) : null;

            $orphanId = DB::table('orphans')->insertGetId([
                'reference_code' => 'KFL-'.(2000 + $i),
                'first_name' => $gender === 'male'
                    ? self::FIRST_NAMES_MALE[array_rand(self::FIRST_NAMES_MALE)]
                    : self::FIRST_NAMES_FEMALE[array_rand(self::FIRST_NAMES_FEMALE)],
                'last_name' => self::LAST_NAMES[array_rand(self::LAST_NAMES)],
                'gender' => $gender,
                'date_of_birth' => now()->subYears(random_int(4, 16))->subDays(random_int(0, 364))->toDateString(),
                'family_id' => $familyId,
                'program_id' => $programIds[$slug],
                'status' => $status->value,
                'is_urgent' => $urgent,
                'photo_path' => null,
                'grade_level' => self::GRADES[array_rand(self::GRADES)],
                'health_status' => random_int(0, 100) < 75 ? 'سليم' : 'يحتاج متابعة دورية',
                'dream' => self::DREAMS[array_rand(self::DREAMS)],
                'story' => 'حالة موثّقة ميدانياً ضمن برنامج الكفالة، تفاصيلها في ملف التحقق.',
                'monthly_need' => $programs[$slug],
                'funded_percent' => 0,
                'verified_at' => $status === OrphanStatus::PendingVerification ? null : now()->subDays(random_int(20, 800)),
                'published_at' => $published,
                'created_at' => now()->subDays(random_int(30, 900)),
                'updated_at' => now(),
            ]);

            if ($status !== OrphanStatus::PendingVerification) {
                $this->makeDocuments($orphanId);
            }

            $out[] = [
                'id' => $orphanId,
                'need' => (float) $programs[$slug],
                'funded' => $status === OrphanStatus::Active ? $funded : 0,
                'country' => $countryId,
                'program' => $programIds[$slug],
            ];
        }

        return $out;
    }

    /** توزيع واقعي: الأغلب ممول جزئياً، وقلة مكتملة، وقلة بلا كفالة. */
    private function weightedFunded(): int
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 12 => 0,
            $roll <= 30 => 100,
            $roll <= 60 => random_int(20, 60),
            default => random_int(60, 95),
        };
    }

    /** جمعية شريكة موثّقة لكل بلد عامل، تُسند إليها أسره. */
    private array $associationByCountry = [];

    private int $adminId = 0;

    /**
     * جمعية تجريبية لكل بلد. الاسم عامٌّ مقصود («جمعية لمّة الشريكة في …») كي
     * لا يُظنّ جمعيةً حقيقية بعينها.
     */
    private function associations(int $adminId): void
    {
        $this->adminId = $adminId;

        foreach (Country::operational()->get() as $i => $country) {
            $association = Association::updateOrCreate(['slug' => 'lamma-'.strtolower($country->iso2)], [
                'name_ar' => 'جمعية لمّة الشريكة في '.$country->name_ar,
                'name_en' => 'Lamma Partner Association, '.$country->name_en,
                'country_id' => $country->id,
                'registration_number' => 'REG-'.$country->iso2.'-'.(2010 + $i),
                'registration_authority' => 'سجل الجمعيات الأهلية',
                'status' => AssociationStatus::Verified,
                'verified_at' => now()->subMonths(random_int(2, 18)),
                'verified_by' => $adminId,
                'safeguarding_signed_at' => now()->subMonths(random_int(19, 30)),
                'family_care_attested_at' => now()->subMonths(random_int(19, 30)),
                'about_ar' => 'جمعية محلية تعرف أسر الأيتام في '.$country->name_ar.' وتزورها، وتتابع كل طفل في بيته ومدرسته.',
                'about_en' => "A local association that knows orphans' families in {$country->name_en}, visits them, and follows each child at home and at school.",
                'transfer_currency' => $country->currency_code,
            ]);
            $this->associationByCountry[$country->id] = $association->id;
        }
    }

    private function makeFamily(int $n, int $countryId, ?int $cityId, int $members, ?GuardianRelation $relation = null): int
    {
        $relation ??= [GuardianRelation::Mother, GuardianRelation::Mother, GuardianRelation::Grandmother, GuardianRelation::Uncle, GuardianRelation::Aunt][random_int(0, 4)];
        $attested = now()->subDays(random_int(10, 300));

        $guardianId = DB::table('guardians')->insertGetId([
            'full_name' => self::FIRST_NAMES_FEMALE[array_rand(self::FIRST_NAMES_FEMALE)].' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)],
            'relation' => $relation->value,
            'phone' => '+9705'.random_int(10000000, 99999999),
            'national_id_hash' => hash('sha256', "demo-{$n}".config('kafala.guardian_id_pepper')),
            'occupation' => ['ربة منزل', 'خياطة', 'عاملة نظافة', 'بائع متجول', 'بلا عمل'][random_int(0, 4)],
            'monthly_income' => random_int(0, 180),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('families')->insertGetId([
            'family_code' => 'FAM-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'guardian_id' => $guardianId,
            'country_id' => $countryId,
            'city_id' => $cityId,
            'address' => 'حي '.['النصر', 'الرمال', 'الزيتون', 'الشجاعية', 'التفاح'][random_int(0, 4)],
            'members_count' => $members,
            'monthly_income' => random_int(0, 220),
            'housing_status' => [HousingStatus::Rented, HousingStatus::Hosted, HousingStatus::Displaced, HousingStatus::Owned][random_int(0, 3)]->value,
            'association_id' => $this->associationByCountry[$countryId] ?? null,
            'care_arrangement' => CareArrangement::fromGuardianRelation($relation)?->value,
            'care_attested_at' => $attested,
            'care_attested_by' => $this->adminId ?: null,
            'care_review_due_at' => $attested->copy()->addMonths(Family::CARE_REVIEW_MONTHS)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeDocuments(int $orphanId): void
    {
        $types = [DocumentType::BirthCertificate, DocumentType::DeathCertificate, DocumentType::GuardianshipProof];
        $rows = [];

        foreach ($types as $type) {
            $rows[] = [
                'orphan_id' => $orphanId,
                'type' => $type->value,
                'path' => "documents/{$orphanId}/{$type->value}.pdf",
                'original_name' => $type->value.'.pdf',
                'mime' => 'application/pdf',
                'size_bytes' => random_int(80_000, 900_000),
                'uploaded_by' => null,
                'verified_at' => now()->subDays(random_int(10, 400)),
                'verified_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('orphan_documents')->insert($rows);
    }

    /* ==================================================================== */

    /** @return array<int, int> */
    private function sponsors(): array
    {
        $donorCountries = Country::where('is_operational', false)->pluck('id')->all();
        $ids = [];

        // كافل تجريبي بحساب معروف — هو عبدالله المطيري في لوحة الكافل الحالية
        $demo = User::updateOrCreate(['email' => 'sponsor@lamma.org'], [
            'name' => 'عبدالله المطيري',
            'password' => Hash::make('password'),
            'phone' => '+966500000001',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $demo->syncRoles(UserRole::Sponsor->value);
        $ids[] = Sponsor::updateOrCreate(['user_id' => $demo->id], [
            'sponsor_code' => 'SPN-00001',
            'country_id' => $donorCountries[0] ?? null,
            'city' => 'الرياض',
            'preferred_currency' => 'USD',
            'prefers_zakat' => false,
            'communication_prefs' => ['email' => true, 'sms' => false, 'whatsapp' => true],
            'sponsoring_since' => now()->subYears(3),
        ])->id;

        for ($i = 2; $i <= 180; $i++) {
            $gender = random_int(0, 1) === 1;
            $name = ($gender ? self::FIRST_NAMES_MALE[array_rand(self::FIRST_NAMES_MALE)] : self::FIRST_NAMES_FEMALE[array_rand(self::FIRST_NAMES_FEMALE)])
                .' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)];

            $user = User::create([
                'name' => $name,
                'email' => "sponsor{$i}@example.com",
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now()->subDays(random_int(1, 900)),
            ]);
            $user->syncRoles(UserRole::Sponsor->value);

            $ids[] = Sponsor::create([
                'user_id' => $user->id,
                'sponsor_code' => 'SPN-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'country_id' => $donorCountries[array_rand($donorCountries)],
                'preferred_currency' => ['USD', 'USD', 'USD', 'GBP', 'SAR'][random_int(0, 4)],
                'prefers_zakat' => random_int(0, 100) < 35,
                'is_anonymous' => random_int(0, 100) < 12,
                'communication_prefs' => ['email' => true, 'sms' => random_int(0, 1) === 1],
                // آخر ثلاثة سجّلوا هذا الأسبوع: «كفلاء جدد» في «منذ آخر دخول»
                'sponsoring_since' => $i > 177 ? now()->subDays(random_int(0, 4)) : now()->subDays(random_int(20, 1600)),
            ])->id;
        }

        // تاريخ التسجيل تاريخ الكفالة الأولى، لا لحظة البذر: وإلا بدا الكفلاء
        // كلهم «جدداً» اليوم
        Sponsor::query()->update(['created_at' => DB::raw('sponsoring_since')]);

        return $ids;
    }

    /* ==================================================================== */

    /**
     * الكفالات وما يتبعها من مدفوعات شهرية.
     *
     * @param  array<int, array{id:int, need:float, funded:int, country:int, program:int}>  $orphans
     * @param  array<int, int>  $sponsorIds
     */
    private function sponsorshipsAndPayments(array $orphans, array $sponsorIds): void
    {
        $rates = ['USD' => 1.0, 'GBP' => 1.27, 'SAR' => 0.267];
        $used = [];              // sponsor_id => [orphan_id => true] لاحترام القيد الفريد
        $sponsorships = [];
        $counter = 0;

        /**
         * الكافل التجريبي (SPN-00001) يكفل أحمد وأمنية تحديداً — وهما الطفلان
         * المعروضان في dashboard.html اليوم. تثبيتهما هنا يجعل بوابة الكافل
         * تعرض المحتوى نفسه الذي اعتاده الموقع، بدل لوحة فارغة تعتمد على
         * الاختيار العشوائي أن يقع عليه.
         */
        $demoSponsorId = $sponsorIds[0];
        $demoTargets = ['KFL-1043' => 65.0, 'KFL-1042' => 35.0];

        foreach ($orphans as $orphan) {
            $code = DB::table('orphans')->where('id', $orphan['id'])->value('reference_code');

            if (isset($demoTargets[$code])) {
                $used[$demoSponsorId][$orphan['id']] = true;
                $day = $code === 'KFL-1043' ? 25 : 1;
                $startedAt = Carbon::now()->startOfMonth()->subMonthsNoOverflow(random_int(14, 22));

                $sponsorships[] = [
                    'reference_code' => 'KFS-2026-'.str_pad((string) ++$counter, 5, '0', STR_PAD_LEFT),
                    'sponsor_id' => $demoSponsorId,
                    'orphan_id' => $orphan['id'],
                    'program_id' => $orphan['program'],
                    'monthly_amount' => min($demoTargets[$code], $orphan['need'] * $orphan['funded'] / 100),
                    'currency' => 'USD',
                    'share_percent' => min(100, $orphan['funded']),
                    'is_zakat' => false,
                    'status' => SponsorshipStatus::Active->value,
                    'billing_day' => $day,
                    'started_at' => $startedAt->toDateString(),
                    'next_due_at' => Carbon::now()->addMonthNoOverflow()->setDay($day)->toDateString(),
                    'ended_at' => null,
                    'cancellation_reason' => null,
                    'created_at' => $startedAt,
                    'updated_at' => now(),
                ];

                continue;
            }

            if ($orphan['funded'] <= 0) {
                continue;
            }

            // كفالة واحدة تغطي النسبة، أو اثنتان تتقاسمانها
            $splits = $orphan['funded'] >= 90 && random_int(0, 100) < 30
                ? [(int) round($orphan['funded'] / 2), $orphan['funded'] - (int) round($orphan['funded'] / 2)]
                : [$orphan['funded']];

            foreach ($splits as $share) {
                $sponsorId = $sponsorIds[array_rand($sponsorIds)];
                if (isset($used[$sponsorId][$orphan['id']])) {
                    continue;
                }
                $used[$sponsorId][$orphan['id']] = true;

                $startedAt = Carbon::now()->startOfMonth()->subMonthsNoOverflow(random_int(1, self::MONTHS_OF_HISTORY));
                $billingDay = random_int(1, 28);
                $amount = round($orphan['need'] * $share / 100, 2);

                if ($amount < 5) {
                    continue;
                }

                $status = random_int(0, 100) < 92 ? SponsorshipStatus::Active : SponsorshipStatus::Paused;

                $sponsorships[] = [
                    'reference_code' => 'KFS-2026-'.str_pad((string) ++$counter, 5, '0', STR_PAD_LEFT),
                    'sponsor_id' => $sponsorId,
                    'orphan_id' => $orphan['id'],
                    'program_id' => $orphan['program'],
                    'monthly_amount' => $amount,
                    'currency' => 'USD',
                    'share_percent' => min(100, $share),
                    'is_zakat' => random_int(0, 100) < 35,
                    'status' => $status->value,
                    'billing_day' => $billingDay,
                    'started_at' => $startedAt->toDateString(),
                    'next_due_at' => Carbon::now()->addMonthNoOverflow()->setDay($billingDay)->toDateString(),

                    // مفاتيح موحّدة عبر كل الصفوف: insert() بالجملة يبني قائمة
                    // الأعمدة من الصف الأول، فاختلاف المفاتيح يكسر الإدراج
                    'ended_at' => null,
                    'cancellation_reason' => null,

                    'created_at' => $startedAt,
                    'updated_at' => now(),
                ];
            }
        }

        // كفالات ملغاة — لتُظهر التحليلات معدّل الانقطاع، ولتثبت أن القيد
        // الجزئي يسمح بعودة الكافل نفسه للطفل نفسه بعد الإلغاء
        $cancelled = 0;
        foreach (array_slice($orphans, 0, 40) as $orphan) {
            $sponsorId = $sponsorIds[array_rand($sponsorIds)];
            $started = Carbon::now()->startOfMonth()->subMonthsNoOverflow(random_int(12, self::MONTHS_OF_HISTORY));

            $sponsorships[] = [
                'reference_code' => 'KFS-2025-'.str_pad((string) ++$cancelled, 5, '0', STR_PAD_LEFT),
                'sponsor_id' => $sponsorId,
                'orphan_id' => $orphan['id'],
                'program_id' => $orphan['program'],
                'monthly_amount' => round($orphan['need'] / 2, 2),
                'currency' => 'USD',
                'share_percent' => 50,
                'is_zakat' => false,
                'status' => SponsorshipStatus::Cancelled->value,
                'billing_day' => random_int(1, 28),
                'started_at' => $started->toDateString(),
                'next_due_at' => null,
                'ended_at' => $started->copy()->addMonths(random_int(2, 9)),
                'cancellation_reason' => ['تغيّر الظروف المالية', 'تحوّل إلى حالة أخرى', 'بلا سبب مذكور'][random_int(0, 2)],
                'created_at' => $started,
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($sponsorships, 200) as $chunk) {
            DB::table('sponsorships')->insert($chunk);
        }

        $this->payments($rates);
    }

    /** @param array<string, float> $rates */
    private function payments(array $rates): void
    {
        $today = Carbon::now();
        $counter = 0;

        DB::table('sponsorships')
            ->whereIn('status', [SponsorshipStatus::Active->value, SponsorshipStatus::Cancelled->value])
            ->orderBy('id')
            ->chunk(200, function ($chunk) use (&$counter, $today, $rates): void {
                $rows = [];

                foreach ($chunk as $s) {
                    $cursor = Carbon::parse($s->started_at)->setDay(min((int) $s->billing_day, 28));
                    $end = $s->ended_at ? Carbon::parse($s->ended_at) : $today;

                    while ($cursor->lte($end)) {
                        $currency = 'USD';
                        $rate = $rates[$currency];
                        $failed = random_int(0, 100) < 4;

                        $rows[] = [
                            'reference_code' => 'PAY-'.str_pad((string) ++$counter, 7, '0', STR_PAD_LEFT),
                            'sponsorship_id' => $s->id,
                            'sponsor_id' => $s->sponsor_id,
                            'amount' => $s->monthly_amount,
                            'currency' => $currency,
                            'fx_rate' => $rate,
                            'amount_base' => round((float) $s->monthly_amount * $rate, 2),
                            'method' => [PaymentMethod::Card, PaymentMethod::Card, PaymentMethod::ApplePay, PaymentMethod::BankTransfer][random_int(0, 3)]->value,
                            'gateway' => 'demo-gateway',
                            'gateway_reference' => 'ch_'.bin2hex(random_bytes(8)),
                            'status' => $failed ? PaymentStatus::Failed->value : PaymentStatus::Succeeded->value,
                            'card_brand' => 'فيزا',
                            'card_last4' => (string) random_int(1000, 9999),
                            'paid_at' => $failed ? null : $cursor->copy()->addHours(random_int(0, 20)),
                            'failure_reason' => $failed ? 'رصيد غير كافٍ' : null,
                            'receipt_number' => $failed ? null : 'RCP-'.str_pad((string) $counter, 7, '0', STR_PAD_LEFT),
                            'idempotency_key' => hash('sha256', "{$s->id}-{$cursor->format('Y-m')}"),

                            /**
                             * `copy()` لا `$cursor` مباشرةً.
                             *
                             * Carbon كائن قابل للتغيير، و`$cursor->addMonthNoOverflow()`
                             * في نهاية الحلقة يعدّل الكائن نفسه الذي تشير إليه كل الصفوف
                             * السابقة — فتنتهي كل الدفعات بتاريخ إنشاء واحد هو آخر قيمة
                             * وصلها المؤشر، ويخرج كشف الحساب غير مرتّب.
                             */
                            'created_at' => $cursor->copy(),
                            'updated_at' => $cursor->copy(),
                        ];

                        $cursor->addMonthNoOverflow();
                    }
                }

                foreach (array_chunk($rows, 500) as $part) {
                    DB::table('payments')->insert($part);
                }
            });

        /*
         * من دفع آخر قسطٍ ببطاقة له بطاقةٌ محفوظة عند البوابة التجريبية،
         * فيخصمها billing:run كما يخصم بطاقة من كفل من الموقع. وغيره (تحويل)
         * يبقى بلا بطاقة، يتابعه المحاسب.
         */
        $cards = DB::table('payments as p')
            ->join('sponsorships as s', 's.id', '=', 'p.sponsorship_id')
            ->where('s.status', SponsorshipStatus::Active->value)
            ->where('p.status', PaymentStatus::Succeeded->value)
            ->whereIn('p.method', [PaymentMethod::Card->value, PaymentMethod::ApplePay->value])
            ->whereRaw('p.paid_at = (select max(paid_at) from payments where sponsorship_id = s.id and status = ?)', [PaymentStatus::Succeeded->value])
            ->get(['s.id', 'p.card_brand', 'p.card_last4']);

        foreach ($cards as $card) {
            DB::table('sponsorships')->where('id', $card->id)->update([
                'payment_method' => PaymentMethod::Card->value,
                'gateway_payment_method' => 'pm_demo_seed_'.$card->id,
                'card_brand' => $card->card_brand,
                'card_last4' => $card->card_last4,
            ]);
        }
    }

    /* ==================================================================== */

    /**
     * التحويلات الشهرية للجمعيات، من الدفعات المزروعة نفسها: كل شهرٍ مكتمل
     * يُجهَّز كما يجهّزه الأمر المجدول، والأقدم وصل، والشهر الماضي أُرسل وينتظر
     * تأكيد الجمعية. فيقرأ الكافل في «أين ذهب مالك» سجلاً كما سيكون فعلاً.
     *
     * @param  array{admin:int, accountant:int, supervisors:array<int,int>}  $staff
     */
    private function transfers(array $staff): void
    {
        $preparer = app(TransferPreparer::class);
        $lastMonth = Carbon::now()->startOfMonth()->subMonthNoOverflow();

        for ($m = self::MONTHS_OF_HISTORY; $m >= 1; $m--) {
            $preparer->prepare(Carbon::now()->startOfMonth()->subMonthsNoOverflow($m));
        }

        AssociationTransfer::query()->where('status', TransferStatus::Draft->value)->where('amount_base', '>', 0)->get()
            ->each(function (AssociationTransfer $t) use ($staff, $lastMonth): void {
                $sentAt = $t->period->copy()->addMonthNoOverflow()->setDay(random_int(2, 5))->setTime(random_int(9, 14), random_int(0, 59));
                $arrived = $t->period->lt($lastMonth);

                $t->update([
                    'status' => $arrived ? TransferStatus::Confirmed : TransferStatus::Sent,
                    'bank_reference' => 'SWIFT-'.$t->period->format('ym').'-'.str_pad((string) $t->association_id, 3, '0', STR_PAD_LEFT),
                    'sent_at' => $sentAt,
                    'sent_by' => $staff['accountant'],
                    'confirmed_at' => $arrived ? $sentAt->copy()->addDays(random_int(2, 6)) : null,
                    'confirmed_by' => $arrived ? $staff['accountant'] : null,
                ]);
            });
    }

    /* ==================================================================== */

    /**
     * الصرف — بنسب قريبة من SPENDING في data.js، لكن محسوبة من صفوف حقيقية
     * فتخرج نسب حلقة الشفافية من تجميع لا من رقم مكتوب.
     *
     * @param  array<int, array{id:int, need:float, funded:int, country:int, program:int}>  $orphans
     * @param  array{admin:int, accountant:int, supervisors:array<int,int>}  $staff
     */
    private function disbursements(array $orphans, array $staff): void
    {
        $categories = DisbursementCategory::pluck('id', 'slug')->all();
        $weights = ['living' => 38, 'education' => 27, 'health' => 19, 'psychosocial' => 9];
        $rows = [];

        foreach ($orphans as $orphan) {
            if ($orphan['funded'] <= 0) {
                continue;
            }

            $months = random_int(3, 18);
            for ($m = 0; $m < $months; $m++) {
                // startOfMonth قبل الطرح: من ٢٩ أغسطس، subMonths(6) يعطي ٢٩ فبراير
                // غير الموجود فينزلق إلى مارس، فيخرج شهر فارغ وآخر مضاعف في الرسم
                // الشهر الجاري حتى اليوم وحده: صرفٌ بتاريخ لم يأتِ بعد كان يدخل
                // «مصروف الشهر» وأرقام الشفافية كأنه وقع
                $lastDay = $m === 0 ? max(1, min(28, (int) now()->day)) : 28;
                $spentOn = Carbon::now()->startOfMonth()->subMonthsNoOverflow($m)->setDay(random_int(1, $lastDay));
                $budget = $orphan['need'] * $orphan['funded'] / 100;

                foreach ($weights as $slug => $weight) {
                    $amount = round($budget * $weight / 100 * (random_int(85, 115) / 100), 2);
                    if ($amount < 1) {
                        continue;
                    }

                    $rows[] = [
                        'orphan_id' => $orphan['id'],
                        'category_id' => $categories[$slug],
                        'amount' => $amount,
                        'currency' => 'USD',
                        'amount_base' => $amount,
                        'spent_on' => $spentOn->toDateString(),
                        'description' => null,
                        // المحاسب يسجّل والمدير يعتمد: المشرف لا يملك صلاحية تسجيل الصرف،
                        // والعينان اثنتان كما تفرضهما DisbursementPolicy
                        'recorded_by' => $staff['accountant'],
                        'approved_by' => $staff['admin'],
                        'approved_at' => $spentOn->copy()->addDays(random_int(1, 6))->min(now()),
                        'created_at' => $spentOn,
                        'updated_at' => $spentOn,
                    ];
                }
            }
        }

        /**
         * المصاريف الإدارية: صفّ شهري عام لا يخصّ طفلاً بعينه.
         *
         * تُشتق من الإنفاق الفعلي على الأطفال في الشهر نفسه لا تُكتب رقماً ثابتاً،
         * وإلا خرجت النسبة مناقضةً لما يعلنه الموقع. النسبة المعلنة ٧٪ من الإجمالي،
         * أي 7/93 من الإنفاق على الأطفال.
         */
        $childSpendByMonth = [];
        foreach ($rows as $row) {
            $month = substr($row['spent_on'], 0, 7);
            $childSpendByMonth[$month] = ($childSpendByMonth[$month] ?? 0) + $row['amount'];
        }

        foreach ($childSpendByMonth as $month => $childSpend) {
            // آخر الشهر، أو اليوم إن كان الشهر الجاري
            $spentOn = Carbon::parse($month.'-01')->endOfMonth()->startOfDay()->min(now()->startOfDay());
            $amount = round($childSpend * 7 / 93 * (random_int(92, 108) / 100), 2);

            $rows[] = [
                'orphan_id' => null,
                'category_id' => $categories['overhead'],
                'amount' => $amount,
                'currency' => 'USD',
                'amount_base' => $amount,
                'spent_on' => $spentOn->toDateString(),
                'description' => 'مصاريف تشغيل شهرية',
                'recorded_by' => $staff['accountant'],
                'approved_by' => $staff['admin'],
                'approved_at' => $spentOn->copy()->addDays(2)->min(now()),
                'created_at' => $spentOn,
                'updated_at' => $spentOn,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('disbursements')->insert($chunk);
        }
    }

    /* ==================================================================== */

    /**
     * @param  array<int, array{id:int, need:float, funded:int, country:int, program:int}>  $orphans
     * @param  array{admin:int, accountant:int, supervisors:array<int,int>}  $staff
     * @param  array<int, int>  $sponsorIds
     */
    private function reportsAndVisits(array $orphans, array $staff, array $sponsorIds): void
    {
        $reports = [];
        $visits = [];
        $seen = [];

        foreach ($orphans as $orphan) {
            if ($orphan['funded'] <= 0) {
                continue;
            }

            // تقارير آخر أربعة أرباع — والقيد الفريد يمنع التكرار
            for ($q = 0; $q < random_int(1, 4); $q++) {
                $when = Carbon::now()->subMonthsNoOverflow(3 * $q);
                $key = $orphan['id'].'-'.$when->year.'-'.$when->quarter;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $published = $q > 0 || random_int(0, 100) < 70;

                $reports[] = [
                    'orphan_id' => $orphan['id'],
                    'period_year' => $when->year,
                    'period_quarter' => $when->quarter,
                    'title' => "تقرير الربع {$when->quarter} لعام {$when->year}",
                    'summary' => 'زيارة ميدانية موثّقة، ومتابعة دراسية وصحية ونفسية.',
                    'academic_note' => 'معدّل '.random_int(62, 97).'٪، حضور منتظم.',
                    'health_note' => random_int(0, 100) < 80 ? 'فحص دوري بلا ملاحظات.' : 'يحتاج متابعة تغذية.',
                    'psychosocial_note' => 'اندماج جيد مع أقرانه.',
                    'spending_breakdown' => json_encode([
                        'living' => 38, 'education' => 27, 'health' => 19, 'psychosocial' => 9, 'overhead' => 7,
                    ], JSON_UNESCAPED_UNICODE),
                    'visit_date' => $when->copy()->subDays(random_int(3, 25))->toDateString(),
                    'supervisor_id' => $staff['supervisors'][array_rand($staff['supervisors'])],
                    'status' => $published ? ReportStatus::Published->value : ReportStatus::Submitted->value,
                    'approved_by' => $published ? $staff['admin'] : null,
                    'published_at' => $published ? $when->copy()->subDays(random_int(0, 5)) : null,
                    'created_at' => $when->copy()->subDays(random_int(5, 30)),
                    'updated_at' => $when,
                ];
            }

            for ($v = 0; $v < random_int(1, 6); $v++) {
                $when = Carbon::now()->subMonthsNoOverflow($v * 2)->subDays(random_int(0, 20));
                $visits[] = [
                    'orphan_id' => $orphan['id'],
                    'supervisor_id' => $staff['supervisors'][array_rand($staff['supervisors'])],
                    'visited_at' => $when,
                    'findings' => 'الوضع مستقر، والاحتياجات موثّقة في التقرير الربعي.',
                    'needs_flagged' => json_encode(
                        random_int(0, 100) < 30 ? [['ملابس شتوية', 'تعثّر دراسي', 'حاجة صحية', 'دعم نفسي', 'مواصلات'][random_int(0, 4)]] : [],
                        JSON_UNESCAPED_UNICODE,
                    ),
                    'next_visit_due' => $when->copy()->addMonths(2)->toDateString(),
                    'created_at' => $when,
                    'updated_at' => $when,
                ];
            }
        }

        foreach (array_chunk($reports, 400) as $chunk) {
            DB::table('reports')->insert($chunk);
        }
        foreach (array_chunk($visits, 400) as $chunk) {
            DB::table('field_visits')->insert($chunk);
        }

        $this->reportViews();
        $this->messages($sponsorIds);
    }

    /** بعض التقارير مقروءة وبعضها لا — فتظهر شارة «جديد» بمعنى حقيقي. */
    private function reportViews(): void
    {
        $rows = [];

        DB::table('reports')
            ->join('sponsorships', 'sponsorships.orphan_id', '=', 'reports.orphan_id')
            ->where('reports.status', ReportStatus::Published->value)
            ->select('reports.id as report_id', 'sponsorships.sponsor_id')
            ->distinct()
            ->orderBy('reports.id')
            ->chunk(500, function ($chunk) use (&$rows): void {
                foreach ($chunk as $row) {
                    if (random_int(0, 100) < 30) {
                        continue;                     // لم يفتحه بعد
                    }
                    $rows[] = [
                        'report_id' => $row->report_id,
                        'sponsor_id' => $row->sponsor_id,
                        'viewed_at' => Carbon::now()->subDays(random_int(1, 200)),
                    ];
                }
            });

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('report_views')->insertOrIgnore($chunk);
        }
    }

    /** @param array<int, int> $sponsorIds */
    private function messages(array $sponsorIds): void
    {
        $rows = [];

        DB::table('sponsorships')
            ->where('status', SponsorshipStatus::Active->value)
            ->inRandomOrder()
            ->limit(120)
            ->pluck('id')
            ->each(function (int $id) use (&$rows): void {
                $when = Carbon::now()->subDays(random_int(1, 300));
                $status = [MessageStatus::PendingReview, MessageStatus::Approved, MessageStatus::Delivered, MessageStatus::Delivered][random_int(0, 3)];

                $rows[] = [
                    'sponsorship_id' => $id,
                    'sender_type' => random_int(0, 1) === 1 ? 'sponsor' : 'orphan',
                    'body' => 'رسالة قصيرة ضمن المراسلة المسموح بها عبر المشرف الميداني.',
                    'status' => $status->value,
                    'reviewed_at' => $status === MessageStatus::PendingReview ? null : $when->copy()->addDays(1),
                    'created_at' => $when,
                    'updated_at' => $when,
                ];
            });

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('messages')->insert($chunk);
        }
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\DisbursementCategory;
use App\Models\Program;
use App\Models\ProgramFeature;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * البيانات المرجعية — منقولة حرفياً من web/assets/js/modules/data.js
 * حتى يبقى الموقع العام معروضاً بالمحتوى نفسه بعد ربطه بالـ API.
 */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->countries();
        $this->programs();
        $this->disbursementCategories();
        $this->settings();
    }

    /** COUNTRIES في data.js — أول ثمانٍ منها تشغيلية. */
    private function countries(): void
    {
        $rows = [
            ['PS', 'PSE', 'فلسطين', 'Palestine', '🇵🇸', 'ILS', true, ['غزة', 'الخليل', 'رفح', 'خان يونس', 'نابلس']],
            ['YE', 'YEM', 'اليمن', 'Yemen', '🇾🇪', 'YER', true, ['صنعاء', 'تعز', 'عدن', 'الحديدة']],
            ['SY', 'SYR', 'سوريا', 'Syria', '🇸🇾', 'SYP', true, ['إدلب', 'حلب', 'حمص', 'درعا']],
            ['IQ', 'IRQ', 'العراق', 'Iraq', '🇮🇶', 'IQD', true, ['الموصل', 'بغداد', 'البصرة']],
            ['SD', 'SDN', 'السودان', 'Sudan', '🇸🇩', 'SDG', true, ['أم درمان', 'الخرطوم', 'بورتسودان']],
            ['LB', 'LBN', 'لبنان', 'Lebanon', '🇱🇧', 'LBP', true, ['طرابلس', 'بيروت', 'صيدا']],
            ['SO', 'SOM', 'الصومال', 'Somalia', '🇸🇴', 'SOS', true, ['مقديشو', 'هرجيسا']],
            ['JO', 'JOR', 'الأردن', 'Jordan', '🇯🇴', 'JOD', true, ['عمّان', 'الزرقاء', 'إربد']],
            ['EG', 'EGY', 'مصر', 'Egypt', '🇪🇬', 'EGP', true, ['القاهرة', 'الإسكندرية']],
            ['MR', 'MRT', 'موريتانيا', 'Mauritania', '🇲🇷', 'MRU', true, ['نواكشوط']],
            ['AF', 'AFG', 'أفغانستان', 'Afghanistan', '🇦🇫', 'AFN', true, ['كابول', 'قندهار']],
            ['BD', 'BGD', 'بنغلاديش', 'Bangladesh', '🇧🇩', 'BDT', true, ['دكا', 'كوكس بازار']],

            // دول الكفلاء — ليست تشغيلية، لكنها لازمة لعناوينهم
            ['SA', 'SAU', 'السعودية', 'Saudi Arabia', '🇸🇦', 'SAR', false, ['الرياض', 'جدة', 'الدمام']],
            ['AE', 'ARE', 'الإمارات', 'United Arab Emirates', '🇦🇪', 'AED', false, ['دبي', 'أبوظبي']],
            ['KW', 'KWT', 'الكويت', 'Kuwait', '🇰🇼', 'KWD', false, ['الكويت']],
            ['QA', 'QAT', 'قطر', 'Qatar', '🇶🇦', 'QAR', false, ['الدوحة']],
            ['GB', 'GBR', 'المملكة المتحدة', 'United Kingdom', '🇬🇧', 'GBP', false, ['لندن', 'مانشستر']],
            ['US', 'USA', 'الولايات المتحدة', 'United States', '🇺🇸', 'USD', false, ['نيويورك', 'شيكاغو']],
        ];

        foreach ($rows as $i => [$iso2, $iso3, $ar, $en, $flag, $currency, $operational, $cities]) {
            $country = Country::updateOrCreate(
                ['iso2' => $iso2],
                [
                    'iso3' => $iso3,
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'flag_emoji' => $flag,
                    'currency_code' => $currency,
                    'is_operational' => $operational,
                    'sort_order' => $i,
                ],
            );

            foreach ($cities as $city) {
                City::updateOrCreate(
                    ['country_id' => $country->id, 'name_ar' => $city],
                    [],
                );
            }
        }
    }

    /** PROGRAMS في data.js — الأسعار كما هي معروضة على الموقع اليوم. */
    private function programs(): void
    {
        $rows = [
            [
                'comprehensive', 'الكفالة الشاملة', 'Comprehensive Sponsorship',
                'fa-hands-holding-child', 'var(--gradient-brand)', 65.00, true,
                'رعاية كاملة تغطي كل جوانب حياة اليتيم: المعيشة والتعليم والصحة والدعم النفسي.',
                ['غذاء وكسوة شهرية', 'رسوم ومستلزمات دراسية', 'تأمين صحي ومتابعة طبية', 'دعم نفسي واجتماعي', 'تقرير مصوّر كل ٣ أشهر'],
            ],
            [
                'education', 'الكفالة التعليمية', 'Education Sponsorship',
                'fa-book-open-reader', 'linear-gradient(135deg, var(--teal-700), var(--teal-600))', 35.00, false,
                'تبقي الطفل على مقعد الدراسة: رسوم، كتب، زي، ودروس تقوية عند الحاجة.',
                ['الرسوم الدراسية', 'حقيبة وقرطاسية', 'الزي المدرسي', 'دروس تقوية', 'تقرير أكاديمي فصلي'],
            ],
            [
                'health', 'الكفالة الصحية', 'Health Sponsorship',
                'fa-heart-pulse', 'linear-gradient(135deg, var(--rose-700), var(--rose-600))', 45.00, false,
                'رعاية طبية لأيتام يواجهون ظروفاً صحية تحتاج متابعة مستمرة أو تدخلاً عاجلاً.',
                ['فحص دوري شامل', 'الأدوية والمستلزمات', 'جلسات علاج طبيعي', 'تغطية العمليات', 'ملف طبي محدّث'],
            ],
            [
                'family', 'كفالة الأسرة', 'Family Sponsorship',
                'fa-house-chimney-user', 'linear-gradient(135deg, var(--olive-600), var(--olive-500))', 90.00, false,
                'دعم الأسرة كاملةً حتى تبقى مجتمعة، فبقاء الطفل بين أهله خيرٌ من أي بديل.',
                ['سلة غذائية شهرية', 'مساهمة في الإيجار', 'مشروع صغير مدرّ للدخل', 'تعليم كل الأبناء', 'زيارة ميدانية شهرية'],
            ],
        ];

        foreach ($rows as $i => [$slug, $ar, $en, $icon, $accent, $price, $featured, $summary, $features]) {
            $program = Program::updateOrCreate(['slug' => $slug], [
                'name_ar' => $ar,
                'name_en' => $en,
                'icon' => $icon,
                'accent' => $accent,
                'monthly_price' => $price,
                'currency' => 'USD',
                'summary_ar' => $summary,
                'is_featured' => $featured,
                'is_active' => true,
                'sort_order' => $i,
            ]);

            $program->features()->delete();
            foreach ($features as $j => $label) {
                ProgramFeature::create([
                    'program_id' => $program->id,
                    'label_ar' => $label,
                    'sort_order' => $j,
                ]);
            }
        }
    }

    /**
     * SPENDING في data.js.
     * كانت نسباً ثابتة؛ صارت بنوداً تُجمَّع منها النسب من صرفٍ معتمَد.
     */
    private function disbursementCategories(): void
    {
        $rows = [
            ['living', 'معيشة وغذاء', 'var(--clay-600)', false],
            ['education', 'تعليم', 'var(--amber-500)', false],
            ['health', 'صحة', 'var(--olive-600)', false],
            ['psychosocial', 'دعم نفسي وأنشطة', 'var(--teal-700)', false],
            ['overhead', 'مصاريف إدارية', 'var(--sand-400)', true],
        ];

        foreach ($rows as $i => [$slug, $name, $color, $isOverhead]) {
            DisbursementCategory::updateOrCreate(['slug' => $slug], [
                'name_ar' => $name,
                'color' => $color,
                'is_overhead' => $isOverhead,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }

    /** SITE في data.js. */
    private function settings(): void
    {
        $rows = [
            ['site', 'name', 'كفالة', 'string'],
            ['site', 'tagline', 'منصة كفالة الأيتام', 'string'],
            ['site', 'email', 'info@kafala.org', 'string'],
            ['site', 'phone', '+970 599 000 000', 'string'],
            ['site', 'address', 'غزة، فلسطين', 'string'],
            ['site', 'start_year', '2012', 'number'],
            ['finance', 'base_currency', 'USD', 'string'],
            ['finance', 'overhead_target_percent', '7', 'number'],
            ['policy', 'moderate_messages', 'true', 'boolean'],
            ['policy', 'report_cadence_months', '3', 'number'],
        ];

        foreach ($rows as [$group, $key, $value, $type]) {
            Setting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value, 'type' => $type],
            );
        }
    }
}

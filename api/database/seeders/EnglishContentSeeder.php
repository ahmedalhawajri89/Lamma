<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\DisbursementCategory;
use App\Models\Faq;
use App\Models\Orphan;
use App\Models\Partner;
use App\Models\PostCategory;
use App\Models\Program;
use App\Models\ProgramFeature;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * المحتوى التجريبي بالإنجليزية، للموقع تحت /en.
 *
 * يُترجم ما كتبه DemoSeeder وReferenceSeeder بقاموسٍ من نصوصهما نفسها:
 * أسماء الأطفال (نقحرةً)، وصفوفهم، وصحتهم، وأحلامهم، والقصص المكتوبة باليد،
 * والمدن والبرامج. يُعاد تشغيله بأمان، ولا يمسّ نصاً لا يعرفه: ما لا مقابل
 * له يبقى عربياً، وتعرضه الصفحة كما هو.
 *
 * في منصةٍ حقيقية يكتب الشريك الترجمة أو يراجعها؛ هذا للعرض وحده.
 */
class EnglishContentSeeder extends Seeder
{
    private const NAMES = [
        'أحمد' => 'Ahmad', 'محمد' => 'Mohammed', 'يوسف' => 'Yousef', 'خالد' => 'Khaled', 'سعيد' => 'Saeed',
        'عمر' => 'Omar', 'إبراهيم' => 'Ibrahim', 'مصطفى' => 'Mustafa', 'حمزة' => 'Hamza', 'زياد' => 'Ziad',
        'كرم' => 'Karam', 'أنس' => 'Anas', 'باسل' => 'Basel', 'رامي' => 'Rami', 'طارق' => 'Tareq', 'نور الدين' => 'Nour Al-Din',
        'أمنية' => 'Omnia', 'عائشة' => 'Aisha', 'مريم' => 'Maryam', 'ليان' => 'Layan', 'سارة' => 'Sara', 'هدى' => 'Huda',
        'رزان' => 'Razan', 'جنى' => 'Jana', 'سلمى' => 'Salma', 'لينا' => 'Lina', 'رغد' => 'Raghad', 'دعاء' => 'Duaa',
        'آية' => 'Aya', 'ملك' => 'Malak', 'تالا' => 'Tala', 'شهد' => 'Shahad',
    ];

    private const GRADES = [
        'الروضة' => 'Kindergarten', 'تمهيدي' => 'Pre-school', 'الصف الأول' => 'Grade 1', 'الصف الثاني' => 'Grade 2',
        'الصف الثالث' => 'Grade 3', 'الصف الرابع' => 'Grade 4', 'الصف الخامس' => 'Grade 5', 'الصف السادس' => 'Grade 6',
        'الصف السابع' => 'Grade 7', 'الصف الثامن' => 'Grade 8',
    ];

    private const HEALTH = [
        'سليمة' => 'Healthy', 'سليم' => 'Healthy', 'يحتاج متابعة دورية' => 'Needs regular check-ups',
        'يحتاج متابعة تغذية' => 'Needs nutrition follow-up', 'تحتاج جلسات علاج طبيعي' => 'Needs physiotherapy sessions',
        'يعاني من ضعف نظر شديد' => 'Severe short-sightedness',
    ];

    private const DREAMS = [
        'أن تصبح معلمة' => 'To become a teacher', 'أن يصبح طبيباً' => 'To become a doctor', 'أن ترسم كل يوم' => 'To draw every day',
        'أن يصبح مهندساً' => 'To become an engineer', 'أن تدرس الصيدلة' => 'To study pharmacy',
        'أن يلعب في نادٍ للكرة' => 'To play for a football club', 'أن تحفظ القرآن' => 'To memorise the Qur\'an',
        'أن يقرأ بلا نظارة مكسورة' => 'To read without broken glasses', 'أن يصير طياراً' => 'To become a pilot',
        'أن تكتب قصة' => 'To write a story',
    ];

    /** القصص المكتوبة باليد في DemoSeeder، برمز الطفل */
    private const STORIES = [
        'KFL-1042' => 'Omnia lost her father when their neighbourhood was shelled, and moved with her mother and two brothers into a relative\'s home. She has not missed a day of school since, and keeps a notebook of what she wants to teach her own pupils one day.',
        'KFL-1043' => 'After his parents died, Ahmad looks after himself and his little sister with help from their elderly grandmother. He walks an hour to school every morning, and says he wants to study medicine to treat the people of his village.',
        'KFL-1044' => 'Aisha injured her leg when she was three and needs regular physiotherapy to walk without pain again. Every day she draws a house with a garden, and says it is their old home.',
        'KFL-1045' => 'Saeed loves taking old appliances apart and putting them back together. He fixed his neighbours\' fan by himself, and dreams of building his mother a new house with his own hands.',
        'KFL-1046' => 'Maryam missed a whole year of school when her family was displaced, then came back and finished top of her class. She knows the names of the medicinal plants her grandmother used, and wants to study pharmacy.',
        'KFL-1047' => 'Yousef is the youngest of five children who lost their father in a work accident. His mother sews at home, but it isn\'t enough; family sponsorship keeps them together under one roof.',
        'KFL-1048' => 'Layan is too young to understand what happened, but she asks for her father every night. She knows short surahs by heart and recites them out loud; her mother says her voice is what keeps her going.',
        'KFL-1049' => 'Khaled sits in the front row because he can\'t see the board from further back, and his only glasses are broken at one side. An eye test and simple treatment could change his whole education.',
    ];

    private const GENERIC_STORY = 'حالة موثّقة ميدانياً ضمن برنامج الكفالة، تفاصيلها في ملف التحقق.';

    private const CITIES = [
        'غزة' => 'Gaza', 'الخليل' => 'Hebron', 'رفح' => 'Rafah', 'خان يونس' => 'Khan Younis', 'نابلس' => 'Nablus',
        'صنعاء' => 'Sanaa', 'تعز' => 'Taiz', 'عدن' => 'Aden', 'الحديدة' => 'Hodeidah', 'إدلب' => 'Idlib', 'حلب' => 'Aleppo',
        'حمص' => 'Homs', 'درعا' => 'Daraa', 'الموصل' => 'Mosul', 'بغداد' => 'Baghdad', 'البصرة' => 'Basra',
        'أم درمان' => 'Omdurman', 'الخرطوم' => 'Khartoum', 'بورتسودان' => 'Port Sudan', 'طرابلس' => 'Tripoli',
        'بيروت' => 'Beirut', 'صيدا' => 'Sidon', 'مقديشو' => 'Mogadishu', 'هرجيسا' => 'Hargeisa', 'عمّان' => 'Amman',
        'الزرقاء' => 'Zarqa', 'إربد' => 'Irbid', 'القاهرة' => 'Cairo', 'الإسكندرية' => 'Alexandria', 'نواكشوط' => 'Nouakchott',
        'كابول' => 'Kabul', 'قندهار' => 'Kandahar', 'دكا' => 'Dhaka', 'كوكس بازار' => 'Cox\'s Bazar', 'الرياض' => 'Riyadh',
        'جدة' => 'Jeddah', 'الدمام' => 'Dammam', 'دبي' => 'Dubai', 'أبوظبي' => 'Abu Dhabi', 'الكويت' => 'Kuwait City',
        'الدوحة' => 'Doha', 'لندن' => 'London', 'مانشستر' => 'Manchester', 'نيويورك' => 'New York', 'شيكاغو' => 'Chicago',
    ];

    private const PROGRAMS = [
        'comprehensive' => 'Full care for every part of an orphan\'s life: daily living, education, health and emotional support.',
        'education' => 'Keeps the child in school: fees, books, uniform, and tutoring when needed.',
        'health' => 'Medical care for orphans with conditions that need ongoing follow-up or urgent treatment.',
        'family' => 'Supports the whole family so they can stay together; a child is better with their family than anywhere else.',
    ];

    private const FEATURES = [
        'غذاء وكسوة شهرية' => 'Monthly food and clothing', 'رسوم ومستلزمات دراسية' => 'School fees and supplies',
        'تأمين صحي ومتابعة طبية' => 'Health insurance and medical follow-up', 'دعم نفسي واجتماعي' => 'Emotional and social support',
        'تقرير مصوّر كل ٣ أشهر' => 'A photo report every 3 months', 'الرسوم الدراسية' => 'School fees',
        'حقيبة وقرطاسية' => 'School bag and stationery', 'الزي المدرسي' => 'School uniform', 'دروس تقوية' => 'Tutoring',
        'تقرير أكاديمي فصلي' => 'Termly school report', 'فحص دوري شامل' => 'Regular full check-ups',
        'الأدوية والمستلزمات' => 'Medicine and supplies', 'جلسات علاج طبيعي' => 'Physiotherapy sessions',
        'تغطية العمليات' => 'Surgery cover', 'ملف طبي محدّث' => 'An up-to-date medical file',
        'سلة غذائية شهرية' => 'Monthly food basket', 'مساهمة في الإيجار' => 'Help with rent',
        'مشروع صغير مدرّ للدخل' => 'A small income-generating project', 'تعليم كل الأبناء' => 'Schooling for all the children',
        'زيارة ميدانية شهرية' => 'A monthly home visit',
    ];

    private const SPENDING = [
        'living' => 'Daily living and food', 'education' => 'Education', 'health' => 'Health',
        'psychosocial' => 'Emotional support and activities', 'overhead' => 'Running costs',
    ];

    private const POST_CATEGORIES = [
        'شفافية' => 'Transparency', 'برامجنا' => 'Our programmes', 'قصص' => 'Stories',
        'حملات' => 'Campaigns', 'فقه' => 'Islamic guidance', 'سياساتنا' => 'Our policies',
    ];

    private const PARTNERS = [
        'وزارة التنمية الاجتماعية' => 'Ministry of Social Development', 'الهلال الأحمر' => 'Red Crescent',
        'شبكة المنظمات الأهلية' => 'NGO Network', 'مدقق حسابات معتمد' => 'Certified auditor',
        'اتحاد الجمعيات الخيرية' => 'Union of Charities', 'برنامج حماية الطفل' => 'Child Protection Programme',
    ];

    /** بالاسم العربي للكاتب: النص، والاسم، والصفة */
    private const TESTIMONIALS = [
        'عبدالله المطيري' => ['Abdullah Al-Mutairi', 'Sponsor for 3 years', 'I thought sponsorship was just an amount taken each month. The first report I got was a video of Ahmad reading something he had written himself. That day I understood what I was part of.'],
        'سارة الحاج' => ['Sara Al-Hajj', 'Sponsors two girls', 'What drew me in was the clarity. I know where every dollar goes, when, and to whom. I have never had to ask or chase anyone for an answer.'],
        'محمد قاسم' => ['Mohammed Qasem', 'Sponsor for 2 years', 'I started with a small education sponsorship because I was a student. After graduating I moved up to full sponsorship. The platform never pushed me; it let me go at my own pace.'],
        'أم عمر' => ['Umm Omar', 'Sponsor for 5 years', 'My daughter sends Maryam a voice message every month. Sponsorship has become a lesson for our whole family, not just a donation.'],
    ];

    /** بالسؤال العربي: السؤال، والجواب ({overhead} و{toChild} تُملأ بالأرقام الحيّة)، والتصنيف */
    private const FAQS = [
        'كيف أتأكد أن كفالتي وصلت فعلاً للطفل؟' => ['How can I be sure my sponsorship actually reaches the child?', 'You get an account that shows your transfer history, plus a photo report every three months on the child\'s schooling, health and how the money was spent. Our audited annual budget is also published on the transparency page.', 'Transparency'],
        'ما نسبة المصاريف الإدارية؟' => ['How much goes on running costs?', 'Running costs this year are {overhead}% of approved spending; the rest ({toChild}%) goes straight to the child and their family. Every line is published on the transparency page and updates with each payment.', 'Transparency'],
        'هل يمكنني التواصل مع الطفل أو زيارته؟' => ['Can I contact or visit the child?', 'Yes, through written or voice messages that pass through a field officer to protect the child\'s privacy. Visits are possible by prior arrangement and under our child-protection policy.', 'Sponsorship'],
        'هل أستطيع إيقاف الكفالة في أي وقت؟' => ['Can I stop my sponsorship at any time?', 'Yes, with one click from your account, with no fees and no phone call. If you can, let us know beforehand so we can find the child another sponsor and their support doesn\'t stop.', 'Sponsorship'],
        'ما وسائل الدفع المتاحة؟' => ['How can I pay?', 'By credit card (Visa and Mastercard), Apple Pay, or direct bank transfer. Every payment goes through an encrypted gateway, and we never store your card details on our servers.', 'Payment'],
        'هل الكفالة تُحتسب من الزكاة؟' => ['Can sponsorship count as zakat?', 'Sponsoring an orphan is general charity (sadaqah). If the child and their family are poor or in need, the sponsorship can also count as zakat. You can mark your payment as zakat at checkout and we will direct it to eligible cases.', 'Islamic guidance'],
    ];

    public function run(): void
    {
        foreach (self::SPENDING as $slug => $en) {
            DisbursementCategory::where('slug', $slug)->update(['name_en' => $en]);
        }
        foreach (self::POST_CATEGORIES as $ar => $en) {
            PostCategory::where('name_ar', $ar)->update(['name_en' => $en]);
        }
        foreach (self::PARTNERS as $ar => $en) {
            self::translate(Partner::where('name', $ar)->get(), ['name' => $en]);
        }
        foreach (self::TESTIMONIALS as $ar => [$name, $role, $quote]) {
            self::translate(Testimonial::where('author_name', $ar)->get(), ['author_name' => $name, 'role' => $role, 'quote' => $quote]);
        }
        foreach (self::FAQS as $ar => [$question, $answer, $category]) {
            self::translate(Faq::where('question', $ar)->get(), ['question' => $question, 'answer' => $answer, 'category' => $category]);
        }

        foreach (self::PROGRAMS as $slug => $summary) {
            Program::where('slug', $slug)->update(['summary_en' => $summary]);
        }
        foreach (self::FEATURES as $ar => $en) {
            ProgramFeature::where('label_ar', $ar)->update(['label_en' => $en]);
        }
        foreach (self::CITIES as $ar => $en) {
            City::where('name_ar', $ar)->whereNull('name_en')->update(['name_en' => $en]);
        }

        Orphan::query()->select(['id', 'reference_code', 'first_name', 'grade_level', 'health_status', 'dream', 'story', 'translations'])
            ->chunkById(200, function ($orphans): void {
                foreach ($orphans as $orphan) {
                    $en = array_filter([
                        'first_name' => self::NAMES[$orphan->first_name] ?? null,
                        'grade_level' => self::GRADES[$orphan->grade_level] ?? null,
                        'health_status' => self::HEALTH[$orphan->health_status] ?? null,
                        'dream' => self::DREAMS[$orphan->dream] ?? null,
                        'story' => self::STORIES[$orphan->reference_code]
                            ?? ($orphan->story === self::GENERIC_STORY ? 'A case verified at home under our sponsorship programme; the details are in the verification file.' : null),
                    ]);

                    if ($en !== []) {
                        self::translate([$orphan], $en);
                    }
                }
            });
    }

    /** يدمج الإنجليزية في translations دون أن يمسّ لغةً أخرى فيه. */
    private static function translate(iterable $models, array $en): void
    {
        foreach ($models as $model) {
            $translations = $model->translations ?? [];
            $translations['en'] = [...($translations['en'] ?? []), ...$en];
            $model->forceFill(['translations' => $translations])->saveQuietly();
        }
    }
}

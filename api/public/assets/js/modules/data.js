/**
 * مصدر البيانات الوحيد للمشروع.
 *
 * كان هذا الملف ثوابت مكتوبة بخط اليد، وصار الآن **بذرةً وطبقة ترطيب معاً**:
 *
 *  · القيم المكتوبة أدناه تبقى كما هي وتعمل بلا خادم إطلاقاً — الموقع منشور
 *    ملفاتٍ ساكنة على Vercel، ويجب أن يعرض محتوى صحيحاً ولو كان الـ API
 *    متوقفاً أو غير منشور بعد.
 *  · و`hydrate()` تستبدلها بما في قاعدة البيانات حين يكون الخادم متاحاً.
 *
 * الصادرات `let` لا `const` عمداً: ارتباطات ES modules حيّة، فتغيير القيمة
 * هنا يراه كل مستورِد فوراً بلا أن تتغيّر سطراً واحداً في الوحدات الأخرى.
 * ولا وحدة تقرأ هذه المصفوفات وقت الاستيراد — كلها تقرؤها داخل دوالها،
 * وهو شرط صحّة هذه الحيلة.
 */

import {
  fetchBootstrap,
  fetchOrphan,
  fetchOrphans,
  fetchPosts,
  fetchTransparency,
} from './api.js';

export let SITE = {
  name: 'كفالة',
  tagline: 'منصة كفالة الأيتام',
  email: 'info@kafala.org',
  phone: '+970 599 000 000',
  phoneHref: '+970599000000',
  address: 'غزة، فلسطين',
  social: [
    { label: 'إكس', icon: 'fa-x-twitter', url: '#' },
    { label: 'إنستغرام', icon: 'fa-instagram', url: '#' },
    { label: 'فيسبوك', icon: 'fa-facebook-f', url: '#' },
    { label: 'يوتيوب', icon: 'fa-youtube', url: '#' },
  ],
};

/**
 * التنقل الرئيسي: خمسة روابط بترتيب نية الزائر، الأيتام أولاً.
 * الشعار يكفي للعودة للرئيسية، والمدونة في الفوتر.
 */
export const NAV_LINKS = [
  { label: 'الأيتام', href: 'orphans.html' },
  { label: 'برامج الكفالة', href: 'programs.html' },
  { label: 'الشفافية', href: 'transparency.html' },
  { label: 'المدونة', href: 'blog.html' },
  { label: 'من نحن', href: 'about.html' },
  { label: 'تواصل معنا', href: 'contact.html' },
];

/** برامج الكفالة — المصدر الوحيد للأسعار في الموقع. */
export let PROGRAMS = [
  {
    id: 'comprehensive',
    name: 'الكفالة الشاملة',
    icon: 'fa-hands-holding-child',
    accent: 'var(--program-full)',
    price: 65,
    summary: 'رعاية كاملة تغطي كل جوانب حياة اليتيم: المعيشة والتعليم والصحة والدعم النفسي.',
    features: ['غذاء وكسوة شهرية', 'رسوم ومستلزمات دراسية', 'تأمين صحي ومتابعة طبية', 'دعم نفسي واجتماعي', 'تقرير مصوّر كل ٣ أشهر'],
    featured: true,
  },
  {
    id: 'education',
    name: 'الكفالة التعليمية',
    icon: 'fa-book-open-reader',
    accent: 'var(--program-education)',
    price: 35,
    summary: 'تبقي الطفل على مقعد الدراسة: رسوم، كتب، زي، ودروس تقوية عند الحاجة.',
    features: ['الرسوم الدراسية', 'حقيبة وقرطاسية', 'الزي المدرسي', 'دروس تقوية', 'تقرير أكاديمي فصلي'],
  },
  {
    id: 'health',
    name: 'الكفالة الصحية',
    icon: 'fa-heart-pulse',
    accent: 'var(--program-health)',
    price: 45,
    summary: 'رعاية طبية لأيتام يواجهون ظروفاً صحية تحتاج متابعة مستمرة أو تدخلاً عاجلاً.',
    features: ['فحص دوري شامل', 'الأدوية والمستلزمات', 'جلسات علاج طبيعي', 'تغطية العمليات', 'ملف طبي محدّث'],
  },
  {
    id: 'family',
    name: 'كفالة الأسرة',
    icon: 'fa-house-chimney-user',
    accent: 'var(--program-family)',
    price: 90,
    summary: 'دعم الأسرة كاملةً حتى تبقى مجتمعة، فبقاء الطفل بين أهله خيرٌ من أي بديل.',
    features: ['سلة غذائية شهرية', 'مساهمة في الإيجار', 'مشروع صغير مدرّ للدخل', 'تعليم كل الأبناء', 'زيارة ميدانية شهرية'],
  },
];

/** الأيتام. حقل `urgent` يرفع الحالة إلى أعلى القائمة. */
export let ORPHANS = [
  {
    id: 'KFL-1042',
    name: 'أمنية',
    age: 8,
    gender: 'أنثى',
    country: 'فلسطين',
    city: 'غزة',
    program: 'education',
    monthly: 35,
    funded: 62,
    urgent: true,
    photo: 'assets/img/orphan-omnia.webp',
    grade: 'الصف الثالث',
    health: 'سليمة',
    dream: 'أن تصبح معلمة',
    story: 'فقدت أمنية والدها في قصف على حيّها، فانتقلت مع أمها وأخويها إلى بيت أقارب. رغم ذلك لم تترك مدرستها يوماً، وتحتفظ بدفتر تكتب فيه ما تودّ تعليمه لطلابها يوماً ما.',
  },
  {
    id: 'KFL-1043',
    name: 'أحمد',
    age: 10,
    gender: 'ذكر',
    country: 'اليمن',
    city: 'صنعاء',
    program: 'comprehensive',
    monthly: 65,
    funded: 24,
    urgent: true,
    photo: 'assets/img/orphan-ahmad.webp',
    grade: 'الصف الخامس',
    health: 'يحتاج متابعة تغذية',
    dream: 'أن يصبح طبيباً',
    story: 'يعيل أحمد نفسه وأخته الصغيرة بمساعدة جدته المسنّة بعد وفاة والديه. يمشي ساعةً كل صباح للوصول إلى مدرسته، ويقول إنه يريد أن يتعلم الطب ليعالج أهل قريته.',
  },
  {
    id: 'KFL-1044',
    name: 'عائشة',
    age: 6,
    gender: 'أنثى',
    country: 'سوريا',
    city: 'إدلب',
    program: 'health',
    monthly: 45,
    funded: 78,
    urgent: false,
    photo: 'assets/img/orphan-aisha.webp',
    grade: 'الروضة',
    health: 'تحتاج جلسات علاج طبيعي',
    dream: 'أن ترسم كل يوم',
    story: 'أصيبت عائشة في ساقها وهي في الثالثة، وتحتاج جلسات علاج طبيعي منتظمة لتستعيد قدرتها على المشي بلا ألم. ترسم كل يوم بيتاً بحديقة، وتقول إنه بيتهم القديم.',
  },
  {
    id: 'KFL-1045',
    name: 'سعيد',
    age: 9,
    gender: 'ذكر',
    country: 'العراق',
    city: 'الموصل',
    program: 'comprehensive',
    monthly: 65,
    funded: 91,
    urgent: false,
    photo: 'assets/img/orphan-saeed.webp',
    grade: 'الصف الرابع',
    health: 'سليم',
    dream: 'أن يصبح مهندساً',
    story: 'يحب سعيد تفكيك الأجهزة القديمة وإعادة تركيبها. أصلح مروحة جيرانه بنفسه، وحلمه أن يبني بيتاً جديداً لأمه بيديه.',
  },
  {
    id: 'KFL-1046',
    name: 'مريم',
    age: 12,
    gender: 'أنثى',
    country: 'السودان',
    city: 'أم درمان',
    program: 'education',
    monthly: 35,
    funded: 40,
    urgent: true,
    photo: 'assets/img/orphan-mariam.webp',
    grade: 'الصف السابع',
    health: 'سليمة',
    dream: 'أن تدرس الصيدلة',
    story: 'انقطعت مريم عن الدراسة عاماً كاملاً بسبب النزوح، ثم عادت وتفوّقت على صفها. تحفظ أسماء النباتات الطبية التي كانت جدتها تستعملها، وتريد أن تدرس الصيدلة.',
  },
  {
    id: 'KFL-1047',
    name: 'يوسف',
    age: 7,
    gender: 'ذكر',
    country: 'فلسطين',
    city: 'الخليل',
    program: 'family',
    monthly: 90,
    funded: 15,
    urgent: true,
    photo: 'assets/img/orphan-yousef.webp',
    grade: 'الصف الثاني',
    health: 'سليم',
    dream: 'أن يلعب في نادٍ للكرة',
    story: 'يوسف أصغر خمسة إخوة فقدوا والدهم في حادث عمل. تعمل أمه بالخياطة في البيت ولا يكفي دخلها، وكفالة الأسرة تبقيهم مجتمعين تحت سقف واحد.',
  },
  {
    id: 'KFL-1048',
    name: 'ليان',
    age: 5,
    gender: 'أنثى',
    country: 'لبنان',
    city: 'طرابلس',
    program: 'comprehensive',
    monthly: 65,
    funded: 55,
    urgent: false,
    photo: 'assets/img/orphan-layan.webp',
    grade: 'تمهيدي',
    health: 'سليمة',
    dream: 'أن تحفظ القرآن',
    story: 'ليان أصغر من أن تفهم ما جرى، لكنها تسأل عن أبيها كل ليلة. تحفظ سوراً قصيرة وترددها بصوت عالٍ، وأمها تقول إن صوتها هو ما يبقيها صامدة.',
  },
  {
    id: 'KFL-1049',
    name: 'خالد',
    age: 11,
    gender: 'ذكر',
    country: 'الصومال',
    city: 'مقديشو',
    program: 'health',
    monthly: 45,
    funded: 33,
    urgent: false,
    photo: 'assets/img/orphan-khaled.webp',
    grade: 'الصف السادس',
    health: 'يعاني من ضعف نظر شديد',
    dream: 'أن يقرأ بلا نظارة مكسورة',
    story: 'يجلس خالد في المقعد الأول لأنه لا يرى اللوح من بعيد، ونظارته الوحيدة مكسورة من طرف. فحص نظر وعلاج بسيط قد يغيّران مسار تعليمه كله.',
  },
];

/** توزيع الإنفاق — تُستعمل في حلقة الشفافية. */
export let SPENDING = [
  { label: 'معيشة وغذاء', value: 38, color: 'var(--chart-1)' },
  { label: 'تعليم', value: 27, color: 'var(--chart-4)' },
  { label: 'صحة', value: 19, color: 'var(--chart-2)' },
  { label: 'دعم نفسي وأنشطة', value: 9, color: 'var(--chart-5)' },
  { label: 'مصاريف إدارية', value: 7, color: 'var(--chart-muted)' },
];

/** ما يوفّره المبلغ الشهري — تعتمد عليه حاسبة الأثر. */
export const IMPACT_TIERS = [
  { min: 15,  icon: 'fa-bowl-food',        title: 'وجبات شهر كامل',       text: 'سلة غذائية أساسية تكفي الطفل شهراً.' },
  { min: 30,  icon: 'fa-book-open-reader', title: 'مقعد دراسي محفوظ',      text: 'رسوم وكتب وقرطاسية تُبقيه في مدرسته.' },
  { min: 45,  icon: 'fa-notes-medical',    title: 'رعاية طبية متابَعة',    text: 'فحص دوري وأدوية عند الحاجة.' },
  { min: 65,  icon: 'fa-shirt',            title: 'كسوة وشتاء دافئ',       text: 'ملابس ومستلزمات موسمية.' },
  { min: 90,  icon: 'fa-house-chimney',    title: 'استقرار الأسرة',        text: 'مساهمة في السكن تُبقي الإخوة معاً.' },
  { min: 120, icon: 'fa-heart-circle-check', title: 'دعم نفسي وأنشطة',     text: 'جلسات إرشاد ورحلات وأنشطة صيفية.' },
];

/** شهادات الكفلاء. */
export let STORIES = [
  {
    quote: 'كنت أظن أن الكفالة مجرد مبلغ يُخصم شهرياً. أول تقرير وصلني كان فيديو لأحمد يقرأ نصاً كتبه بنفسه. يومها فهمت في ماذا أشارك.',
    name: 'عبدالله المطيري',
    role: 'كافل منذ ٣ سنوات',
  },
  {
    quote: 'ما جذبني هو الوضوح. أعرف أين يذهب كل دولار، ومتى، ولمن. لم أضطر يوماً أن أسأل أو أطارد أحداً للحصول على إجابة.',
    name: 'سارة الحاج',
    role: 'كافلة لطفلتين',
  },
  {
    quote: 'بدأت بكفالة تعليمية بمبلغ صغير لأنني طالب. بعد التخرج رفعتها إلى شاملة. الجميل أن المنصة لم تضغط عليّ يوماً، تركتني أتقدّم بوتيرتي.',
    name: 'محمد قاسم',
    role: 'كافل منذ سنتين',
  },
  {
    quote: 'أرسل ابنتي رسالة صوتية لمريم كل شهر. صارت الكفالة درساً تربوياً لأسرتي كلها، لا مجرد تبرع.',
    name: 'أم عمر',
    role: 'كافلة منذ ٥ سنوات',
  },
];

/** الأسئلة الشائعة. */
export let FAQS = [
  {
    q: 'كيف أتأكد أن كفالتي وصلت فعلاً للطفل؟',
    a: 'تحصل على حساب في المنصة يعرض لك سجل التحويلات، وتقريراً مصوّراً كل ثلاثة أشهر يوثّق حالة الطفل الدراسية والصحية وأوجه صرف الكفالة. كما تُنشر الميزانية السنوية مدققة على صفحة الشفافية.',
  },
  {
    q: 'ما نسبة المصاريف الإدارية؟',
    a: '٧٪ فقط، وهي مغطاة أساساً من داعمين مخصصين لهذا الغرض. الباقي (٩٣٪) يصل للطفل وأسرته مباشرة، وتفصيل ذلك منشور على صفحة الشفافية.',
  },
  {
    q: 'هل يمكنني التواصل مع الطفل أو زيارته؟',
    a: 'نعم، عبر رسائل مكتوبة أو صوتية تمرّ بمشرف ميداني لحماية خصوصية الطفل. الزيارات الميدانية ممكنة بترتيب مسبق ووفق سياسة حماية الطفل المعتمدة لدينا.',
  },
  {
    q: 'هل أستطيع إيقاف الكفالة في أي وقت؟',
    a: 'نعم، ودون أي رسوم أو التزام. نرجو فقط إشعارنا قبل شهر لنؤمّن كافلاً بديلاً فلا ينقطع الدعم عن الطفل فجأة.',
  },
  {
    q: 'ما وسائل الدفع المتاحة؟',
    a: 'البطاقات الائتمانية (فيزا وماستركارد)، Apple Pay، والتحويل البنكي المباشر. كل عمليات الدفع تمر عبر بوابة مشفّرة، ولا نحتفظ ببيانات بطاقتك على خوادمنا إطلاقاً.',
  },
  {
    q: 'هل الكفالة تُحتسب من الزكاة؟',
    a: 'كفالة اليتيم من الصدقة العامة. أما إن كان الطفل وأسرته من الفقراء أو المساكين فيجوز احتساب الكفالة من الزكاة. يمكنك تحديد "من الزكاة" عند الدفع لنوجّه مبلغك للحالات المستحقة زكوياً.',
  },
];

/** مقالات المدونة. */
export let POSTS = [
  {
    title: 'كيف نتحقق من كل حالة قبل نشرها؟',
    category: 'شفافية',
    date: '2026-07-28',
    dateLabel: '٢٨ يوليو ٢٠٢٦',
    readMin: 6,
    excerpt: 'ثلاث مراحل تحقّق ميدانية ووثائقية تمرّ بها كل حالة قبل أن تظهر على المنصة، نشرحها هنا خطوةً بخطوة.',
    image: 'assets/img/post-01.webp',
    featured: true,
  },
  {
    title: 'ماذا يحدث حين يكبر اليتيم على سنّ الكفالة؟',
    category: 'برامجنا',
    date: '2026-07-12',
    dateLabel: '١٢ يوليو ٢٠٢٦',
    readMin: 4,
    excerpt: 'برنامج التمكين المهني يرافق الشاب من الثامنة عشرة حتى أول وظيفة أو مشروع صغير يقف به على قدميه.',
    image: 'assets/img/post-02.webp',
  },
  {
    title: 'قصة مريم: عام انقطاع ثم المركز الأول',
    category: 'قصص',
    date: '2026-06-30',
    dateLabel: '٣٠ يونيو ٢٠٢٦',
    readMin: 5,
    excerpt: 'انقطعت عن الدراسة عاماً كاملاً بسبب النزوح، ثم عادت لتتصدّر صفّها. هذه قصتها كما روتها بنفسها.',
    image: 'assets/img/post-03.webp',
  },
  {
    title: 'حملة الشتاء: ما الذي وصل فعلاً؟',
    category: 'حملات',
    date: '2026-06-15',
    dateLabel: '١٥ يونيو ٢٠٢٦',
    readMin: 3,
    excerpt: 'تقرير ختامي بالأرقام والصور عن حملة الشتاء: ما جُمع، وما صُرف، وكم أسرة وصلها الدفء.',
    image: 'assets/img/post-04.webp',
  },
  {
    title: 'الفرق بين الصدقة والزكاة في كفالة اليتيم',
    category: 'فقه',
    date: '2026-05-22',
    dateLabel: '٢٢ مايو ٢٠٢٦',
    readMin: 7,
    excerpt: 'متى تجوز الكفالة من الزكاة ومتى تكون صدقةً عامة؟ ملخّص مبسّط لأقوال أهل العلم.',
    image: 'assets/img/post-05.webp',
  },
  {
    title: 'لماذا نرفض نشر صور الأطفال الباكية؟',
    category: 'سياساتنا',
    date: '2026-05-04',
    dateLabel: '٤ مايو ٢٠٢٦',
    readMin: 5,
    excerpt: 'كرامة الطفل ليست وسيلة جمع تبرعات. سياستنا في التصوير والنشر، ولماذا نتمسك بها رغم كلفتها.',
    image: 'assets/img/post-06.webp',
  },
];

/** الدول التي نعمل فيها — تُعرض بأسمائها لا كرقم مجرّد. */
export let COUNTRIES = [
  'فلسطين', 'اليمن', 'سوريا', 'العراق', 'السودان', 'لبنان',
  'الصومال', 'الأردن', 'مصر', 'موريتانيا', 'أفغانستان', 'بنغلاديش',
];

/**
 * الميزانية السنوية المنشورة.
 *
 * كانت أرقام صفحة الشفافية مكتوبة في HTML — وهو أسوأ مكان لرقم مكتوب:
 * صفحةٌ عنوانها «لا نطلب منك أن تثق بنا، نطلب أن تتحقق» تعرض رقماً لا
 * سبيل للتحقق منه.
 */
export let ANNUAL = {
  year: new Date().getFullYear(),
  income: 0,
  spent: 0,
  balance: 0,
};

/** لوحة الثقة — أرقام تُرى لا تُقال. */
export let PROOF = {
  sponsored: 3280,      // يتيم مكفول اليوم
  perDot: 40,           // كل نقطة في الشبكة تمثّل هذا العدد
  toChild: 93,          // نسبة ما يصل للطفل مباشرة
  startYear: 2012,      // بداية العمل
  visits: 11420,        // زيارات ميدانية موثّقة العام الماضي
};

/** الشركاء — شريط الثقة. */
export let PARTNERS = [
  { name: 'وزارة التنمية الاجتماعية', icon: 'fa-landmark' },
  { name: 'الهلال الأحمر', icon: 'fa-star-and-crescent' },
  { name: 'شبكة المنظمات الأهلية', icon: 'fa-diagram-project' },
  { name: 'مدقق حسابات معتمد', icon: 'fa-file-shield' },
  { name: 'اتحاد الجمعيات الخيرية', icon: 'fa-handshake-angle' },
  { name: 'برنامج حماية الطفل', icon: 'fa-shield-heart' },
];

/** مساعد: جلب برنامج بالمعرّف. */
export const getProgram = (id) => PROGRAMS.find((p) => p.id === id);


/* =========================================================================
   الترطيب من الـ API
   ========================================================================= */

/** يستبدل قيمة الصادر إن وصلت بيانات صالحة غير فارغة، وإلا يبقيها. */
const take = (incoming, fallback) =>
  Array.isArray(incoming) ? (incoming.length ? incoming : fallback) : (incoming ?? fallback);

/**
 * تُستدعى مرة واحدة من `main.js` قبل الإقلاع.
 *
 * الطلبات متوازية لا متسلسلة: لا واحد منها يعتمد على غيره، والتسلسل كان
 * سيضاعف زمن الانتظار بلا سبب. و`allSettled` لا `all` — إخفاق طلب واحد
 * يجب ألا يُسقط البقية.
 */
export async function hydrate() {
  /**
   * لا نجلب إلا ما تحتاجه الصفحة المعروضة.
   *
   * `bootstrap` وحده لازم في كل صفحة لأن الهيدر والفوتر يُبنيان منه.
   * أما قائمة الأيتام والشفافية والمقالات فلكلٍّ منها محورٌ في الـ DOM،
   * ووجوده هو ما يقرّر جلبها. بلا هذا الشرط كانت لوحة الكافل تجلب مئة
   * حالة وأرقام الشفافية واثني عشر مقالاً لتعرض أربعة أرقام لا تخصّ شيئاً
   * من ذلك — أربعة طلبات مهدورة في كل فتح.
   *
   * وهذا امتداد لقاعدة المشروع نفسها: كل وحدة تفحص وجود عناصرها وتخرج
   * بهدوء إن لم تجدها.
   */
  const has = (selectors) => selectors.some((s) => document.querySelector(s) !== null);

  // صفحة الأيتام (#catalogGrid) تطلب صفحتها بنفسها من الخادم، فلا تحتاج القائمة هنا
  const needsOrphans = has([
    '#homeOrphans', '#orphanDetail', '#similarOrphans', '#checkoutSummary',
  ]);
  const needsTransparency = has(['#spendingDonut', '#spendingLegend', '#financeBoard', '#proofBoard']);
  const needsPosts = has(['#postsGrid']);

  const [bootstrap, orphans, transparency, posts] = (
    await Promise.allSettled([
      fetchBootstrap(),
      needsOrphans ? fetchOrphans() : null,
      needsTransparency ? fetchTransparency() : null,
      needsPosts ? fetchPosts() : null,
    ])
  ).map((r) => (r.status === 'fulfilled' ? r.value : null));

  if (bootstrap) {
    // الهاتف القابل للنقر والحسابات الاجتماعية تبقى من البذرة:
    // ليست في قاعدة البيانات ولا حاجة لأن تكون
    SITE = { ...SITE, ...bootstrap.site };
    PROGRAMS = take(bootstrap.programs, PROGRAMS);
    COUNTRIES = take(bootstrap.countries, COUNTRIES);
    PARTNERS = take(bootstrap.partners, PARTNERS);
    STORIES = take(bootstrap.testimonials, STORIES);
    FAQS = take(bootstrap.faqs, FAQS);
  }

  if (orphans) ORPHANS = take(orphans, ORPHANS);

  /**
   * صفحة الطفل: إن لم يكن بين المحمَّلين (القائمة أول مئة، والفهرس يصفّح كل
   * الأطفال) يُطلب برمزه قبل البناء. كانت الصفحة تعرض أول طفل مكانه بصمت.
   */
  const wanted = document.querySelector('#orphanDetail') && new URLSearchParams(window.location.search).get('id');
  if (wanted && orphans && !ORPHANS.some((o) => o.id === wanted)) {
    const one = await fetchOrphan(wanted).catch(() => null);
    if (one) ORPHANS = [...ORPHANS, one];
  }
  if (posts) POSTS = take(posts, POSTS);

  if (transparency) {
    SPENDING = take(transparency.spending, SPENDING);
    PROOF = { ...PROOF, ...transparency.proof };
    ANNUAL = { ...ANNUAL, ...transparency.annual };
  }

  return { live: Boolean(bootstrap) };
}

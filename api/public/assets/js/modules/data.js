/**
 * بيانات الموقع في المتصفح: تبدأ فارغة، و`hydrate()` تملؤها من الخادم.
 *
 * كانت هنا بذرة عربية مكتوبة باليد (أطفال وبرامج وشهادات وأرقام) يعرضها
 * الموقع إن تعثّر الخادم، يوم كان ملفاتٍ ساكنة على Vercel. صار الخادم يرسم
 * الصفحات، فلم يبقَ للبذرة إلا ضررها: عربيةٌ تظهر في الصفحات الإنجليزية حين
 * تكون قائمةٌ فارغة، وأرقامٌ مخترعة («3280 مكفولاً») في موقعٍ وعده أن كل رقمٍ
 * فيه من قاعدة البيانات. فارغٌ يعني «لا شيء الآن»، وكل قسمٍ يختفي حينها.
 *
 * الصادرات `let` لا `const` عمداً: ارتباطات ES modules حيّة، فتغيير القيمة
 * هنا يراه كل مستورِد فوراً. ولا وحدة تقرأ هذه القيم وقت الاستيراد، كلها
 * تقرؤها داخل دوالها، وهو شرط صحّة هذه الحيلة.
 */

import {
  fetchBootstrap,
  fetchOrphans,
  fetchPosts,
  fetchTransparency,
} from './api.js';
import { t } from './i18n.js';

/** هوية الموقع (الاسم والشعار والبريد…) بلغة الصفحة من bootstrap؛ القالب يرسمها أصلاً. */
export let SITE = {};

/** برامج الكفالة: المصدر الوحيد للأسعار في الموقع. */
export let PROGRAMS = [];

/** الأطفال في الرئيسية. حقل `urgent` يرفع الحالة إلى أعلى القائمة. */
export let ORPHANS = [];

/** توزيع الإنفاق: حلقة الشفافية. */
export let SPENDING = [];

/**
 * ما يوفّره المبلغ الشهري: حاسبة الأثر. نصوص واجهة لا بيانات، فتبقى هنا
 * وتمرّ بـ t() إلى لغة الصفحة.
 */
export const IMPACT_TIERS = [
  { min: 15,  icon: 'fa-bowl-food',          title: t('وجبات شهر كامل'),     text: t('سلة غذائية أساسية تكفي الطفل شهراً.') },
  { min: 30,  icon: 'fa-book-open-reader',   title: t('مقعد دراسي محفوظ'),    text: t('رسوم وكتب وقرطاسية تُبقيه في مدرسته.') },
  { min: 45,  icon: 'fa-notes-medical',      title: t('رعاية طبية متابَعة'),  text: t('فحص دوري وأدوية عند الحاجة.') },
  { min: 65,  icon: 'fa-shirt',              title: t('كسوة وشتاء دافئ'),     text: t('ملابس ومستلزمات موسمية.') },
  { min: 90,  icon: 'fa-house-chimney',      title: t('استقرار الأسرة'),      text: t('مساهمة في السكن تُبقي الإخوة معاً.') },
  { min: 120, icon: 'fa-heart-circle-check', title: t('دعم نفسي وأنشطة'),     text: t('جلسات إرشاد ورحلات وأنشطة صيفية.') },
];

/** شهادات الكفلاء. */
export let STORIES = [];

/** الأسئلة الشائعة. */
export let FAQS = [];

/** مقالات المدونة. */
export let POSTS = [];

/** الميزانية السنوية المنشورة. */
export let ANNUAL = {
  year: new Date().getFullYear(),
  income: 0,
  spent: 0,
  balance: 0,
};

/**
 * لوحة الثقة: أرقام تُرى لا تُقال. بلا خادم تبقى فارغة (null) فيُخفى كل
 * بندٍ بلا رقم؛ «0 مكفولاً» أسوأ من غيابه، والرقم المخترع أسوأ منهما.
 */
export let PROOF = {
  sponsored: null,
  perDot: 40,            // كل نقطة في الشبكة تمثّل هذا العدد (إعداد عرض)
  toChild: null,
  startYear: null,
  visits: null,
};

/** الشركاء: شريط الثقة. */
export let PARTNERS = [];


/* =========================================================================
   الترطيب من الـ API
   ========================================================================= */

/** ما وصل من الخادم هو الحقيقة، ولو قائمة فارغة؛ وإن لم يصل شيء تبقى القيمة. */
const take = (incoming, current) => incoming ?? current;

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
    // صفحة الكفالة تطلب طفلها برمزه (checkout.js)
    '#homeOrphans',
  ]);
  // #heroStats و#moneyDonut في الرئيسية: أرقام الهيرو والحلقة من الشفافية نفسها
  const needsTransparency = has(['#spendingDonut', '#spendingLegend', '#proofBoard', '#heroStats', '#moneyDonut']);
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
    SITE = { ...SITE, ...bootstrap.site };
    PROGRAMS = take(bootstrap.programs, PROGRAMS);
    PARTNERS = take(bootstrap.partners, PARTNERS);
    STORIES = take(bootstrap.testimonials, STORIES);
    FAQS = take(bootstrap.faqs, FAQS);
  }

  if (orphans) ORPHANS = take(orphans, ORPHANS);

  if (posts) POSTS = take(posts, POSTS);

  if (transparency) {
    SPENDING = take(transparency.spending, SPENDING);
    PROOF = { ...PROOF, ...transparency.proof };
    ANNUAL = { ...ANNUAL, ...transparency.annual };
  }

  return { live: Boolean(bootstrap) };
}

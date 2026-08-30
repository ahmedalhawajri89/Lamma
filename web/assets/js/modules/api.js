/**
 * طبقة الاتصال بالـ API.
 *
 * مبدأ هذا الملف: **الموقع يجب أن يعمل كاملاً بلا خادم.**
 *
 * ليس هذا احتياطاً نظرياً — هذا الموقع منشور على Vercel كملفات ساكنة، وقد
 * يُفتح والخادم متوقف أو غير منشور بعد. فكل دالة هنا تُرجع `null` عند أي
 * إخفاق، و`data.js` يبقي بياناته المبدئية كما هي عندئذ. النتيجة: صفحة تعمل
 * بمحتوى صحيح بدل شاشة فارغة أو أخطاء في الطرفية.
 *
 * ولذلك أيضاً لا يوجد `throw` في هذا الملف إطلاقاً.
 */

/**
 * أصل الـ API.
 *
 * يُقرأ من `<meta name="api-base">` إن وُجد، فيمكن تغييره عند النشر بلا
 * إعادة بناء — وهذا لازم لأن المشروع بلا خطوة بناء أصلاً ولا متغيرات بيئة.
 * وتركه فارغاً يعني «لا خادم»، فيبقى الموقع على بياناته المبدئية.
 */
const BASE = document.querySelector('meta[name="api-base"]')?.content?.trim() ?? '';

/** مهلة قصيرة: صفحة تنتظر خادماً بطيئاً أسوأ من صفحة تعرض بياناتها المبدئية. */
const TIMEOUT_MS = 4000;

let offline = !BASE;

/**
 * طلب واحد. يُرجع البيانات أو `null`.
 *
 * بعد أول إخفاق نرفع `offline` فلا تحاول بقية الطلبات — إذ لا معنى لانتظار
 * أربع ثوانٍ ستّ مرات على التوالي بينما الخادم متوقف يقيناً.
 */
async function get(path, params = {}) {
  if (offline) return null;

  const url = new URL(`${BASE}/api/v1/public/${path}`);
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
  });

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);

  try {
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
      signal: controller.signal,

      /**
       * `credentials: 'include'` وإن كانت هذه النقاط عامة لا تحتاج مصادقة.
       *
       * السبب دقيق ومهم: مسارات الـ API كلها ضمن مجموعة Sanctum الحالِيّة
       * (stateful)، فأي طلب يصل بلا كوكي جلسة يفتح له لارافل **جلسة ضيف
       * جديدة** ويعيد `Set-Cookie` بها. وبلا هذا السطر كانت طلبات الترطيب
       * على `dashboard.html` تستبدل كوكي جلسة الكافل بجلسة ضيف، فيُطرَد
       * من لوحته بعد ثوانٍ من دخوله.
       *
       * وكان العيب متقطعاً لأنه سباق: يقع حين تسبق استجابة الترطيب استجابة
       * `/auth/me`. وهذا بالضبط ما تكشفه اختبارات المتصفح ولا تكشفه
       * اختبارات الوحدة.
       */
      credentials: 'include',
    });

    if (!response.ok) {
      offline = true;
      return null;
    }

    const payload = await response.json();
    return payload.data ?? null;
  } catch {
    offline = true;
    return null;
  } finally {
    clearTimeout(timer);
  }
}

/** كل ما كان ثابتاً في data.js عدا الأيتام — طلب واحد بدل ستة. */
export const fetchBootstrap = () => get('bootstrap');

/**
 * الأيتام المنشورون.
 *
 * الفلترة والترتيب في `features.js` تعملان في المتصفح على القائمة كاملةً،
 * وهو الأنسب ما دامت بالمئات: استجابة فورية بلا طلب لكل ضغطة مفتاح.
 * والـ API يدعم الفلترة والترتيب والتصفيح على الخادم متى تجاوزت الحالات
 * ما يُحمَّل دفعةً واحدة.
 */
export const fetchOrphans = () => get('orphans', { per_page: 100, sort: 'urgent' });

export const fetchTransparency = () => get('transparency');

export const fetchPosts = () => get('posts', { per_page: 12 });

export const isOffline = () => offline;

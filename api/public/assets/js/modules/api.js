/**
 * طبقة الاتصال بالـ API.
 *
 * فيها نوعان من الطلبات بسلوكين مختلفين عمداً:
 *
 *  · **القراءة** (`tryGet`) تتدهور بلطف: تُرجع `null` عند أي إخفاق ولا ترمي
 *    شيئاً. الموقع منشور ملفاتٍ ساكنة على Vercel وقد يُفتح والـ API متوقف،
 *    فيعرض عندئذ بياناته المبدئية من `data.js` بدل شاشة فارغة.
 *
 *  · **الكتابة** (`request`) ترمي عند الإخفاق: من أرسل رسالة يجب أن يعرف
 *    إن لم تصل. الصمت هنا كذب، لا لطف.
 */

/**
 * أصل الـ API.
 *
 * الموقع يُقدَّم من خادم لارافل نفسه، فالأصل واحد والمسارات نسبية — ولهذا
 * `<meta name="api-base" content="">` هو الوضع الطبيعي.
 *
 * وتبقى للوسم فائدتان:
 *  · **`content="off"`** يوقف كل نداء ويُبقي الموقع على بياناته المبدئية —
 *    لتصدير ساكن بحت على CDN بلا خادم إطلاقاً.
 *  · **أصل كامل** (`https://api.kafala.org`) لو فُصل الموقع عن الـ API يوماً.
 *
 * والقراءة من وسم HTML لا من متغيّر بيئة مقصودة: هذا الجزء من المشروع بلا
 * خطوة بناء، فالتغيير عند النشر يجب أن يكون بتحرير ملف لا بإعادة تجميع.
 */
const META = document.querySelector('meta[name="api-base"]')?.content?.trim() ?? '';
const DISABLED = META === 'off';
const BASE = DISABLED ? '' : META;

/** مهلة قصيرة: صفحة تنتظر خادماً بطيئاً أسوأ من صفحة تعرض بياناتها المبدئية. */
const TIMEOUT_MS = 4000;

let offline = DISABLED;

export const apiEnabled = () => !DISABLED;
export const isOffline = () => offline;

/* =========================================================================
   CSRF
   ========================================================================= */

/**
 * مسارات الـ API كلها ضمن مجموعة Sanctum الحالِيّة، فكل طلب مُغيِّر للحالة
 * يحتاج توكن CSRF — حتى نموذج التواصل المفتوح للجميع.
 */
let csrfReady = null;

function ensureCsrf() {
  csrfReady ??= fetch(`${BASE}/sanctum/csrf-cookie`, { credentials: 'include' });
  return csrfReady;
}

function csrfToken() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

/* =========================================================================
   الطلبات
   ========================================================================= */

/**
 * طلب يرمي عند الإخفاق.
 *
 * الخطأ كائن فيه `message` و`errors`، فيستطيع النموذج أن يعرض خطأ كل حقل
 * تحت حقله بدل رسالة عامة عائمة.
 */
export async function request(path, { method = 'GET', body = null } = {}) {
  if (DISABLED) {
    throw { message: 'هذه النسخة ساكنة بلا خادم.', errors: {} };
  }

  if (method !== 'GET') await ensureCsrf();

  const headers = { Accept: 'application/json' };
  if (body) headers['Content-Type'] = 'application/json';

  const token = csrfToken();
  if (token) headers['X-XSRF-TOKEN'] = token;

  const response = await fetch(`${BASE}/api/v1${path}`, {
    method,
    credentials: 'include',
    headers,
    body: body ? JSON.stringify(body) : null,
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw {
      status: response.status,
      message: errorMessage(response.status, payload),
      errors: payload.errors ?? {},
    };
  }

  return payload;
}

/**
 * نصّ الخطأ المعروض للزائر.
 *
 * رسائل 4xx مكتوبة له فتُعرض. أما 5xx فعطلٌ في الخادم قد يحمل نصّه تفاصيل
 * داخلية لو أفلت من معالج الخادم، فلا يُعرض منه إلا رسالتنا العامة ذات
 * رقم المرجع.
 */
function errorMessage(status, payload) {
  if (status >= 500) {
    return payload.reference
      ? `${payload.message} (المرجع ${payload.reference})`
      : 'تعذّر إتمام الطلب بسبب عطل في الخادم. حاول بعد قليل.';
  }

  return payload.message ?? 'تعذّر إتمام الطلب.';
}

/**
 * قراءة لا ترمي أبداً.
 *
 * وبعد أول إخفاق نرفع `offline` فلا تحاول بقية الطلبات — لا معنى لانتظار
 * أربع ثوانٍ ستّ مرات على التوالي بينما الخادم متوقف يقيناً.
 */
async function tryGet(path, params = {}) {
  const payload = await tryGetRaw(path, params);
  return payload ? (payload.data ?? null) : null;
}

/** كـ`tryGet` لكن يُرجع الحمولة كاملة (‏`data` و`meta`). */
async function tryGetRaw(path, params = {}) {
  if (offline) return null;

  // الأساس الثاني لازم: `BASE` فارغ في الوضع الطبيعي (الأصل نفسه)،
  // و`new URL()` بمسار نسبي بلا أساس يرمي TypeError — فيُبتلع في catch
  // أدناه ويصير الموقع «بلا خادم» صامتاً بلا أثر في الطرفية
  const url = new URL(`${BASE}/api/v1/public/${path}`, window.location.origin);
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
       * السبب دقيق ومهم: أي طلب يصل بلا كوكي جلسة يفتح له لارافل **جلسة
       * ضيف جديدة** ويعيد `Set-Cookie` بها. وبلا هذا السطر كانت طلبات
       * الترطيب على `dashboard.html` تستبدل كوكي جلسة الكافل بجلسة ضيف،
       * فيُطرَد من لوحته بعد ثوانٍ من دخوله.
       */
      credentials: 'include',
    });

    if (!response.ok) {
      offline = true;
      return null;
    }

    return await response.json();
  } catch {
    offline = true;
    return null;
  } finally {
    clearTimeout(timer);
  }
}

/* =========================================================================
   نقاط النهاية العامة
   ========================================================================= */

/** كل ما كان ثابتاً في data.js عدا الأيتام — طلب واحد بدل ستة. */
export const fetchBootstrap = () => tryGet('bootstrap');

/**
 * الأيتام المنشورون.
 *
 * الفلترة والترتيب في `features.js` تعملان في المتصفح على القائمة كاملةً،
 * وهو الأنسب ما دامت بالمئات: استجابة فورية بلا طلب لكل ضغطة مفتاح.
 * والـ API يدعم الفلترة والترتيب والتصفيح على الخادم متى تجاوزت الحالات
 * ما يُحمَّل دفعةً واحدة.
 */
export const fetchOrphans = () => tryGet('orphans', { per_page: 100, sort: 'urgent' });

export const fetchTransparency = () => tryGet('transparency');

export const POSTS_PER_PAGE = 9;

export const fetchPosts = () => tryGet('posts', { per_page: POSTS_PER_PAGE });

/**
 * صفحة مقالات بمعلومات التصفيح.
 *
 * `tryGet` تُرجع `data` وحدها وتُسقط `meta`، وهو الصواب لبقية النقاط.
 * أما زرّ «تحميل مقالات أقدم» فيحتاج أن يعرف: هل بقي شيء؟ بلا ذلك يبقى
 * الزرّ ظاهراً بعد آخر مقال فيضغطه الزائر ولا يحدث شيء.
 */
export async function fetchPostsPage(page) {
  const payload = await tryGetRaw('posts', { per_page: POSTS_PER_PAGE, page });
  return payload ? { posts: payload.data ?? [], meta: payload.meta ?? {} } : null;
}

/** مقال واحد بنصّه الكامل — نقطة القائمة لا تُرجع `body`. */
export const fetchPost = (slug) => tryGet(`posts/${encodeURIComponent(slug)}`);

/* -------------------------------------------------------------- الكتابة */

export const submitContact = (payload) =>
  request('/public/contact', { method: 'POST', body: payload });

export const subscribeNewsletter = (email, source = 'footer') =>
  request('/public/newsletter', { method: 'POST', body: { email, source } });

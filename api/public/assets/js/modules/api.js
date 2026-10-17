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

/**
 * مهلة الكتابة أطول عمداً: الدفع يمرّ بالبوابة وقد يستغرق ثوانيَ حقيقية.
 * لكنها موجودة، فخادمٌ عالق لا يترك زرّ «ادفع» يدور إلى الأبد بلا كلمة.
 */
const REQUEST_TIMEOUT_MS = 20000;

let offline = DISABLED;

/**
 * سبب آخر قراءة فشلت: `missing` (ردّ الخادم 404) أو `error` (انقطاع أو عطل).
 * صفحة المقال أو الطفل تفرّق بهما بين «لم نجد» و«تعذّر التحميل، أعد المحاولة»؛
 * كانت تقول «لم نجد» لكل إخفاق، فيظن الزائر أن الرابط مكسور وهو سليم.
 */
let lastFailure = null;

export const apiEnabled = () => !DISABLED;
export const isOffline = () => offline;
export const lastReadFailure = () => lastFailure;

/* =========================================================================
   CSRF
   ========================================================================= */

/**
 * مسارات الـ API كلها ضمن مجموعة Sanctum الحالِيّة، فكل طلب مُغيِّر للحالة
 * يحتاج توكن CSRF — حتى نموذج التواصل المفتوح للجميع.
 */
let csrfReady = null;

function ensureCsrf() {
  csrfReady ??= fetch(`${BASE}/sanctum/csrf-cookie`, {
    credentials: 'include',
    signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
  }).catch((error) => {
    // لا نحفظ الإخفاق: المحاولة التالية تطلب الكوكي من جديد
    csrfReady = null;
    throw error;
  });
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
export async function request(path, { method = 'GET', body = null, headers: extra = {} } = {}) {
  if (DISABLED) {
    throw { message: 'هذه النسخة ساكنة بلا خادم.', errors: {} };
  }

  let response;

  try {
    if (method !== 'GET') await ensureCsrf();

    const headers = { Accept: 'application/json', ...extra };
    if (body) headers['Content-Type'] = 'application/json';

    const token = csrfToken();
    if (token) headers['X-XSRF-TOKEN'] = token;

    response = await fetch(`${BASE}/api/v1${path}`, {
      method,
      credentials: 'include',
      headers,
      body: body ? JSON.stringify(body) : null,
      signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    });
  } catch (error) {
    /*
     * لا ردّ أصلاً: انقطاع أو مهلة. الرسالة تقول ذلك صراحةً بدل «تعذّر إتمام
     * الطلب» العامة، وإعادة المحاولة آمنة لأن مفتاح الكفالة ثابت للمحاولة.
     */
    const timedOut = error?.name === 'TimeoutError';
    throw {
      status: 0,
      code: timedOut ? 'timeout' : 'network',
      message: timedOut
        ? 'لم يردّ الخادم في الوقت المحدد. تحقّق من اتصالك وحاول مجدداً.'
        : 'تعذّر الاتصال بالخادم. تحقّق من اتصالك وحاول مجدداً.',
      errors: {},
    };
  }

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw {
      status: response.status,
      // رمز الحالة من الخادم (مثل login_required أو payment_failed) لتتصرف الصفحة بحسبه
      code: payload.code ?? null,
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
  if (offline) { lastFailure = 'error'; return null; }

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
       * الترطيب على `/dashboard` تستبدل كوكي جلسة الكافل بجلسة ضيف،
       * فيُطرَد من لوحته بعد ثوانٍ من دخوله.
       */
      credentials: 'include',
    });

    if (!response.ok) {
      // 404 جوابٌ من خادم يعمل: لا يُسقط بقية القراءات كما يُسقطها العطل
      lastFailure = response.status === 404 ? 'missing' : 'error';
      if (response.status !== 404) offline = true;
      return null;
    }

    lastFailure = null;
    return await response.json();
  } catch {
    lastFailure = 'error';
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

/** مقال واحد بنصّه الكامل — نقطة القائمة لا تُرجع `body`. */
export const fetchPost = (slug) => tryGet(`posts/${encodeURIComponent(slug)}`);

/* -------------------------------------------------------------- الكتابة */

export const submitContact = (payload) =>
  request('/public/contact', { method: 'POST', body: payload });

/* =========================================================================
   الكفالة
   ========================================================================= */

/** طفلٌ واحد برمزه، يرمي إن لم يوجد: صفحة الكفالة لا ترجع إلى طفل آخر بصمت */
export const fetchOrphan = (reference) => request(`/public/orphans/${encodeURIComponent(reference)}`).then((r) => r.data);

/** وضع البوابة (تجريبية؟)، وبيانات التحويل، ومهلة الحجز */
export const fetchCheckoutConfig = () => request('/public/checkout/config').then((r) => r.data);

/** الكفالة والدفع. المفتاح يجعل النقرة الثانية لا تخصم مرتين */
export const submitCheckout = (payload, idempotencyKey) =>
  request('/public/checkout', { method: 'POST', body: payload, headers: { 'Idempotency-Key': idempotencyKey } });

export const subscribeNewsletter = (email, source = 'footer') =>
  request('/public/newsletter', { method: 'POST', body: { email, source } });

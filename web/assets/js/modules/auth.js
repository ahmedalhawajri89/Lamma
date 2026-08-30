/**
 * مصادقة الكافل من الموقع العام.
 *
 * الجلسة كوكي `HttpOnly` يضبطه الخادم — لا توكن في `localStorage`. هذا يعني
 * أن هذا الملف لا يملك أن يقرأ حالة الدخول من الذاكرة، فيسألها الخادم عبر
 * `me()`. وهو الصواب: أي حالة نحفظها هنا قد تكذب بعد انتهاء الجلسة.
 *
 * الموقع (‏5501) والـ API (‏8000) على المضيف نفسه بمنفذين مختلفين، وهذا
 * **same-site** لا cross-site — فكوكي `SameSite=Lax` يُرسَل. وفي الإنتاج
 * يتحقق الشرط نفسه بنطاقين فرعيين تحت نطاق واحد.
 */

const BASE = document.querySelector('meta[name="api-base"]')?.content?.trim() ?? '';

/** يقرأ توكن CSRF من الكوكي ليعيده في الترويسة — هكذا يعمل Sanctum. */
function csrfToken() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

let csrfReady = null;

function ensureCsrf() {
  csrfReady ??= fetch(`${BASE}/sanctum/csrf-cookie`, { credentials: 'include' });
  return csrfReady;
}

/**
 * طلب مُصادَق.
 *
 * يرمي كائناً فيه `message` و`errors` عند الرفض، فيستطيع النموذج أن يعرض
 * خطأ كل حقل تحت حقله بدل رسالة عامة.
 */
async function request(path, { method = 'GET', body = null } = {}) {
  if (!BASE) {
    throw { message: 'لا اتصال بالخادم.', errors: {} };
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
      message: payload.message ?? 'تعذّر إتمام الطلب.',
      errors: payload.errors ?? {},
    };
  }

  return payload;
}

export const login = (email, password, remember = false) =>
  request('/auth/login', { method: 'POST', body: { email, password, remember } });

export const register = (payload) =>
  request('/auth/register', { method: 'POST', body: payload });

export const logout = () => request('/auth/logout', { method: 'POST' });

/** يُرجع المستخدم أو `null` — والـ 401 هنا حالة طبيعية لا خطأ. */
export async function me() {
  try {
    const { data } = await request('/auth/me');
    return data;
  } catch {
    return null;
  }
}

export const portalOverview = () => request('/portal/overview');
export const portalPayments = (params = '') => request(`/portal/payments${params}`);
export const portalReports = () => request('/portal/reports');
export const markReportSeen = (id) => request(`/portal/reports/${id}`);

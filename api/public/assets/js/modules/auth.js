/**
 * مصادقة الكافل وبوابته.
 *
 * الجلسة كوكي `HttpOnly` يضبطه الخادم — لا توكن في `localStorage`. هذا يعني
 * أن هذا الملف لا يملك أن يقرأ حالة الدخول من الذاكرة، فيسألها الخادم عبر
 * `me()`. وهو الصواب: أي حالة نحفظها هنا قد تكذب بعد انتهاء الجلسة.
 *
 * الموقع (‏5501) والـ API (‏8000) على المضيف نفسه بمنفذين مختلفين، وهذا
 * **same-site** لا cross-site — فكوكي `SameSite=Lax` يُرسَل. وفي الإنتاج
 * يتحقق الشرط نفسه بنطاقين فرعيين تحت نطاق واحد.
 *
 * وآلة الطلب نفسها في `api.js`: نقطة واحدة تعرف كيف تتعامل مع CSRF
 * والكوكيز والأخطاء، بدل نسختين تفترقان مع أول تعديل.
 */

import { request } from './api.js';

export const login = (email, password, remember = false) =>
  request('/auth/login', { method: 'POST', body: { email, password, remember } });

export const register = (payload) =>
  request('/auth/register', { method: 'POST', body: payload });

export const logout = () => request('/auth/logout', { method: 'POST' });

/** طلب رابط الاستعادة. الردّ واحد سواء وُجد البريد أم لا — بقصد. */
export const forgotPassword = (email) =>
  request('/auth/forgot-password', { method: 'POST', body: { email } });

export const resetPassword = (payload) =>
  request('/auth/reset-password', { method: 'POST', body: payload });

/**
 * هل الحساب حساب كافل؟
 *
 * الدخول نقطة واحدة لكل الحسابات، لكن لوحة الكافل للكفلاء وحدهم، وفريق
 * العمل يدخل من لوحة الإدارة. الخادم يرفض /portal/* لغير الكافل على أي حال.
 */
export const isSponsor = (user) =>
  Boolean(user?.sponsor) || (user?.roles ?? []).includes('sponsor');

/** يُرجع المستخدم أو `null` — والـ 401 هنا حالة طبيعية لا خطأ. */
export async function me({ strict = false } = {}) {
  try {
    const { data } = await request('/auth/me');
    return data;
  } catch (error) {
    /*
     * في `strict` لا يُعدّ انقطاع الشبكة أو عطل الخادم خروجاً: كانت اللوحة
     * تُرسل الكافل إلى صفحة الدخول ويُمسح تلميحه كلما تعثّر الخادم ثانية.
     */
    if (strict && !isAuthLoss(error)) throw error;
    return null;
  }
}

/** الجلسة انتهت أو رُفض رمزها: 401 أو 419 (CSRF). */
export const isAuthLoss = (error) => error?.status === 401 || error?.status === 419;

/**
 * تلميح «سبق له الدخول» للهيدر.
 *
 * ليس حالة دخول: الجلسة يملكها الخادم وحده. هو فقط يجنّب كل زائر طلب
 * `me()` في كل صفحة. من عنده التلميح نسأل الخادم عنه، ومن ليس عنده لا نسأل.
 */
const HINT = 'kafala-signed-in';

export function markSignedIn() {
  try { localStorage.setItem(HINT, '1'); } catch { /* تخزين محجوب */ }
}

export function clearSignedIn() {
  try { localStorage.removeItem(HINT); } catch { /* تخزين محجوب */ }
}

export function hasSignedInHint() {
  try { return localStorage.getItem(HINT) === '1'; } catch { return false; }
}

export const portalOverview = () => request('/portal/overview');
export const portalPayments = (params = '') => request(`/portal/payments${params}`);
export const portalReports = () => request('/portal/reports');
/** «أين ذهب مالك»: سجلّ الطفل المالي شهراً بشهر */
export const portalLedger = (sponsorshipId, months = 12) =>
  request(`/portal/sponsorships/${sponsorshipId}/ledger?months=${months}`);
/** القسط المتعثّر ببطاقة جديدة: يُخصم بها فوراً وتُحفظ للأشهر القادمة. */
export const updateSponsorshipCard = (sponsorshipId, card) =>
  request(`/portal/sponsorships/${sponsorshipId}/card`, { method: 'PUT', body: card });
export const markReportSeen = (id) => request(`/portal/reports/${id}`);

export const portalMessages = (sponsorshipId) =>
  request(`/portal/sponsorships/${sponsorshipId}/messages`);
export const sendPortalMessage = (sponsorshipId, body) =>
  request(`/portal/sponsorships/${sponsorshipId}/messages`, { method: 'POST', body: { body } });

/** الإنهاء إلغاءٌ نهائي على الخادم: يتوقف الخصم، ويُؤمَّن للطفل كافل بديل. */
export const cancelSponsorship = (sponsorshipId, reason = '') =>
  request(`/portal/sponsorships/${sponsorshipId}`, { method: 'DELETE', body: { reason } });

/** إيقاف مؤقت (`paused`) أو استئناف (`active`): الطفل يبقى محجوزاً للكافل. */
export const setSponsorshipStatus = (sponsorshipId, status) =>
  request(`/portal/sponsorships/${sponsorshipId}`, { method: 'PATCH', body: { status } });

export const updateProfile = (payload) =>
  request('/portal/profile', { method: 'PATCH', body: payload });
export const updatePassword = (payload) =>
  request('/portal/profile/password', { method: 'PUT', body: payload });

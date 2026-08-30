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

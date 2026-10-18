/**
 * سلوك الترويسة في كل الصفحات: تختفي عند النزول وتعود عند الصعود، ورابط
 * الحساب يصير «كفالاتي» لمن دخل، وإعدادات الموقع تُكتب في أماكنها.
 *
 * الترويسة والفوتر يرسمهما الخادم (resources/views/site/partials). كانا يُبنيان
 * هنا بـ JS لصفحات HTML القديمة، وذهبت تلك الصفحات فذهب البناء معها.
 */

import { SITE } from './data.js';
import { $, $$, rafThrottle } from './utils.js';
import { me, hasSignedInHint, clearSignedIn } from './auth.js';
import { url, t } from './i18n.js';

/**
 * الشريط يختفي عند النزول ويعود عند أول صعود.
 *
 * ولا يختفي أبداً ودرج الجوال مفتوح أو التركيز بداخله — من يتنقّل
 * بلوحة المفاتيح لا يجوز أن يختفي الرابط الذي يقف عليه.
 */
function initStickyHeader() {
  const header = $('#siteHeader');
  if (!header) return;

  const nav = $('#siteNav');
  let lastY = window.scrollY;

  // ارتفاعها لما يلصق تحتها (شريط فلاتر الأيتام)، ويتبعها إن تغيّر
  const syncHeight = () => document.documentElement.style.setProperty('--kf-header-h', `${header.offsetHeight}px`);
  syncHeight();
  if ('ResizeObserver' in window) new ResizeObserver(syncHeight).observe(header);

  const update = rafThrottle(() => {
    const y = window.scrollY;

    // لا تختفي ودرج الجوال مفتوح أو التركيز بداخلها
    const locked = nav?.classList.contains('show') || header.contains(document.activeElement);
    if (locked || y < 120) header.classList.remove('is-hidden');
    else if (y > lastY + 6) header.classList.add('is-hidden');
    else if (y < lastY - 6) header.classList.remove('is-hidden');

    lastY = y;
  });

  update();
  window.addEventListener('scroll', update, { passive: true });
  header.addEventListener('focusin', () => header.classList.remove('is-hidden'));
}

/**
 * الكافل المسجّل يرى «كفالاتي» مكان «تسجيل الدخول» ويذهب إلى بوابته مباشرة.
 * لا نسأل الخادم إلا إن سبق للمتصفح دخول، فالزائر العادي لا يكلّف طلباً.
 */
async function initAccountLink() {
  if (!hasSignedInHint()) return;

  const user = await me();
  if (!user) {
    clearSignedIn();
    return;
  }

  $$('[data-account-link]').forEach((link) => {
    link.href = url('/dashboard');
    link.textContent = t('كفالاتي');
  });
}

/**
 * تحديث بيانات الموقع في هيكلٍ مركَّب سلفاً.
 *
 * الهيكل يُركَّب من البذرة قبل انتظار الشبكة، كي لا تبقى الصفحة بلا ترويسة
 * ولا تنقّل حتى يعود طلب bootstrap. فإن جاءت إعدادات الخادم بعد ذلك تُكتب
 * نصوصها هنا في أماكنها، بلا إعادة بناء تُضيّع التركيز أو حالة القائمة.
 */
export function refreshLayout() {
  $$('[data-site]').forEach((el) => {
    const value = SITE[el.dataset.site];
    if (typeof value !== 'string') return;
    el.textContent = value;
    if (el.dataset.site === 'email') el.href = `mailto:${value}`;
  });
}

/** سلوك الترويسة المرسومة من الخادم. */
export function mountLayout() {
  initStickyHeader();
  initAccountLink();
}

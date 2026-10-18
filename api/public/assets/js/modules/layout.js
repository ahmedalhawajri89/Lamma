/**
 * سلوك الترويسة في كل الصفحات: تتكيّف مع التمرير، وتختفي عند النزول وتعود
 * عند الصعود، وتعرض حساب الكافل لمن دخل، وتُكتب إعدادات الموقع في أماكنها.
 *
 * الترويسة والفوتر يرسمهما الخادم (resources/views/site/partials). كانا يُبنيان
 * هنا بـ JS لصفحات HTML القديمة، وذهبت تلك الصفحات فذهب البناء معها.
 */

import { SITE } from './data.js';
import { $, $$, rafThrottle } from './utils.js';
import { me, isSponsor, logout, hasSignedInHint, clearSignedIn } from './auth.js';

/**
 * أعلى الصفحة: الترويسة بلون الأرضية بلا حدّ، متصلةً بالهيرو. بعد أول
 * تمرير (.is-scrolled): أقصر، على سطحٍ بظلٍّ خفيف.
 * وتختفي عند النزول وتعود عند أول صعود، إلا ودرج الجوال أو البحث مفتوح أو
 * التركيز بداخلها: من يتنقّل بلوحة المفاتيح لا يختفي الرابط الذي يقف عليه.
 */
function initStickyHeader() {
  const header = $('#siteHeader');
  if (!header) return;

  const nav = $('#siteNav');
  const search = $('#siteSearch');
  let lastY = window.scrollY;

  // ارتفاعها لما يلصق تحتها (شريط فلاتر الأيتام)، ويتبعها إن تغيّر
  const syncHeight = () => document.documentElement.style.setProperty('--kf-header-h', `${header.offsetHeight}px`);
  syncHeight();
  if ('ResizeObserver' in window) new ResizeObserver(syncHeight).observe(header);

  const update = rafThrottle(() => {
    const y = window.scrollY;
    header.classList.toggle('is-scrolled', y > 8);

    const locked = nav?.classList.contains('show') || search?.open || header.contains(document.activeElement);
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
 * حساب الكافل: دائرة بأحرف اسمه وقائمة (لوحتي، كفالاتي، التقارير، الإعدادات،
 * الخروج) مكان «تسجيل الدخول»، في الشريط وفي الدرج.
 *
 * لا نسأل الخادم إلا إن سبق للمتصفح دخول، فالزائر العادي لا يكلّف طلباً.
 * ومن له تلميح تظهر دائرته فارغة فوراً، فلا تقفز الترويسة حين يعود الطلب.
 * حساب فريق العمل يبقى على «تسجيل الدخول»: لوحته غير هذه.
 */
async function initAccount() {
  if (!hasSignedInHint()) return;
  showAccount(true);

  const user = await me();
  if (!user || !isSponsor(user)) {
    if (!user) clearSignedIn();
    showAccount(false);
    return;
  }

  $$('[data-account-name]').forEach((el) => { el.textContent = user.name ?? ''; });
  $$('[data-account-email]').forEach((el) => { el.textContent = user.email ?? ''; });
  $$('[data-account-initials]').forEach((el) => { el.textContent = initials(user.name); });

  $$('[data-account-logout]').forEach((btn) => btn.addEventListener('click', async () => {
    btn.disabled = true;
    await logout().catch(() => {});
    clearSignedIn();
    window.location.reload();
  }));
}

function showAccount(signedIn) {
  $$('[data-account]').forEach((el) => { el.hidden = !signedIn; });
  $$('[data-account-login]').forEach((el) => { el.hidden = signedIn; });
}

/** «سارة الحاج» ← «سح»، و«Jane Doe» ← «JD». */
function initials(name = '') {
  return String(name).trim().split(/\s+/).slice(0, 2).map((w) => w[0] ?? '').join('').toUpperCase();
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
  initAccount();
}

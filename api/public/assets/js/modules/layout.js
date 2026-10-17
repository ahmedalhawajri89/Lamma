/**
 * الهيدر لصفحات التصميم القديم (legacy)، وسلوك الترويسة في كل الصفحات.
 *
 * لماذا بناؤهما بـ JS بدل fetch لملف HTML؟
 * لأن fetch يفشل تحت بروتوكول file://، فيفتح أحدهم / بنقرة مزدوجة
 * فلا يرى قائمة ولا فوتر. البناء من قالب داخلي يعمل في كل الحالات،
 * ويبقي التعديل في مكان واحد بدل تكراره في ١٤ صفحة.
 *
 * ولمن لا يعمل لديه JS: كل صفحة تحوي <noscript> بروابط التنقل الأساسية.
 *
 * الصفحات المنقولة إلى Blade (resources/views/site) تصل وترويستها وفوترها
 * مرسومان من الخادم، فلا مكان فيها لـ data-layout ولا يُبنى هنا شيء؛ يبقى
 * السلوك وحده: الإخفاء عند التمرير ورابط الحساب.
 */

import { SITE, NAV_LINKS } from './data.js';
import { $, $$, currentPage, rafThrottle, escapeHtml, lockScroll } from './utils.js';
import { me, hasSignedInHint, clearSignedIn } from './auth.js';

/** الصفحات العميقة تُبرز قسمها في التنقل: ملف الطفل والكفالة تحت «الأيتام»، والمقال تحت «المدونة». */
const SECTION_OF = { '/orphan': '/orphans', '/checkout': '/orphans', '/post': '/blog' };

function navMarkup(page) {
  return NAV_LINKS.map(({ label, href }) => {
    // «page» للصفحة نفسها، و«true» لقسمٍ أنت داخله
    const active = href === page ? ' aria-current="page"' : href === SECTION_OF[page] ? ' aria-current="true"' : '';
    return `<a class="nav__link" href="${href}"${active}>${label}</a>`;
  }).join('');
}

function headerMarkup({ page }) {
  return `
<a class="skip-link" href="${escapeHtml(window.location.pathname + window.location.search)}#main">تخطَّ إلى المحتوى الرئيسي</a>
<header class="site-header" id="siteHeader">
  <div class="container header-row">
    <a class="brand" href="/">
      <img class="brand__mark" src="assets/img/logo.svg" alt="" width="36" height="36">
      <span class="brand__name"><span data-site="name">${escapeHtml(SITE.name)}</span><span class="brand__tag" data-site="tagline">${escapeHtml(SITE.tagline)}</span></span>
    </a>

    <div class="site-nav" id="siteNav">
      <nav class="nav" aria-label="التنقل الرئيسي">${navMarkup(page)}</nav>

      <div class="site-nav__buttons">
        <a class="btn btn--ink-outline btn--lg" href="/login" data-account-link>تسجيل الدخول</a>
        <a class="btn btn--cta btn--lg" href="/orphans">اكفل يتيماً</a>
      </div>
    </div>

    <div class="header-actions">
      <button class="theme-toggle" type="button" id="themeToggle" aria-pressed="false"
              aria-label="الوضع الليلي">
        <i class="fa-solid fa-moon" aria-hidden="true"></i>
        <i class="fa-solid fa-sun" aria-hidden="true"></i>
      </button>
      <a class="header-login" href="/login" data-account-link>تسجيل الدخول</a>
      <a class="btn btn--cta btn--sm" href="/orphans">اكفل يتيماً</a>
      <button class="nav-toggle" type="button" id="navToggle"
              aria-expanded="false" aria-controls="siteNav" aria-label="فتح قائمة التنقل">
        <span class="nav-toggle__bars" aria-hidden="true"><span></span><span></span><span></span></span>
      </button>
    </div>
  </div>
</header>`;
}

/* =========================================================================
   نسخة Bootstrap: للصفحات التي انتقلت إليه (<html data-ui="bs">)
   -------------------------------------------------------------------------
   الصفحات تنتقل واحدةً واحدة، لأن Bootstrap والأنماط القديمة يتشاركان
   أسماء أصناف (.btn و.container و.nav)، فتحميله على صفحة لم تُنقل يكسرها.
   القائمة الجوالة offcanvas-lg: درجٌ جانبي على الجوال، وشريطٌ عادي من lg
   فما فوق. وBootstrap يتولى فيها حبس التركيز وEscape وقفل التمرير.
   ========================================================================= */
const usesBootstrap = () => document.documentElement.dataset.ui === 'bs';

function bsNavMarkup(page) {
  return NAV_LINKS.map(({ label, href }) => {
    const current = href === page ? 'page' : href === SECTION_OF[page] ? 'true' : '';
    return `<li class="nav-item"><a class="nav-link${current ? ' active' : ''}" href="${href}"${current ? ` aria-current="${current}"` : ''}>${label}</a></li>`;
  }).join('');
}

function bsHeaderMarkup({ page }) {
  const brand = (size) => `
      <img src="assets/img/logo.svg" alt="" width="${size}" height="${size}">
      <span class="kf-brand__name"><span data-site="name">${escapeHtml(SITE.name)}</span><span class="kf-brand__tag" data-site="tagline">${escapeHtml(SITE.tagline)}</span></span>`;

  return `
<a class="visually-hidden-focusable kf-skip btn btn-primary" href="${escapeHtml(window.location.pathname + window.location.search)}#main">تخطَّ إلى المحتوى الرئيسي</a>
<header class="kf-header" id="siteHeader">
  <nav class="navbar navbar-expand-lg" aria-label="التنقل الرئيسي">
    <div class="container">
      <a class="navbar-brand kf-brand" href="/">${brand(46)}</a>

      <div class="offcanvas-lg offcanvas-end flex-lg-grow-1" tabindex="-1" id="siteNav" aria-labelledby="siteNavTitle">
        <div class="offcanvas-header">
          <p class="offcanvas-title h5 mb-0" id="siteNavTitle">القائمة</p>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#siteNav" aria-label="إغلاق القائمة"></button>
        </div>
        <div class="offcanvas-body">
          <ul class="navbar-nav mx-lg-auto">${bsNavMarkup(page)}</ul>
          <div class="d-grid gap-2 mt-4 d-lg-none">
            <a class="btn btn-outline-primary btn-lg" href="/login" data-account-link>تسجيل الدخول</a>
            <a class="btn btn-cta btn-lg" href="/orphans">اكفل يتيماً</a>
          </div>
        </div>
      </div>

      <div class="kf-header__actions">
        <a class="btn btn-outline-primary d-none d-lg-inline-flex" href="/login" data-account-link>تسجيل الدخول</a>
        <a class="btn btn-cta d-none d-sm-inline-flex" href="/orphans">
          <i class="fa-regular fa-heart" aria-hidden="true"></i> اكفل يتيماً
        </a>
        <a class="btn btn-icon btn-icon--plain" href="/orphans" aria-label="ابحث عن طفل" title="ابحث عن طفل">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        </a>
        <button class="btn btn-icon btn-icon--plain" type="button" id="themeToggle" aria-pressed="false" aria-label="الوضع الليلي">
          <i class="fa-solid fa-moon" aria-hidden="true"></i>
          <i class="fa-solid fa-sun" aria-hidden="true"></i>
        </button>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#siteNav"
                aria-controls="siteNav" aria-label="فتح قائمة التنقل">
          <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </nav>
</header>`;
}

/** فتح/غلق القائمة الجوالة مع إدارة صحيحة لحالة ARIA. */
function initNavToggle() {
  const toggle = $('#navToggle');
  const nav = $('#siteNav');
  const header = $('#siteHeader');
  if (!toggle || !nav || !header) return;

  /**
   * القائمة المفتوحة تغطي الصفحة، فما خلفها يصير `inert`: لا يصله Tab ولا
   * القارئ الصوتي. كان التركيز يبقى على الزر ثم يتسرّب Tab إلى محتوى لا يُرى.
   * الترويسة نفسها تبقى حيّة لأن زرّ الإغلاق وزرّ السمة فيها، وكذلك مناطق
   * الإعلان الحيّ (الإشعارات) كي لا يضيع ما يُقال أثناء فتحها.
   */
  const background = () => {
    // إخوة الترويسة وإخوة كل أبٍ لها حتى <body>، فتعمل أياً كان موضعها في الصفحة
    const out = [];
    for (let node = header; node.parentElement && node !== document.body; node = node.parentElement) {
      [...node.parentElement.children].forEach((el) => {
        if (el !== node && !el.matches('script, [aria-live]')) out.push(el);
      });
    }
    return out;
  };

  const setOpen = (open, { returnFocus = false } = {}) => {
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'إغلاق قائمة التنقل' : 'فتح قائمة التنقل');
    nav.classList.toggle('is-open', open);
    lockScroll('nav', open);
    background().forEach((el) => el.toggleAttribute('inert', open));

    if (open) {
      // إطارٌ واحد: القائمة ما زالت visibility: hidden لحظة إضافة الصنف
      requestAnimationFrame(() => $('a, button', nav)?.focus());
    } else if (returnFocus) {
      toggle.focus();
    }
  };

  toggle.addEventListener('click', () => {
    setOpen(toggle.getAttribute('aria-expanded') !== 'true');
  });

  // الإغلاق عند اختيار رابط، أو الضغط على Escape، أو النقر خارج الترويسة
  nav.addEventListener('click', (e) => {
    if (e.target.closest('a')) setOpen(false);
  });

  document.addEventListener('click', (e) => {
    if (nav.classList.contains('is-open') && !header.contains(e.target)) setOpen(false, { returnFocus: true });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav.classList.contains('is-open')) setOpen(false, { returnFocus: true });
  });

  // القائمة الجوالة لا معنى لها على الشاشة العريضة؛ والحدث يطلق مرة عند عبور الحد لا مع كل بكسل
  window.matchMedia('(min-width: 64.0625rem)').addEventListener('change', (e) => {
    if (e.matches && nav.classList.contains('is-open')) setOpen(false);
  });
}

/**
 * الشريط يختفي عند النزول ويعود عند أول صعود.
 *
 * ولا يختفي أبداً والقائمة الجوالة مفتوحة أو التركيز بداخله — من يتنقّل
 * بلوحة المفاتيح لا يجوز أن يختفي الرابط الذي يقف عليه.
 */
function initStickyHeader() {
  const header = $('#siteHeader');
  if (!header) return;

  const nav = $('#siteNav');
  let lastY = window.scrollY;

  const update = rafThrottle(() => {
    const y = window.scrollY;

    // is-open للقائمة القديمة، وshow لدرج Bootstrap
    const locked = nav?.classList.contains('is-open') || nav?.classList.contains('show')
      || header.contains(document.activeElement);
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
    link.href = '/dashboard';
    link.textContent = 'كفالاتي';
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

/** تركيب هيكل الصفحة: الهيدر والفوتر وسلوكهما. */
export function mountLayout() {
  const headerSlot = $('[data-layout="header"]');
  const page = currentPage();

  const bs = usesBootstrap();
  if (headerSlot) headerSlot.outerHTML = bs ? bsHeaderMarkup({ page }) : headerMarkup({ page });

  // درج Bootstrap يدير نفسه؛ القائمة القديمة تحتاج سلوكها
  if (!bs) initNavToggle();
  initStickyHeader();
  initAccountLink();
}

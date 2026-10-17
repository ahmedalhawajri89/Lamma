/**
 * الهيدر والفوتر — مصدر واحد لكل الصفحات.
 *
 * لماذا بناؤهما بـ JS بدل fetch لملف HTML؟
 * لأن fetch يفشل تحت بروتوكول file://، فيفتح أحدهم index.html بنقرة مزدوجة
 * فلا يرى قائمة ولا فوتر. البناء من قالب داخلي يعمل في كل الحالات،
 * ويبقي التعديل في مكان واحد بدل تكراره في ١٤ صفحة.
 *
 * ولمن لا يعمل لديه JS: كل صفحة تحوي <noscript> بروابط التنقل الأساسية.
 */

import { SITE, NAV_LINKS } from './data.js';
import { $, $$, currentPage, rafThrottle, escapeHtml, lockScroll } from './utils.js';
import { me, hasSignedInHint, clearSignedIn } from './auth.js';

const YEAR = new Date().getFullYear();

/** روابط الفوتر المساعدة. */
const FOOTER_HELP = [
  { label: 'الأسئلة الشائعة', href: 'faq.html' },
  { label: 'تقارير الشفافية', href: 'transparency.html' },
  { label: 'سياسة حماية الطفل', href: 'transparency.html#child-policy' },
  { label: 'حسابي', href: 'dashboard.html' },
];

/** الصفحات العميقة تُبرز قسمها في التنقل: ملف الطفل والكفالة تحت «الأيتام»، والمقال تحت «المدونة». */
const SECTION_OF = { 'orphan.html': 'orphans.html', 'checkout.html': 'orphans.html', 'post.html': 'blog.html' };

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
    <a class="brand" href="index.html">
      <img class="brand__mark" src="assets/img/logo.svg" alt="" width="36" height="36">
      <span class="brand__name"><span data-site="name">${escapeHtml(SITE.name)}</span><span class="brand__tag" data-site="tagline">${escapeHtml(SITE.tagline)}</span></span>
    </a>

    <div class="site-nav" id="siteNav">
      <nav class="nav" aria-label="التنقل الرئيسي">${navMarkup(page)}</nav>

      <div class="site-nav__buttons">
        <a class="btn btn--ink-outline btn--lg" href="login.html" data-account-link>تسجيل الدخول</a>
        <a class="btn btn--cta btn--lg" href="orphans.html">اكفل يتيماً</a>
      </div>
    </div>

    <div class="header-actions">
      <button class="theme-toggle" type="button" id="themeToggle" aria-pressed="false"
              aria-label="الوضع الليلي">
        <i class="fa-solid fa-moon" aria-hidden="true"></i>
        <i class="fa-solid fa-sun" aria-hidden="true"></i>
      </button>
      <a class="header-login" href="login.html" data-account-link>تسجيل الدخول</a>
      <a class="btn btn--cta btn--sm" href="orphans.html">اكفل يتيماً</a>
      <button class="nav-toggle" type="button" id="navToggle"
              aria-expanded="false" aria-controls="siteNav" aria-label="فتح قائمة التنقل">
        <span class="nav-toggle__bars" aria-hidden="true"><span></span><span></span><span></span></span>
      </button>
    </div>
  </div>
</header>`;
}

function footerMarkup() {
  const social = SITE.social
    .map(({ label, icon, url }) =>
      `<a href="${escapeHtml(url)}" aria-label="${escapeHtml(label)} (تفتح في نافذة جديدة)" rel="noopener noreferrer" target="_blank">
         <i class="fa-brands ${icon}" aria-hidden="true"></i>
       </a>`)
    .join('');

  const quickLinks = [
    { label: 'الرئيسية', href: 'index.html' },
    ...NAV_LINKS,
  ]
    .map(({ label, href }) => `<li><a href="${href}">${label}</a></li>`)
    .join('');

  const helpLinks = FOOTER_HELP
    .map(({ label, href }) => `<li><a href="${href}">${label}</a></li>`)
    .join('');

  return `
<footer class="site-footer">
  <div class="container">

    <div class="footer-top">
      <div class="footer-intro">
        <a class="brand" href="index.html">
          <img class="brand__mark" src="assets/img/logo.svg" alt="" width="38" height="38">
          <span class="brand__name"><span data-site="name">${escapeHtml(SITE.name)}</span><span class="brand__tag" data-site="tagline">${escapeHtml(SITE.tagline)}</span></span>
        </a>
        <p class="footer-about">
          منصة تربط بين من يريد أن يعطي ومن يستحق أن يُعطى، بشفافية موثّقة وتقارير دورية.
          نعمل منذ ١٤ عاماً في ١٢ دولة.
        </p>
        <p class="footer-trust">
          <i class="fa-solid fa-file-shield" aria-hidden="true"></i>
          <span><strong>مرخّصون ومدقّقون.</strong> ميزانية سنوية معتمدة من مكتب مستقل ومنشورة للجميع.</span>
        </p>
      </div>

      <div class="footer-news">
        <p class="footer-claim">تابِع أثر الكفالة كل شهر، ولو لم تكفل بعد.</p>
        <p class="footer-news__label">
          رسالة واحدة كل شهر: ما أنجزناه، وما صرفناه، وقصة طفل واحد. بلا إلحاح.
        </p>
        <form class="newsletter" data-validate-form data-action="newsletter" data-source="footer"
              data-success-message="تم اشتراكك، ستصلك أول رسالة مطلع الشهر" novalidate>
          <div class="field" style="flex:1">
            <label class="sr-only" for="footerNewsEmail">البريد الإلكتروني</label>
            <div class="field__control">
              <input class="input" type="email" id="footerNewsEmail" name="email"
                     placeholder="بريدك الإلكتروني" autocomplete="email"
                     data-validate="required|email" aria-describedby="err-footerNews">
            </div>
            <p class="field__error" id="err-footerNews" aria-live="polite"></p>
          </div>
          <button class="btn btn--primary" type="submit">اشترك</button>
        </form>
      </div>
    </div>

    <div class="site-footer__grid">
      <nav class="footer-col footer-col--wide" aria-labelledby="footerNavHeading">
        <h2 class="footer-heading" id="footerNavHeading">تصفّح</h2>
        <ul class="footer-links footer-links--split" role="list">${quickLinks}</ul>
      </nav>

      <nav class="footer-col" aria-labelledby="footerHelpHeading">
        <h2 class="footer-heading" id="footerHelpHeading">مساعدة</h2>
        <ul class="footer-links" role="list">${helpLinks}</ul>
      </nav>

      <div class="footer-col">
        <h2 class="footer-heading">تواصل معنا</h2>
        <ul class="footer-contact" role="list">
          <li>
            <i class="fa-solid fa-phone" aria-hidden="true"></i>
            <a href="tel:${escapeHtml(SITE.phoneHref)}" dir="ltr" data-site="phone">${escapeHtml(SITE.phone)}</a>
          </li>
          <li>
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            <a href="mailto:${escapeHtml(SITE.email)}" data-site="email">${escapeHtml(SITE.email)}</a>
          </li>
          <li>
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
            <span data-site="address">${escapeHtml(SITE.address)}</span>
          </li>
        </ul>
        <div class="social-row">${social}</div>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© ${YEAR} منصة <span data-site="name">${escapeHtml(SITE.name)}</span>. جميع الحقوق محفوظة.</p>
      <nav aria-label="روابط قانونية">
        <a href="transparency.html#child-policy">سياسة حماية الطفل</a>
        <a href="transparency.html">الخصوصية</a>
        <a href="faq.html">شروط الاستخدام</a>
      </nav>
      <div class="payment-row" role="img" aria-label="وسائل الدفع المقبولة: فيزا، ماستركارد، Apple Pay، والتحويل البنكي">
        <i class="fa-brands fa-cc-visa" aria-hidden="true"></i>
        <i class="fa-brands fa-cc-mastercard" aria-hidden="true"></i>
        <i class="fa-brands fa-apple-pay" aria-hidden="true"></i>
        <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
      </div>
    </div>
  </div>
</footer>`;
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
      <a class="navbar-brand kf-brand" href="index.html">${brand(46)}</a>

      <div class="offcanvas-lg offcanvas-end flex-lg-grow-1" tabindex="-1" id="siteNav" aria-labelledby="siteNavTitle">
        <div class="offcanvas-header">
          <p class="offcanvas-title h5 mb-0" id="siteNavTitle">القائمة</p>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#siteNav" aria-label="إغلاق القائمة"></button>
        </div>
        <div class="offcanvas-body">
          <ul class="navbar-nav mx-lg-auto">${bsNavMarkup(page)}</ul>
          <div class="d-grid gap-2 mt-4 d-lg-none">
            <a class="btn btn-outline-primary btn-lg" href="login.html" data-account-link>تسجيل الدخول</a>
            <a class="btn btn-cta btn-lg" href="orphans.html">اكفل يتيماً</a>
          </div>
        </div>
      </div>

      <div class="kf-header__actions">
        <a class="btn btn-outline-primary d-none d-lg-inline-flex" href="login.html" data-account-link>تسجيل الدخول</a>
        <a class="btn btn-cta d-none d-sm-inline-flex" href="orphans.html">
          <i class="fa-regular fa-heart" aria-hidden="true"></i> اكفل يتيماً
        </a>
        <a class="btn btn-icon btn-icon--plain" href="orphans.html" aria-label="ابحث عن طفل" title="ابحث عن طفل">
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

function bsFooterMarkup() {
  const links = (items) => items.map(({ label, href }) => `<li><a href="${href}">${label}</a></li>`).join('');
  const social = SITE.social
    .map(({ label, icon, url }) =>
      `<a class="kf-footer__social" href="${escapeHtml(url)}" aria-label="${escapeHtml(label)} (تفتح في نافذة جديدة)" rel="noopener noreferrer" target="_blank"><i class="fa-brands ${icon}" aria-hidden="true"></i></a>`)
    .join('');

  return `
<footer class="kf-footer">
  <div class="container">
    <div class="kf-news-card">
      <span class="kf-news-card__icon" aria-hidden="true"><i class="fa-solid fa-envelope-open-text"></i></span>
      <div class="kf-news-card__text">
        <h2 class="kf-news-card__title">تابِع أثر الكفالة كل شهر، ولو لم تكفل بعد.</h2>
        <p class="kf-footer__hint">رسالة واحدة كل شهر: ما أنجزناه، وما صرفناه، وقصة طفل واحد.</p>
      </div>
      <form class="kf-news" data-validate-form data-action="newsletter" data-source="footer"
            data-success-message="تم اشتراكك، ستصلك أول رسالة مطلع الشهر" novalidate>
        <div class="field flex-grow-1">
          <label class="visually-hidden" for="footerNewsEmail">البريد الإلكتروني</label>
          <input class="form-control form-control-lg" type="email" id="footerNewsEmail" name="email"
                 placeholder="بريدك الإلكتروني" autocomplete="email"
                 data-validate="required|email" aria-describedby="err-footerNews">
          <p class="field__error" id="err-footerNews" aria-live="polite"></p>
        </div>
        <button class="btn btn-cta btn-lg" type="submit">اشترك</button>
      </form>
    </div>

    <div class="row g-4 g-lg-5 kf-footer__grid">
      <div class="col-lg-4">
        <a class="kf-brand kf-brand--on-ink" href="index.html">
          <img src="assets/img/logo.svg" alt="" width="40" height="40">
          <span class="kf-brand__name"><span data-site="name">${escapeHtml(SITE.name)}</span><span class="kf-brand__tag" data-site="tagline">${escapeHtml(SITE.tagline)}</span></span>
        </a>
        <p class="kf-footer__about">منصة تربط بين من يريد أن يعطي ومن يستحق أن يُعطى، بشفافية موثّقة وتقارير دورية.</p>
        <div class="d-flex gap-2">${social}</div>
      </div>
      <nav class="col-7 col-md-4 col-lg-3" aria-labelledby="footerNavHeading">
        <h2 class="kf-footer__heading" id="footerNavHeading">تصفّح</h2>
        <ul class="kf-footer__links kf-footer__links--two">${links([{ label: 'الرئيسية', href: 'index.html' }, ...NAV_LINKS])}</ul>
      </nav>
      <nav class="col-5 col-md-4 col-lg-2" aria-labelledby="footerHelpHeading">
        <h2 class="kf-footer__heading" id="footerHelpHeading">مساعدة</h2>
        <ul class="kf-footer__links">${links(FOOTER_HELP)}</ul>
      </nav>
      <div class="col-md-4 col-lg-3">
        <h2 class="kf-footer__heading">تواصل معنا</h2>
        <ul class="kf-footer__contact">
          <li><span class="kf-footer__ico" aria-hidden="true"><i class="fa-solid fa-phone"></i></span><a href="tel:${escapeHtml(SITE.phoneHref)}" dir="ltr" data-site="phone">${escapeHtml(SITE.phone)}</a></li>
          <li><span class="kf-footer__ico" aria-hidden="true"><i class="fa-solid fa-envelope"></i></span><a href="mailto:${escapeHtml(SITE.email)}" data-site="email">${escapeHtml(SITE.email)}</a></li>
          <li><span class="kf-footer__ico" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span><span data-site="address">${escapeHtml(SITE.address)}</span></li>
        </ul>
      </div>
    </div>

    <div class="kf-footer__bottom">
      <p class="mb-0">© ${YEAR} منصة <span data-site="name">${escapeHtml(SITE.name)}</span>. جميع الحقوق محفوظة.</p>
      <nav class="d-flex flex-wrap gap-3" aria-label="روابط قانونية">
        <a href="transparency.html#child-policy">سياسة حماية الطفل</a>
        <a href="transparency.html">الخصوصية</a>
        <a href="faq.html">شروط الاستخدام</a>
      </nav>
      <div class="kf-footer__pay" role="img" aria-label="وسائل الدفع المقبولة: فيزا، ماستركارد، Apple Pay، والتحويل البنكي">
        <i class="fa-brands fa-cc-visa" aria-hidden="true"></i>
        <i class="fa-brands fa-cc-mastercard" aria-hidden="true"></i>
        <i class="fa-brands fa-apple-pay" aria-hidden="true"></i>
        <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
      </div>
    </div>
  </div>
</footer>`;
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
    link.href = 'dashboard.html';
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
  const footerSlot = $('[data-layout="footer"]');
  const page = currentPage();

  const bs = usesBootstrap();
  if (headerSlot) headerSlot.outerHTML = bs ? bsHeaderMarkup({ page }) : headerMarkup({ page });
  if (footerSlot) footerSlot.outerHTML = bs ? bsFooterMarkup() : footerMarkup();

  // درج Bootstrap يدير نفسه؛ القائمة القديمة تحتاج سلوكها
  if (!bs) initNavToggle();
  initStickyHeader();
  initAccountLink();
}

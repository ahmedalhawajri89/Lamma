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
import { $, currentPage, rafThrottle } from './utils.js';

const YEAR = new Date().getFullYear();

/** روابط الفوتر المساعدة. */
const FOOTER_HELP = [
  { label: 'الأسئلة الشائعة', href: 'faq.html' },
  { label: 'تقارير الشفافية', href: 'transparency.html' },
  { label: 'سياسة حماية الطفل', href: 'transparency.html#child-policy' },
  { label: 'حسابي', href: 'dashboard.html' },
];

function navMarkup(page) {
  return NAV_LINKS.map(({ label, href }) => {
    const active = href === page ? ' aria-current="page"' : '';
    return `<a class="nav__link" href="${href}"${active}>${label}</a>`;
  }).join('');
}

function headerMarkup({ page, solid }) {
  return `
<a class="skip-link" href="#main">تخطَّ إلى المحتوى الرئيسي</a>
<header class="site-header${solid ? ' site-header--solid' : ''}" id="siteHeader">
  <div class="container">
    <div class="header-shell">
      <a class="brand" href="index.html">
        <img class="brand__mark" src="assets/img/logo.svg" alt="" width="38" height="38">
        <span class="brand__name">${SITE.name}<span class="brand__tag">${SITE.tagline}</span></span>
      </a>

      <div class="site-nav" id="siteNav">
        <nav class="nav" aria-label="التنقل الرئيسي">${navMarkup(page)}</nav>
        <a class="btn btn--outline btn--sm" href="login.html">تسجيل الدخول</a>
        <a class="btn btn--primary btn--sm" href="orphans.html">
          اكفل الآن <i class="fa-solid fa-heart btn__icon" aria-hidden="true"></i>
        </a>
      </div>

      <div class="header-actions">
        <a class="btn btn--outline btn--sm" href="login.html">تسجيل الدخول</a>
        <a class="btn btn--primary btn--sm" href="orphans.html">
          اكفل الآن <i class="fa-solid fa-heart btn__icon" aria-hidden="true"></i>
        </a>
        <button class="nav-toggle" type="button" id="navToggle"
                aria-expanded="false" aria-controls="siteNav" aria-label="فتح قائمة التنقل">
          <span class="nav-toggle__bars" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>
      </div>
    </div>
  </div>
</header>`;
}

function footerMarkup() {
  const social = SITE.social
    .map(({ label, icon, url }) =>
      `<a href="${url}" aria-label="${label}" rel="noopener noreferrer" target="_blank">
         <i class="fa-brands ${icon}" aria-hidden="true"></i>
       </a>`)
    .join('');

  const quickLinks = NAV_LINKS
    .map(({ label, href }) => `<li><a href="${href}">${label}</a></li>`)
    .join('');

  const helpLinks = FOOTER_HELP
    .map(({ label, href }) => `<li><a href="${href}">${label}</a></li>`)
    .join('');

  return `
<footer class="site-footer">
  <span class="footer-watermark" aria-hidden="true">${SITE.name}</span>

  <div class="container">

    <div class="footer-top">
      <p class="footer-claim">
        كل شهر يمرّ بلا كافل هو شهرٌ لا يعود.
        <em>ابدأ اليوم بما تستطيع.</em>
      </p>

      <div class="footer-news">
        <p class="footer-news__label">
          رسالة واحدة كل شهر: ما أنجزناه، وما صرفناه، وقصة طفل واحد. بلا إلحاح.
        </p>
        <form class="newsletter" data-validate-form
              data-success-message="تم اشتراكك، ستصلك أول رسالة مطلع الشهر" novalidate>
          <div class="field" style="flex:1">
            <label class="sr-only" for="footerNewsEmail">البريد الإلكتروني</label>
            <div class="field__control">
              <input class="input" type="email" id="footerNewsEmail" name="email"
                     placeholder="بريدك الإلكتروني" autocomplete="email"
                     data-validate="required|email" aria-describedby="err-footerNews">
            </div>
            <p class="field__error" id="err-footerNews" role="alert"></p>
          </div>
          <button class="btn btn--primary" type="submit">اشترك</button>
        </form>
      </div>
    </div>

    <div class="site-footer__grid">
      <div>
        <a class="brand" href="index.html">
          <img class="brand__mark" src="assets/img/logo.svg" alt="" width="38" height="38">
          <span class="brand__name">${SITE.name}<span class="brand__tag">${SITE.tagline}</span></span>
        </a>
        <p class="footer-about">
          منصة تربط بين من يريد أن يعطي ومن يستحق أن يُعطى، بشفافية موثّقة وتقارير دورية.
          نعمل منذ ١٤ عاماً في ١٢ دولة.
        </p>
        <div class="footer-trust">
          <i class="fa-solid fa-file-shield" aria-hidden="true"></i>
          <span>
            <strong>مرخّصون ومدقّقون</strong>
            ميزانية سنوية معتمدة من مكتب مستقل ومنشورة للجميع
          </span>
        </div>
      </div>

      <nav aria-labelledby="footerNavHeading">
        <h2 class="footer-heading" id="footerNavHeading">تصفّح</h2>
        <ul class="footer-links" role="list">${quickLinks}</ul>
      </nav>

      <nav aria-labelledby="footerHelpHeading">
        <h2 class="footer-heading" id="footerHelpHeading">مساعدة</h2>
        <ul class="footer-links" role="list">${helpLinks}</ul>
      </nav>

      <div>
        <h2 class="footer-heading">تواصل معنا</h2>
        <ul class="footer-contact" role="list">
          <li>
            <i class="fa-solid fa-phone" aria-hidden="true"></i>
            <a href="tel:${SITE.phoneHref}" dir="ltr">${SITE.phone}</a>
          </li>
          <li>
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            <a href="mailto:${SITE.email}">${SITE.email}</a>
          </li>
          <li>
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
            <span>${SITE.address}</span>
          </li>
        </ul>
        <div class="social-row" style="margin-block-start:var(--space-5)">${social}</div>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© ${YEAR} منصة ${SITE.name} — جميع الحقوق محفوظة.</p>
      <nav aria-label="روابط قانونية">
        <a href="transparency.html#child-policy">سياسة حماية الطفل</a>
        <a href="transparency.html">الخصوصية</a>
        <a href="faq.html">شروط الاستخدام</a>
      </nav>
      <div class="payment-row" aria-label="وسائل الدفع المقبولة">
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
  if (!toggle || !nav) return;

  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'إغلاق قائمة التنقل' : 'فتح قائمة التنقل');
    nav.classList.toggle('is-open', open);
    document.body.style.overflow = open ? 'hidden' : '';
  };

  toggle.addEventListener('click', () => {
    setOpen(toggle.getAttribute('aria-expanded') !== 'true');
  });

  // الإغلاق عند اختيار رابط، أو الضغط على Escape، أو النقر خارج القائمة
  nav.addEventListener('click', (e) => {
    if (e.target.closest('a')) setOpen(false);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav.classList.contains('is-open')) {
      setOpen(false);
      toggle.focus();
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 1024 && nav.classList.contains('is-open')) setOpen(false);
  });
}

/** الهيدر يصبح زجاجياً بعد النزول قليلاً. */
function initStickyHeader() {
  const header = $('#siteHeader');
  if (!header || header.classList.contains('site-header--solid')) return;

  const update = rafThrottle(() => {
    header.classList.toggle('is-stuck', window.scrollY > 24);
  });

  update();
  window.addEventListener('scroll', update, { passive: true });
}

/**
 * تركيب هيكل الصفحة.
 * @param {{solid?: boolean}} options solid = هيدر معتم من البداية (الصفحات الداخلية)
 */
export function mountLayout({ solid = false } = {}) {
  const headerSlot = $('[data-layout="header"]');
  const footerSlot = $('[data-layout="footer"]');
  const page = currentPage();

  if (headerSlot) headerSlot.outerHTML = headerMarkup({ page, solid });
  if (footerSlot) footerSlot.outerHTML = footerMarkup();

  initNavToggle();
  initStickyHeader();
}

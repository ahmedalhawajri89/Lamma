/**
 * نقطة الدخول الوحيدة.
 *
 * يُحمّل كوحدة ES (<script type="module">)، فهو مؤجّل تلقائياً
 * ويعمل في نطاق خاص لا يلوّث window.
 *
 * المبدأ: كل وحدة تفحص وجود عناصرها بنفسها وتخرج بهدوء إن لم تجدها،
 * فيبقى هذا الملف واحداً لكل الصفحات بلا شروط متشعبة.
 */

import { hydrate } from './modules/data.js';
import { mountLayout, refreshLayout } from './modules/layout.js';
import { initThemeToggle } from './modules/theme.js';
import { initSearch } from './modules/search.js';
import { initHowSteps } from './modules/how.js';
import {
  initReveal,
  initCounters,
  initProgressBars,
  initDonuts,
  initFooterMark,
  initToTop,
  initActionBar,
} from './modules/motion.js';
import {
  initAccordions,
  initChipGroups,
  initPasswordToggles,
  initCharCounts,
  initToastStack,
} from './modules/ui.js';
import { initForms, initCardInput } from './modules/forms.js';
import { initCheckout, initThankYou } from './modules/checkout.js';
import { initImpactCalculator } from './modules/features.js';
import { renderHomeSections } from './modules/home.js';
import { initCatalog } from './modules/catalog.js';
import { initProfile } from './modules/profile.js';
import { initFaqPage } from './modules/faq.js';
import { initSignin } from './modules/signin.js';
import { initTransparency } from './modules/transparency.js';
import { initBlog } from './modules/blog.js';
import { initAbout } from './modules/about.js';
import { initCredentials } from './modules/proof.js';
import { initPortal } from './modules/portal.js';
import { initPartner } from './modules/partner.js';

function boot() {
  // ١) الهيكل مركَّب سلفاً من البذرة (انظر start)؛ تُكتب فيه الآن بيانات الخادم
  refreshLayout();

  // ٢) المحتوى المُولَّد من البيانات قبل تشغيل الحركات عليه
  renderHomeSections();
  initCredentials();
  initTransparency();
  initAbout();
  initCatalog();
  initProfile();
  initFaqPage();
  initSignin();
  initCheckout();
  initBlog();
  initThankYou();
  initPortal();
  initPartner();

  // ٣) مكونات الواجهة
  initAccordions();
  initHowSteps();
  initChipGroups();
  initPasswordToggles();
  initCharCounts();

  // ٤) النماذج
  initForms();
  initCardInput();
  initImpactCalculator();

  // ٥) الحركة أخيراً، بعد اكتمال DOM
  initReveal();
  initCounters();
  initProgressBars();
  initDonuts();
  initFooterMark();
  initToTop();
  initActionBar();
}

/**
 * الترطيب قبل الإقلاع.
 *
 * `boot()` يبني كل شيء من البيانات دفعةً واحدة، فلا بدّ أن تكون نهائية قبله.
 * وترطيبها بعده كان سيعني إعادة بناء الصفحة أمام الزائر — وميضٌ لا داعي له.
 *
 * و`hydrate()` لا ترمي استثناءً أبداً: تُرجع بذرتها عند أي إخفاق، فالإقلاع
 * مضمون في كل الأحوال.
 */
/**
 * قفل النماذج حتى يربطها JS.
 *
 * بين تحميل الـ HTML وانتهاء `hydrate()` تكون النماذج ظاهرة وقابلة للإرسال
 * بلا أن يكون معالجها موجوداً. ومن يكتب بسرعة ويضغط Enter عندئذ يُرسَل
 * نموذجه إرسالاً أصلياً: النموذج بلا `action` ولا `method`، فيتحوّل إلى
 * طلب GET إلى الصفحة نفسها **وكلمة المرور في شريط العنوان** — ومنه إلى
 * سجلّ المتصفح وسجلّات الخادم الوسيط.
 *
 * هذا المستمع يمنع ذلك بالتقاط الحدث في مرحلة capture قبل أي معالج آخر،
 * ويُزال فور اكتمال الإقلاع فلا يبقى له أثر.
 */
function lockForms() {
  let pending = null;

  const block = (event) => {
    if (!event.target.matches('form')) return;
    event.preventDefault();
    pending = event.target;
  };

  document.addEventListener('submit', block, true);

  return () => {
    document.removeEventListener('submit', block, true);

    // لا نبتلع نقرة المستخدم: نعيد إرسال النموذج الآن وقد صار له معالج.
    // `requestSubmit` لا `submit()` لأن الأخيرة تتخطى حدث submit كلياً
    // فتتخطى معه التحقق من الحقول.
    pending?.requestSubmit();
  };
}

async function start() {
  const unlock = lockForms();

  /*
   * الهيدر والفوتر قبل انتظار الشبكة: كانا يُبنيان بعد `hydrate()`، فتبقى
   * الصفحة حتى أربع ثوانٍ بلا ترويسة ولا تنقّل، ثم تقفز حين يظهران.
   * يُبنيان من البذرة، و`refreshLayout()` يكتب إعدادات الخادم عند وصولها.
   */
  try {
    mountLayout();
    initThemeToggle();
    initSearch();
    initToastStack();

    await hydrate();
    boot();
  } finally {
    unlock();

    // إشارة جاهزية للاختبارات ولأي نمط CSS يريد إخفاء حالة التحميل
    document.documentElement.dataset.ready = 'true';
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start, { once: true });
} else {
  start();
}

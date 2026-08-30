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
import { mountLayout } from './modules/layout.js';
import {
  initReveal,
  initCounters,
  initProgressBars,
  initDonuts,
  initTilt,
  initWordReveal,
  initToTop,
  initActionBar,
} from './modules/motion.js';
import { initSliders } from './modules/slider.js';
import { initHeroSlider } from './modules/hero.js';
import {
  initAccordions,
  initTabs,
  initModals,
  initChipGroups,
  initPasswordToggles,
} from './modules/ui.js';
import { initForms, initCardInput, initPasswordStrength } from './modules/forms.js';
import {
  initImpactCalculator,
  initOrphanFilters,
  initOrphanDetail,
  initCheckoutSummary,
  initThankYou,
  initPostDetail,
  initPostsPager,
  initResetPassword,
} from './modules/features.js';
import { renderHomeSections } from './modules/home.js';
import { initProofBoard, initCredentials, initFinanceBoard } from './modules/proof.js';
import { initPortal } from './modules/portal.js';

function boot() {
  // ١) الهيكل أولاً — بقية الوحدات قد تحتاج عناصره
  mountLayout({ solid: document.body.dataset.header === 'solid' });

  // ٢) المحتوى المُولَّد من البيانات قبل تشغيل الحركات عليه
  renderHomeSections();
  initProofBoard();
  initCredentials();
  initFinanceBoard();
  initOrphanFilters();
  initOrphanDetail();
  initCheckoutSummary();
  initPostDetail();
  initPostsPager();
  initResetPassword();
  initThankYou();
  initPortal();

  // ٣) مكونات الواجهة
  initAccordions();
  initTabs();
  initModals();
  initChipGroups();
  initPasswordToggles();
  initHeroSlider();
  initSliders();

  // ٤) النماذج
  initForms();
  initCardInput();
  initPasswordStrength();
  initImpactCalculator();

  // ٥) الحركة أخيراً، بعد اكتمال DOM
  initWordReveal();
  initReveal();
  initCounters();
  initProgressBars();
  initDonuts();
  initTilt();
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

  try {
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

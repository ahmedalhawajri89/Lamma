/**
 * نقطة الدخول الوحيدة.
 *
 * يُحمّل كوحدة ES (<script type="module">)، فهو مؤجّل تلقائياً
 * ويعمل في نطاق خاص لا يلوّث window.
 *
 * المبدأ: كل وحدة تفحص وجود عناصرها بنفسها وتخرج بهدوء إن لم تجدها،
 * فيبقى هذا الملف واحداً لكل الصفحات بلا شروط متشعبة.
 */

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
import { initSliders, initMarquee } from './modules/slider.js';
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
} from './modules/features.js';
import { renderHomeSections } from './modules/home.js';

function boot() {
  // ١) الهيكل أولاً — بقية الوحدات قد تحتاج عناصره
  mountLayout({ solid: document.body.dataset.header === 'solid' });

  // ٢) المحتوى المُولَّد من البيانات قبل تشغيل الحركات عليه
  renderHomeSections();
  initOrphanFilters();
  initOrphanDetail();
  initCheckoutSummary();
  initThankYou();

  // ٣) مكونات الواجهة
  initAccordions();
  initTabs();
  initModals();
  initChipGroups();
  initPasswordToggles();
  initHeroSlider();
  initSliders();
  initMarquee();

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

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}

/**
 * طبقة الحركة: كشف عند التمرير، عدّادات، أشرطة تقدم، حلقة الشفافية،
 * ميلان القوس مع الفأرة، وشريط تقدم القراءة.
 *
 * كل شيء هنا يحترم prefers-reduced-motion، ويعتمد IntersectionObserver
 * بدل الاستماع المستمر لحدث scroll حفاظاً على الأداء.
 */

import { $, $$, observeOnce, prefersReducedMotion, rafThrottle, clamp, formatNumber, presetCount } from './utils.js';

/* -------------------------------------------------------------------------
   1. الكشف عند التمرير
   ------------------------------------------------------------------------- */
export function initReveal() {
  const items = $$('[data-reveal]');
  if (!items.length) return;

  if (prefersReducedMotion()) {
    items.forEach((el) => el.classList.add('is-visible'));
    return;
  }

  // تدرّج زمني تلقائي للعناصر المتجاورة داخل نفس الأب
  items.forEach((el) => {
    if (el.dataset.revealDelay) {
      el.style.setProperty('--reveal-delay', `${el.dataset.revealDelay}ms`);
    }
  });

  observeOnce(items, (el) => el.classList.add('is-visible'));
}

/* -------------------------------------------------------------------------
   2. العدّادات الرقمية
   ------------------------------------------------------------------------- */
const counterText = (el) => formatNumber(Number(el.dataset.count) || 0) + (el.dataset.suffix || '');

function runCounter(el) {
  const target = Number(el.dataset.count) || 0;
  const suffix = el.dataset.suffix || '';
  const duration = Number(el.dataset.duration) || 1600;
  const vis = presetCount(el, counterText(el));

  if (prefersReducedMotion()) {
    vis.textContent = formatNumber(target) + suffix;
    return;
  }

  const start = performance.now();

  const tick = (now) => {
    const progress = clamp((now - start) / duration, 0, 1);
    // تباطؤ في النهاية يجعل الرقم يستقر بدل أن يتوقف فجأة
    const eased = 1 - Math.pow(1 - progress, 4);
    vis.textContent = formatNumber(Math.round(target * eased)) + suffix;
    if (progress < 1) requestAnimationFrame(tick);
  };

  requestAnimationFrame(tick);
}

export function initCounters() {
  const counters = $$('[data-count]');
  // الرقم النهائي للقارئ الصوتي قبل أن يظهر العدّاد على الشاشة
  counters.forEach((el) => presetCount(el, counterText(el)));
  observeOnce(counters, runCounter, { threshold: 0.4 });
}

/* -------------------------------------------------------------------------
   3. أشرطة التقدم
   ------------------------------------------------------------------------- */
export function initProgressBars() {
  const bars = $$('.progress');
  bars.forEach((bar) => {
    const value = bar.dataset.value;
    if (value) bar.style.setProperty('--value', `${clamp(Number(value), 0, 100)}%`);
  });
  observeOnce(bars, (el) => el.classList.add('is-visible'), { threshold: 0.3 });
}

/* -------------------------------------------------------------------------
   4. حلقة توزيع الإنفاق (SVG donut)
   ------------------------------------------------------------------------- */
export function initDonuts() {
  observeOnce($$('.donut'), (el) => el.classList.add('is-visible'), { threshold: 0.3 });
}

/**
 * اسم الموقع الضخم في الفوتر: يُكتب مرةً واحدة حين يظهر منه شيء.
 * الحركة كلها في footer.css، وهنا الإشارة وحدها؛ وتقليل الحركة يُسقطها هناك.
 */
export function initFooterMark() {
  observeOnce($$('.kf-footer__mark'), (el) => el.classList.add('is-visible'), { threshold: 0.2, rootMargin: '0px' });
}

/* -------------------------------------------------------------------------
   7. زر العودة للأعلى + حلقة تقدم القراءة
   ------------------------------------------------------------------------- */
export function initToTop() {
  const btn = $('#toTop');
  const root = document.documentElement;
  const header = $('#siteHeader');

  const update = rafThrottle(() => {
    const scrollable = root.scrollHeight - window.innerHeight;
    const progress = scrollable > 0 ? window.scrollY / scrollable : 0;
    // على الهيدر لا الجذر: متغيّرٌ موروث يتبدّل على الجذر مع كل إطار يعيد
    // حساب أنماط الصفحة كلها، وخط التقدّم وحده من يقرؤه
    header?.style.setProperty('--scroll-progress', progress.toFixed(3));
    btn?.classList.toggle('is-visible', window.scrollY > 600);
  });

  update();
  window.addEventListener('scroll', update, { passive: true });

  btn?.addEventListener('click', () => {
    window.scrollTo({
      top: 0,
      behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
  });
}

/* -------------------------------------------------------------------------
   8. شريط الإجراء السفلي على الجوال
   يظهر بعد تجاوز الهيرو، ويختفي حين يكون زر «اكفل» ظاهراً أصلاً على الشاشة
   (الهيرو والخاتمة والفوتر)، فلا يتزاحم زرّان للفعل نفسه.
   ------------------------------------------------------------------------- */
export function initActionBar() {
  const bar = $('#actionBar');
  if (!bar) return;

  // ومعها لوحة الكفالة في صفحة الطفل: زرّها ظاهر فلا يتكرر تحته
  const zones = $$('.hero, .closing, .site-footer, .kf-footer, .kid-panel');
  const inView = new Set();

  const update = rafThrottle(() => {
    const pastHero = window.scrollY > window.innerHeight * 0.7;
    bar.classList.toggle('is-visible', pastHero && inView.size === 0);
  });

  if ('IntersectionObserver' in window && zones.length) {
    const watch = new IntersectionObserver((entries) => {
      entries.forEach((e) => (e.isIntersecting ? inView.add(e.target) : inView.delete(e.target)));
      update();
    }, { threshold: 0.15 });
    zones.forEach((zone) => watch.observe(zone));
  }

  update();
  window.addEventListener('scroll', update, { passive: true });
}

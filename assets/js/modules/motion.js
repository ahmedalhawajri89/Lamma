/**
 * طبقة الحركة: كشف عند التمرير، عدّادات، أشرطة تقدم، حلقة الشفافية،
 * ميلان القوس مع الفأرة، وشريط تقدم القراءة.
 *
 * كل شيء هنا يحترم prefers-reduced-motion، ويعتمد IntersectionObserver
 * بدل الاستماع المستمر لحدث scroll حفاظاً على الأداء.
 */

import { $, $$, observeOnce, prefersReducedMotion, rafThrottle, clamp, formatNumber } from './utils.js';

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
function runCounter(el) {
  const target = Number(el.dataset.count) || 0;
  const suffix = el.dataset.suffix || '';
  const duration = Number(el.dataset.duration) || 1600;

  if (prefersReducedMotion()) {
    el.textContent = formatNumber(target) + suffix;
    return;
  }

  const start = performance.now();

  const tick = (now) => {
    const progress = clamp((now - start) / duration, 0, 1);
    // تباطؤ في النهاية يجعل الرقم يستقر بدل أن يتوقف فجأة
    const eased = 1 - Math.pow(1 - progress, 4);
    el.textContent = formatNumber(Math.round(target * eased)) + suffix;
    if (progress < 1) requestAnimationFrame(tick);
  };

  requestAnimationFrame(tick);
}

export function initCounters() {
  observeOnce($$('[data-count]'), runCounter, { threshold: 0.4 });
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

/* -------------------------------------------------------------------------
   5. ميلان لطيف للقوس مع حركة الفأرة
   يعمل على المؤشر فقط — لا معنى له باللمس، ويُعطَّل مع تقليل الحركة.
   ------------------------------------------------------------------------- */
export function initTilt() {
  if (prefersReducedMotion()) return;
  if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

  $$('[data-tilt]').forEach((stage) => {
    const target = $('[data-tilt-target]', stage) || stage;
    const max = Number(stage.dataset.tilt) || 5;

    const onMove = rafThrottle((e) => {
      const rect = stage.getBoundingClientRect();
      const px = (e.clientX - rect.left) / rect.width - 0.5;
      const py = (e.clientY - rect.top) / rect.height - 0.5;
      target.style.transform =
        `perspective(1000px) rotateY(${px * max * 2}deg) rotateX(${-py * max * 2}deg)`;
    });

    stage.addEventListener('pointermove', onMove);
    stage.addEventListener('pointerleave', () => {
      target.style.transform = '';
    });
  });
}

/* -------------------------------------------------------------------------
   6. كشف العنوان كلمةً كلمة
   نُقسّم النص إلى كلمات ونلفّ كلاً منها بـ span، مع الحفاظ على
   العناصر الداخلية (مثل span التمييز) كما هي.
   ------------------------------------------------------------------------- */
export function initWordReveal() {
  if (prefersReducedMotion()) return;

  $$('[data-words]').forEach((el) => {
    let index = 0;

    const wrapTextNode = (node) => {
      const words = node.textContent.split(/(\s+)/);
      const fragment = document.createDocumentFragment();

      words.forEach((word) => {
        if (!word.trim()) {
          fragment.appendChild(document.createTextNode(word));
          return;
        }
        const span = document.createElement('span');
        span.className = 'reveal-word';
        span.style.setProperty('--word-index', index++);
        span.textContent = word;
        fragment.appendChild(span);
      });

      node.replaceWith(fragment);
    };

    // نمر على الأبناء المباشرين: النصوص تُقسّم، والعناصر تُعامل ككتلة واحدة
    [...el.childNodes].forEach((node) => {
      if (node.nodeType === Node.TEXT_NODE) {
        wrapTextNode(node);
      } else if (node.nodeType === Node.ELEMENT_NODE) {
        node.classList.add('reveal-word');
        node.style.setProperty('--word-index', index++);
      }
    });
  });
}

/* -------------------------------------------------------------------------
   7. زر العودة للأعلى + حلقة تقدم القراءة
   ------------------------------------------------------------------------- */
export function initToTop() {
  const btn = $('#toTop');
  const root = document.documentElement;

  const update = rafThrottle(() => {
    const scrollable = root.scrollHeight - window.innerHeight;
    const progress = scrollable > 0 ? window.scrollY / scrollable : 0;
    // يُكتب على الجذر فيستفيد منه خط تقدّم القراءة في الهيدر أيضاً
    root.style.setProperty('--scroll-progress', progress.toFixed(3));
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
   يظهر بعد تجاوز الهيرو حتى لا يزاحم زر "اكفل الآن" الرئيسي.
   ------------------------------------------------------------------------- */
export function initActionBar() {
  const bar = $('#actionBar');
  if (!bar) return;

  const update = rafThrottle(() => {
    bar.classList.toggle('is-visible', window.scrollY > window.innerHeight * 0.7);
  });

  update();
  window.addEventListener('scroll', update, { passive: true });
}

/**
 * «كيف تبدأ الكفالة»: تبويبات أفقية فوق مسرح ثابت الارتفاع.
 *
 * اللوحات الثلاث مكدّسة في خلية واحدة وتبقى كلها في التخطيط، فلا يتغيّر
 * ارتفاع القسم عند التبديل ولا يتحرّك ما فوقه وما تحته. اللوحة غير النشطة
 * مخفية بصرياً (CSS) وعن القارئ الصوتي ولوحة المفاتيح (`inert`).
 *
 * العرض التلقائي يدور (١ ثم ٢ ثم ٣ ثم ١) ما دام القسم ظاهراً، وخط التبويب
 * النشط يمتلئ كمؤقّت. يتوقف مؤقتاً عند مرور المؤشر أو خروج القسم من الشاشة،
 * ونهائياً حين يختار الزائر خطوة بنفسه: صار هو المتحكّم (WCAG 2.2.2).
 */

import { $, $$, prefersReducedMotion } from './utils.js';

const STEP_MS = 4200; // مدة كل خطوة في العرض التلقائي

export function initHowSteps() {
  const root = $('[data-how]');
  if (!root) return;

  const tabs = $$('[role="tab"]', root);
  const panels = tabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls')));
  const reduced = prefersReducedMotion();

  let current = 0;
  let timer = null;
  let stopped = reduced;      // أوقفه الزائر نهائياً
  let visible = false;        // القسم على الشاشة
  let hovering = false;       // المؤشر فوق القسم

  const select = (index, { focus = false } = {}) => {
    current = index;

    tabs.forEach((tab, i) => {
      const active = i === index;
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
    });

    panels.forEach((panel, i) => {
      const active = i === index;
      panel.classList.toggle('is-active', active);
      panel.toggleAttribute('inert', !active);
      if (active) panel.removeAttribute('aria-hidden');
      else panel.setAttribute('aria-hidden', 'true');
    });

    if (focus) tabs[index].focus();
  };

  /** المؤقّت المرئي: خط التبويب النشط يمتلئ خلال مدة الخطوة. */
  const showTimer = (on) => {
    tabs.forEach((tab, i) => {
      tab.classList.remove('is-timing');
      if (on && i === current) {
        void tab.offsetWidth; // يعيد تشغيل الحركة من الصفر
        tab.classList.add('is-timing');
      }
    });
  };

  const pause = () => {
    clearTimeout(timer);
    timer = null;
    showTimer(false);
  };

  const play = () => {
    pause();
    if (stopped || !visible || hovering) return;
    showTimer(true);
    timer = setTimeout(() => {
      select((current + 1) % tabs.length);
      play();
    }, STEP_MS);
  };

  /** اختيار الزائر بنفسه يوقف الدوران نهائياً. */
  const takeOver = () => {
    stopped = true;
    pause();
  };

  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => {
      takeOver();
      select(i);
    });

    tab.addEventListener('keydown', (e) => {
      const step = { ArrowLeft: 1, ArrowDown: 1, ArrowRight: -1, ArrowUp: -1 }[e.key];
      if (step === undefined) return;
      e.preventDefault();
      takeOver();
      select((current + step + tabs.length) % tabs.length, { focus: true });
    });
  });

  // التركيز بلوحة المفاتيح داخل القسم يعني أن الزائر يقرأ بنفسه
  root.addEventListener('focusin', takeOver);
  root.addEventListener('pointerenter', () => { hovering = true; pause(); });
  root.addEventListener('pointerleave', () => { hovering = false; play(); });

  if (reduced || !('IntersectionObserver' in window)) return;

  root.style.setProperty('--how-step', `${STEP_MS}ms`);

  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (visible) play();
    else pause();
  }, { threshold: 0.4 }).observe(root);
}

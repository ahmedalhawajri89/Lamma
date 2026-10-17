/**
 * «كيف تبدأ الكفالة»: ثلاث خطوات مرقّمة بجانب شاشة هاتف تتبعها.
 *
 * كل خطوة زرٌّ يفتح شرحها (aria-expanded)، وواحدة مفتوحة دائماً. والهاتف
 * زينة توضيحية (aria-hidden في الصفحة) تُظهر شاشة الخطوة المفتوحة.
 *
 * كانت تبويبات تدور وحدها كل أربع ثوانٍ. ui-ux-pro-max لا يوصي بمحتوى يدور
 * تلقائياً، وقاعدة المشروع أن الحركة لا تتكرر: الزائر يختار بنفسه.
 */

import { $, $$ } from './utils.js';

export function initHowSteps() {
  const root = $('[data-how]');
  if (!root) return;

  const buttons = $$('.how-step__tab', root);
  const screens = $$('.how__panel', root);
  if (!buttons.length) return;

  const select = (index) => {
    buttons.forEach((button, i) => {
      const open = i === index;
      button.setAttribute('aria-expanded', String(open));
      button.closest('.how-step')?.classList.toggle('is-open', open);
    });
    screens.forEach((screen, i) => screen.classList.toggle('is-active', i === index));
    // الحلقات خلف الهاتف تدور قليلاً مع كل خطوة (home.css: .how-orbit__ring)
    root.style.setProperty('--how-i', index);
  };

  buttons.forEach((button, i) => button.addEventListener('click', () => select(i)));

  select(Math.max(0, buttons.findIndex((b) => b.getAttribute('aria-expanded') === 'true')));
}

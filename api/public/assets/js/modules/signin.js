/**
 * صفحات الحساب: ما فوق forms.js.
 *
 *  · الدخول واستعادة كلمة المرور في بطاقةٍ واحدة تتبدّل ([data-acc-view])،
 *    والبريد المكتوب ينتقل من الدخول إلى الاستعادة. كانت نافذةً منبثقة.
 *  · بعد طلب الرابط حالة «تحقّق من بريدك» بجملةٍ لا تكشف إن كان للبريد حساب.
 *  · تنبيه Caps Lock تحت حقل كلمة المرور: أكثر سبب لـ«كلمة المرور خاطئة».
 */

import { $, $$ } from './utils.js';

export function initSignin() {
  initViews();
  initCapsLock();
}

function initViews() {
  const views = $$('[data-acc-view]');
  if (!views.length) return;

  const show = (name, focus = true) => {
    views.forEach((v) => { v.hidden = v.dataset.accView !== name; });
    const view = views.find((v) => v.dataset.accView === name);
    if (focus) (view?.querySelector('input:not([type=hidden]), [tabindex="-1"], h1'))?.focus();
  };

  $$('[data-acc-go]').forEach((btn) => btn.addEventListener('click', () => {
    const to = btn.dataset.accGo;
    // البريد المكتوب في الدخول يُنقل إلى الاستعادة، فلا يكتبه مرتين
    if (to === 'forgot') {
      const typed = $('#email')?.value.trim();
      const target = $('#resetEmail');
      if (typed && target && !target.value) target.value = typed;
    }
    show(to);
  }));

  // forms.js يُطلق form:sent بعد طلب الرابط: نعرض حالتنا بدل الإشعار
  $('[data-action="forgot"]')?.addEventListener('form:sent', (e) => {
    e.preventDefault();
    $$('[data-acc-email]').forEach((el) => { el.textContent = e.detail.email; });
    show('sent');
  });
}

function initCapsLock() {
  $$('[data-caps-for]').forEach((hint) => {
    const input = document.getElementById(hint.dataset.capsFor);
    if (!input) return;
    const check = (e) => { if (e.getModifierState) hint.hidden = !e.getModifierState('CapsLock'); };
    input.addEventListener('keydown', check);
    input.addEventListener('keyup', check);
    input.addEventListener('blur', () => { hint.hidden = true; });
  });
}

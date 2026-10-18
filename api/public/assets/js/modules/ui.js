/**
 * مكونات الواجهة التفاعلية: الأكورديون، والتنبيهات، ومجموعات الاختيار.
 * والنوافذ من Bootstrap (portal.js)، فلا نظام نوافذ خاصاً هنا.
 *
 * كل مكون يدير سمات ARIA بنفسه، ويعمل بالكيبورد كاملاً.
 */

import { $, $$ } from './utils.js';
import { t } from './i18n.js';

/* =========================================================================
   الأكورديون
   ========================================================================= */
export function initAccordions() {
  $$('[data-accordion]').forEach((accordion) => {
    const single = accordion.dataset.accordion === 'single';
    const triggers = $$('.accordion__trigger', accordion);

    triggers.forEach((trigger) => {
      trigger.addEventListener('click', () => {
        const item = trigger.closest('.accordion__item');
        const willOpen = trigger.getAttribute('aria-expanded') !== 'true';

        // في الوضع المفرد نغلق البقية أولاً
        if (single && willOpen) {
          triggers.forEach((other) => {
            if (other === trigger) return;
            other.setAttribute('aria-expanded', 'false');
            other.closest('.accordion__item').classList.remove('is-open');
          });
        }

        trigger.setAttribute('aria-expanded', String(willOpen));
        item.classList.toggle('is-open', willOpen);
      });
    });
  });
}

/* =========================================================================
   التنبيهات — بديل مهذّب لـ alert()
   ========================================================================= */
const TOAST_ICONS = {
  success: 'fa-check',
  error: 'fa-triangle-exclamation',
  info: 'fa-circle-info',
};

/**
 * منطقة الإعلان الحيّ للإشعارات.
 *
 * تُنشأ عند الإقلاع لا مع أول إشعار: منطقةٌ تُنشأ وتُملأ في اللحظة نفسها
 * يتجاهلها كثير من القارئات الصوتية، فيضيع أول إشعار (وغالباً هو الخطأ).
 */
export function initToastStack() {
  let stack = $('#toastStack');
  if (stack) return stack;

  stack = document.createElement('div');
  stack.id = 'toastStack';
  stack.className = 'toast-stack';
  stack.setAttribute('role', 'status');
  stack.setAttribute('aria-live', 'polite');
  document.body.appendChild(stack);
  return stack;
}

/**
 * إشعار عابر.
 *
 * الخطأ يبقى ثماني ثوانٍ لا أربعاً (يُقرأ ويُفهم قبل أن يختفي)، ولكل إشعار
 * زرّ إغلاق. ولا يأخذ التركيز: يُعلَن عبر المنطقة الحيّة وحدها.
 */
export function toast(message, type = 'success', duration = type === 'error' ? 8000 : 4000) {
  const fresh = !$('#toastStack');
  const stack = initToastStack();

  const el = document.createElement('div');
  el.className = `toast toast--${type}`;

  const icon = document.createElement('span');
  icon.className = 'toast__icon';
  icon.innerHTML = `<i class="fa-solid ${TOAST_ICONS[type] || TOAST_ICONS.info}" aria-hidden="true"></i>`;

  const text = document.createElement('span');
  text.className = 'toast__text';
  text.textContent = message; // نص فقط — لا حقن HTML

  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'toast__close';
  close.setAttribute('aria-label', t('إغلاق الإشعار'));
  close.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

  let timer = null;
  const dismiss = () => {
    clearTimeout(timer);
    el.classList.add('is-leaving');
    // بلا حركة خروج (تقليل الحركة) لا يأتي animationend أبداً، فيُزال فوراً
    if (!el.getAnimations().length) {
      el.remove();
      return;
    }
    el.addEventListener('animationend', () => el.remove(), { once: true });
  };
  close.addEventListener('click', dismiss);

  el.append(icon, text, close);
  // منطقةٌ أُنشئت للتو تحتاج لحظة قبل أن تُعلن ما يُضاف إليها
  if (fresh) setTimeout(() => stack.appendChild(el), 150);
  else stack.appendChild(el);

  timer = setTimeout(dismiss, duration);
}

/* =========================================================================
   مجموعات الاختيار (الشرائح)
   ========================================================================= */
export function initChipGroups() {
  $$('[data-chips]').forEach((group) => {
    const chips = $$('.chip', group);

    chips.forEach((chip) => {
      chip.setAttribute('aria-pressed', String(chip.classList.contains('is-active')));

      chip.addEventListener('click', () => {
        chips.forEach((other) => {
          other.classList.toggle('is-active', other === chip);
          other.setAttribute('aria-pressed', String(other === chip));
        });

        group.dispatchEvent(new CustomEvent('chip:change', {
          detail: { value: chip.dataset.value },
          bubbles: true,
        }));
      });
    });
  });
}

/* =========================================================================
   عدّاد أحرف الحقل النصي: [data-count] داخل وصف الحقل (aria-describedby)
   يتبع طوله، فيعرف الكاتب كم بقي على الحدّ الأدنى قبل أن يُرفض.
   ========================================================================= */
export function initCharCounts() {
  $$('textarea[aria-describedby]').forEach((field) => {
    const out = field.getAttribute('aria-describedby').split(' ')
      .map((id) => document.getElementById(id)?.querySelector('[data-count]'))
      .find(Boolean);
    if (!out) return;
    const sync = () => { out.textContent = String(field.value.trim().length); };
    field.addEventListener('input', sync);
    field.form?.addEventListener('reset', () => requestAnimationFrame(sync));
    sync();
  });
}

/* =========================================================================
   إظهار/إخفاء كلمة المرور
   ========================================================================= */
export function initPasswordToggles() {
  $$('[data-password-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.passwordToggle);
      if (!input) return;

      // اسمٌ ثابت وحالة مضغوطة: تغيير الاسم والحالة معاً يُقرأ متناقضاً
      const revealing = input.type === 'password';
      input.type = revealing ? 'text' : 'password';
      btn.setAttribute('aria-label', t('إظهار كلمة المرور'));
      btn.setAttribute('aria-pressed', String(revealing));
      btn.innerHTML = `<i class="fa-regular ${revealing ? 'fa-eye-slash' : 'fa-eye'}" aria-hidden="true"></i>`;
    });
  });
}

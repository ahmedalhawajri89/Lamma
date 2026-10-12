/**
 * مكونات الواجهة التفاعلية: الأكورديون، التبويبات، النوافذ المنبثقة،
 * التنبيهات، ومجموعات الاختيار.
 *
 * كل مكون يدير سمات ARIA بنفسه، ويعمل بالكيبورد كاملاً.
 */

import { $, $$ } from './utils.js';

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
   التبويبات
   ========================================================================= */
export function initTabs() {
  $$('[data-tabs]').forEach((group) => {
    const tabs = $$('[role="tab"]', group);
    const panels = tabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls')));

    const select = (index) => {
      tabs.forEach((tab, i) => {
        const active = i === index;
        tab.setAttribute('aria-selected', String(active));
        tab.tabIndex = active ? 0 : -1;
        if (panels[i]) panels[i].hidden = !active;
      });
    };

    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => select(index));

      // الأسهم تتنقل بين التبويبات — سلوك ARIA القياسي
      tab.addEventListener('keydown', (e) => {
        // في RTL اليسار يعني «التالي»، والأسفل كذلك في القوائم العمودية
        const forward = e.key === 'ArrowLeft' || e.key === 'ArrowDown';
        const back = e.key === 'ArrowRight' || e.key === 'ArrowUp';
        if (!forward && !back) return;

        e.preventDefault();
        const next = (index + (forward ? 1 : -1) + tabs.length) % tabs.length;
        select(next);
        tabs[next].focus();
      });
    });
  });
}

/* =========================================================================
   النوافذ المنبثقة — مع حبس التركيز وإعادته لمصدره
   ========================================================================= */
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])';

let lastFocused = null;

function trapFocus(modal, event) {
  const focusable = $$(FOCUSABLE, modal).filter((el) => el.offsetParent !== null);
  if (!focusable.length) return;

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

export function openModal(id) {
  const modal = document.getElementById(id);
  if (!modal) return;

  lastFocused = document.activeElement;
  modal.classList.add('is-open');
  modal.removeAttribute('aria-hidden');
  document.body.style.overflow = 'hidden';

  // التركيز يُؤجَّل إطاراً واحداً: النافذة ما زالت visibility: hidden لحظة
  // إضافة الصنف، وعنصر مخفي لا يقبل التركيز.
  requestAnimationFrame(() => {
    const target = $('[data-autofocus]', modal) || $(FOCUSABLE, modal);
    target?.focus();
  });
}

export function closeModal(modal) {
  if (!modal) return;
  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  lastFocused?.focus();
  lastFocused = null;
}

export function initModals() {
  $$('[data-modal-open]').forEach((trigger) => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      openModal(trigger.dataset.modalOpen);
    });
  });

  $$('.modal').forEach((modal) => {
    modal.setAttribute('aria-hidden', 'true');

    $$('[data-modal-close]', modal).forEach((btn) => {
      btn.addEventListener('click', () => closeModal(modal));
    });

    $('.modal__backdrop', modal)?.addEventListener('click', () => closeModal(modal));

    modal.addEventListener('keydown', (e) => {
      if (e.key === 'Tab') trapFocus(modal, e);
    });
  });

  // Escape يُلتقط على مستوى المستند لا النافذة، فيعمل حتى لو خرج التركيز منها
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = $('.modal.is-open');
    if (open) closeModal(open);
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

export function toast(message, type = 'success', duration = 4000) {
  let stack = $('#toastStack');

  if (!stack) {
    stack = document.createElement('div');
    stack.id = 'toastStack';
    stack.className = 'toast-stack';
    // القارئ الصوتي يعلن الرسائل الجديدة تلقائياً
    stack.setAttribute('role', 'status');
    stack.setAttribute('aria-live', 'polite');
    document.body.appendChild(stack);
  }

  const el = document.createElement('div');
  el.className = `toast toast--${type}`;

  const icon = document.createElement('span');
  icon.className = 'toast__icon';
  icon.innerHTML = `<i class="fa-solid ${TOAST_ICONS[type] || TOAST_ICONS.info}"></i>`;

  const text = document.createElement('span');
  text.textContent = message; // نص فقط — لا حقن HTML

  el.append(icon, text);
  stack.appendChild(el);

  setTimeout(() => {
    el.classList.add('is-leaving');
    el.addEventListener('animationend', () => el.remove(), { once: true });
  }, duration);
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
   إظهار/إخفاء كلمة المرور
   ========================================================================= */
export function initPasswordToggles() {
  $$('[data-password-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.passwordToggle);
      if (!input) return;

      const revealing = input.type === 'password';
      input.type = revealing ? 'text' : 'password';
      btn.setAttribute('aria-label', revealing ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
      btn.innerHTML = `<i class="fa-regular ${revealing ? 'fa-eye-slash' : 'fa-eye'}"></i>`;
    });
  });
}

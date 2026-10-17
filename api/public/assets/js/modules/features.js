/**
 * المميزات التفاعلية الخاصة بالمشروع:
 * حاسبة الأثر، والاحتفال، واستعادة كلمة المرور. صفحتا الطفل والمقال
 * يرسمهما الخادم الآن (OrphanPageController وPostPageController).
 */

import { $, formatNumber, escapeHtml } from './utils.js';
import { IMPACT_TIERS, PROGRAMS } from './data.js';

/* =========================================================================
   1. حاسبة الأثر
   يسحب المستخدم المبلغ فيرى فوراً ما الذي يوفّره — يحوّل الرقم المجرد
   إلى أثر ملموس، وهو ما يصنع القرار.
   ========================================================================= */
export function initImpactCalculator() {
  const range = $('#impactRange');
  const output = $('#impactAmount');
  const list = $('#impactList');
  if (!range || !output || !list) return;

  const render = () => {
    const amount = Number(range.value);

    output.textContent = formatNumber(amount);

    // تلوين الجزء المملوء من الشريط (لمتصفحات WebKit)
    const percent = ((amount - range.min) / (range.max - range.min)) * 100;
    range.style.setProperty('--fill', `${percent}%`);

    const unlocked = IMPACT_TIERS.filter((tier) => amount >= tier.min);

    list.innerHTML = unlocked.map((tier) => `
      <li>
        <span class="impact-list__icon"><i class="fa-solid ${escapeHtml(tier.icon)}" aria-hidden="true"></i></span>
        <span class="impact-list__text">
          <strong>${escapeHtml(tier.title)}</strong>
          ${escapeHtml(tier.text)}
        </span>
      </li>`).join('');

    // نبضة قصيرة على آخر عنصر أُضيف لتوضيح ما تغيّر
    list.lastElementChild?.classList.add('is-updated');

    // البرنامج الأقرب للمبلغ المختار
    const suggestion = $('#impactSuggestion');
    if (suggestion) {
      const match = [...PROGRAMS].reverse().find((p) => amount >= p.price);
      suggestion.textContent = match
        ? `هذا المبلغ يغطي «${match.name}» بالكامل.`
        : 'كل مبلغ يصنع فرقاً، مهما بدا صغيراً.';
    }
  };

  range.addEventListener('input', render);
  render();
}

/* =========================================================================
   2. فلترة الأيتام: انتقلت إلى catalog.js (فلترة وتصفيح على الخادم)
   ========================================================================= */

/* =========================================================================
   3. صفحة الطفل: يرسمها الخادم الآن (OrphanPageController)، وما بقي لها في profile.js
   ========================================================================= */

/** قصاصات احتفال خفيفة — تُزال من DOM بعد انتهائها. */
export function launchConfetti() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const host = $('.thanks');
  if (!host) return;

  // ألوان المخططات نفسها — تتبع الهوية والوضع الليلي بلا قائمة مكرّرة هنا
  const colors = [1, 2, 3, 4, 5].map((n) => `var(--chart-${n})`);

  for (let i = 0; i < 40; i += 1) {
    const bit = document.createElement('span');
    bit.className = 'confetti';
    bit.style.insetInlineStart = `${Math.random() * 100}%`;
    bit.style.background = colors[i % colors.length];
    bit.style.animationDuration = `${2.5 + Math.random() * 2}s`;
    bit.style.animationDelay = `${Math.random() * 1.2}s`;
    bit.addEventListener('animationend', () => bit.remove(), { once: true });
    host.appendChild(bit);
  }
}


/* =========================================================================
   6. صفحة المقال: يرسمها الخادم الآن (PostPageController)
   ========================================================================= */

/* =========================================================================
   7. صفحات المدونة: انتقلت إلى blog.js (مجلة بتصنيفات وبحث وصفحات)
   ========================================================================= */

/* =========================================================================
   8. صفحة تعيين كلمة المرور
   ========================================================================= */

/**
 * يملأ الرمز والبريد من رابط البريد الإلكتروني.
 *
 * النموذج يبدأ `hidden` ولا يظهر إلا بوجود الرمز: عرض حقول كلمة مرور
 * لطلبٍ يُرفض حتماً يجعل المستخدم يجرّب ثلاث مرات قبل أن يفهم أن العطب
 * في الرابط لا في كلمته.
 */
export function initResetPassword() {
  const form = $('#resetPasswordForm');
  const invalid = $('#resetInvalid');
  if (!form || !invalid) return;

  const params = new URLSearchParams(window.location.search);
  const token = params.get('token');
  const email = params.get('email');

  if (!token || !email) {
    invalid.hidden = false;
    return;
  }

  $('#resetToken').value = token;
  $('#resetEmailHidden').value = email;

  const label = $('[data-reset-email]');
  if (label) label.textContent = email;

  form.hidden = false;
  $('#newPassword')?.focus();
}

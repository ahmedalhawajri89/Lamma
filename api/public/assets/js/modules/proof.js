/**
 * شريط الاعتمادات في الرئيسية: الجهات التي ترخّص المنصة وتدقّقها.
 *
 * كان هنا أيضاً «لوحة الثقة» (شبكة نقاط وحلقة وخط زمني) لصفحةٍ في التصميم
 * القديم، وذهبت مع انتقال الصفحات إلى Blade.
 */

import { $, escapeHtml } from './utils.js';
import { PARTNERS } from './data.js';
import { t } from './i18n.js';

/** شريط الاعتمادات — ساكن ومقروء، بلا حركة. */
export function initCredentials() {
  const host = $('#credentials');
  if (!host) return;
  if (!PARTNERS.length) {
    host.hidden = true;
    return;
  }

  host.innerHTML = PARTNERS.slice(0, 4).map((p) => `
<div class="credential">
  <span class="credential__icon"><i class="fa-solid ${escapeHtml(p.icon)}" aria-hidden="true"></i></span>
  <span class="credential__text">
    <strong>${escapeHtml(p.name)}</strong>
    <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> ${t('موثّق')}</span>
  </span>
</div>`).join('');
}

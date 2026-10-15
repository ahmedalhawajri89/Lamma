/**
 * صفحة «من نحن»: الأرقام من الخادم لحظة الفتح.
 *
 * كانت الصفحة تكتب «3280+ طفلاً» و«14 دولة» و«23 يوماً للتحقق» بيدها، والفعلي
 * غير ذلك. صفحة تقول «لا نُظهر ما لم نتحقق منه» لا تكتب أرقامها بلا مصدر:
 * هنا كلها من `/public/transparency` (proof)، وبلا خادم من PROOF في data.js.
 */

import { $, $$, countUp, formatNumber, observeOnce } from './utils.js';
import { PROOF } from './data.js';
import { apiEnabled, request } from './api.js';

export function initAbout() {
  const figures = $('#abFigures');
  if (!figures) return;

  load();

  async function load() {
    let proof = null;
    if (apiEnabled()) {
      try {
        proof = (await request('/public/transparency')).data?.proof ?? null;
      } catch {
        proof = null;
      }
    }
    render({ ...PROOF, ...(proof ?? {}) });
  }

  function render(p) {
    const values = {
      sponsored: p.sponsored ?? 0,
      years: p.startYear ? new Date().getFullYear() - p.startYear : 0,
      countries: p.countries ?? 0,
      visits: p.visits ?? 0,
      startYear: p.startYear ?? '',
      verifyDays: p.verifyDays ?? null,
    };

    // النصوص الثابتة: السنة بلا فواصل، والبقية بتنسيق الأرقام
    $$('[data-ab]').forEach((el) => {
      const v = values[el.dataset.ab];
      if (v === null || v === undefined || v === '') return;
      el.textContent = el.dataset.ab === 'startYear' ? String(v) : formatNumber(v);
    });

    const stat = $('#abVerifyStat');
    if (stat) stat.hidden = !values.verifyDays;

    // العدّ حين يظهر الشريط، مرة واحدة
    observeOnce($$('[data-ab-count]', figures), (el) => countUp(el, values[el.dataset.abCount] ?? 0), { threshold: 0.4 });
  }
}

/**
 * صفحة الجمعية الشريكة: أطفالها الذين ينتظرون، من /public/orphans?association=.
 * بلا أطفالٍ ينتظرون (أو بلا خادم) يختفي القسم كله، لا عنوانٌ فوق فراغ.
 */
import { $ } from './utils.js';
import { kidCard } from './render.js';
import { apiEnabled, request } from './api.js';
import { initReveal } from './motion.js';

const LIMIT = 8;

export async function initPartner() {
  const grid = $('#partnerKids');
  if (!grid) return;
  const section = grid.closest('[data-partner-section]');

  let data = [];
  try {
    if (apiEnabled()) {
      const params = new URLSearchParams({ association: grid.dataset.association, per_page: LIMIT, sort: 'urgent' });
      data = (await request(`/public/orphans?${params}`)).data ?? [];
    }
  } catch { /* يُخفى القسم أدناه */ }

  if (!data.length) {
    if (section) section.hidden = true;
    return;
  }

  grid.innerHTML = data.map((o, i) => kidCard(o, { delay: Math.min(i, 7) * 60, cols: 'col-sm-6 col-lg-4 col-xl-3' })).join('');
  initReveal();
}

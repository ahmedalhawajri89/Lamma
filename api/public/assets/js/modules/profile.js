/**
 * صفحة الطفل: الملف نفسه يرسمه الخادم (OrphanPageController)، وهنا ما بقي
 * للمتصفح: أطفالٌ آخرون ببطاقة الفهرس نفسها، وزرّ مشاركة الملف.
 */

import { $ } from './utils.js';
import { kidCard } from './render.js';
import { apiEnabled, request } from './api.js';
import { initReveal } from './motion.js';
import { toast } from './ui.js';

const SIMILAR = 4;

export function initProfile() {
  initSimilar();
  initShare();
}

/**
 * من البرنامج نفسه أولاً، ثم الأشد احتياجاً إن لم يكفِ: أربع بطاقات دائماً.
 * القسم مخفيٌّ حتى تصل، ويبقى مخفياً إن تعذّر الطلب: لا هيكلٌ فارغ.
 */
async function initSimilar() {
  const section = $('[data-similar]');
  if (!section || !apiEnabled()) return;

  const { program, exclude } = section.dataset;
  const fetchKids = (params) => request(`/public/orphans?${new URLSearchParams({ per_page: SIMILAR + 1, ...params })}`)
    .then((r) => r.data ?? [])
    .catch(() => []);

  const seen = new Set([exclude]);
  const pick = (list) => list.filter((o) => !seen.has(o.id) && seen.add(o.id));

  let kids = pick(program ? await fetchKids({ program }) : []);
  if (kids.length < SIMILAR) kids = kids.concat(pick(await fetchKids({})));
  kids = kids.slice(0, SIMILAR);
  if (!kids.length) return;

  $('#similarOrphans', section).innerHTML = kids
    .map((o, i) => kidCard(o, { delay: i * 80, cols: 'col-sm-6 col-lg-3' }))
    .join('');
  section.hidden = false;
  initReveal();
}

/**
 * مشاركة الملف: لوح المشاركة في الجوال (واتساب وغيره)، ونسخ الرابط حيث لا
 * يوجد. الرابط هو الصفحة نفسها، ووسوم مشاركتها باسم الطفل من الخادم.
 */
function initShare() {
  const btn = $('[data-share]');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    const data = { title: btn.dataset.shareTitle, url: window.location.href };
    if (navigator.share) {
      try { await navigator.share(data); } catch { /* أغلق اللوح: لا شيء */ }
      return;
    }
    try {
      await navigator.clipboard.writeText(data.url);
      toast('نُسخ رابط الملف، أرسله لمن تحب.');
    } catch {
      toast('تعذّر نسخ الرابط. انسخه من شريط العنوان.', 'error');
    }
  });
}

/**
 * صفحة الطفل: الملف نفسه يرسمه الخادم (OrphanPageController)، وهنا ما بقي
 * للمتصفح: أطفالٌ آخرون ببطاقة الفهرس نفسها، وزرّ مشاركة الملف.
 */

import { $, $$ } from './utils.js';
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
 * المشاركة: كل زرّ data-share يفتح لوح المشاركة الأصلي حيث يوجد (الجوال)،
 * وينسخ الرابط حيث لا يوجد. وأزرار المنصات (partials/share) روابط عادية،
 * ومعها data-copy-link للنسخ، وزرّ المشاركة الأصلي فيها يظهر حيث يُدعم وحده.
 * كانت الدالة تربط أول زرٍّ في الصفحة فقط، وصفحة المقال فيها اثنان.
 */
function initShare() {
  const native = typeof navigator.share === 'function';

  $$('[data-share]').forEach((btn) => {
    if (btn.classList.contains('share__btn--native')) btn.hidden = !native;
    btn.addEventListener('click', async () => {
      // data-share-url لرابطٍ غير الصفحة نفسها (صفحة الشكر تدعو إلى الفهرس)
      const data = { title: btn.dataset.shareTitle, url: new URL(btn.dataset.shareUrl ?? window.location.href, window.location.origin).href };
      if (native) {
        try { await navigator.share(data); } catch { /* أغلق اللوح: لا شيء */ }
        return;
      }
      copy(data.url, btn);
    });
  });

  $$('[data-copy-link]').forEach((btn) => btn.addEventListener('click', () => copy(btn.dataset.copyLink, btn)));
}

async function copy(url, btn) {
  try {
    await navigator.clipboard.writeText(url);
    toast('نُسخ الرابط، أرسله لمن تحب.');
    btn.classList.add('is-copied');
    setTimeout(() => btn.classList.remove('is-copied'), 1800);
  } catch {
    toast('تعذّر نسخ الرابط. انسخه من شريط العنوان.', 'error');
  }
}

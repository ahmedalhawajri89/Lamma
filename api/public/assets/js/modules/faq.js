/**
 * صفحة الأسئلة الشائعة: الأسئلة يرسمها الخادم في <details> تُقرأ وتُفتح بلا
 * JS، وهنا ما يضاف فوقها: بحثٌ يتسامح في الهمزات والتشكيل، وتصنيفٌ، وفتح
 * السؤال الذي في الرابط (/faq#zakat) فيصل من نقر رابطاً إلى جوابه مفتوحاً.
 */

import { $, $$, debounce, prefersReducedMotion } from './utils.js';
import { t } from './i18n.js';

/** «إيقاف» و«ايقاف» و«الزكاةَ» كلمةٌ واحدة في البحث */
const normalize = (text) => String(text)
  .toLowerCase()
  .replace(/[ً-ْٰـ]/g, '')
  .replace(/[إأآٱ]/g, 'ا')
  .replace(/ة/g, 'ه')
  .replace(/ى/g, 'ي');

export function initFaqPage() {
  const list = $('#faqItems');
  if (!list) return;

  const items = $$('.fq-item', list).map((el) => ({ el, text: normalize(el.textContent), cat: el.dataset.category }));
  const search = $('#faqSearch');
  const status = $('#faqStatus');
  const empty = $('#faqEmpty');
  let cat = '';

  const apply = () => {
    const words = normalize(search?.value ?? '').split(/\s+/).filter(Boolean);
    let shown = 0;
    items.forEach(({ el, text, cat: c }) => {
      const hit = (!cat || c === cat) && words.every((w) => text.includes(w));
      el.hidden = !hit;
      // نتيجة البحث تُفتح، فيقرأ الباحث الجواب بلا نقرة أخرى
      if (hit && words.length) el.open = true;
      if (hit) shown += 1;
    });
    empty.hidden = shown > 0;
    status.textContent = words.length || cat ? (shown === 1 ? t('{n} سؤال', { n: shown }) : t('{n} أسئلة', { n: shown })) : '';
  };

  search?.addEventListener('input', debounce(apply, 150));

  $$('[data-faq-cat]').forEach((btn) => btn.addEventListener('click', () => {
    cat = btn.dataset.faqCat;
    $$('[data-faq-cat]').forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
    apply();
  }));

  // السؤال في الرابط: يُفتح ويُمرَّر إليه، عند الفتح وعند كل تغيّر للمرساة
  const openHash = () => {
    const target = window.location.hash && document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
    if (!target || !target.classList.contains('fq-item')) return;
    target.hidden = false;
    target.open = true;
    target.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
    target.querySelector('summary')?.focus({ preventScroll: true });
  };
  openHash();
  window.addEventListener('hashchange', openHash);
}

/**
 * تعبئة الأقسام التي تُبنى من البيانات.
 * كل دالة تخرج بهدوء إن لم تجد حاويتها، فيصلح الملف لكل الصفحات.
 */

import {
  ORPHANS,
  PROGRAMS,
  STORIES,
  FAQS,
  POSTS,
  PARTNERS,
  IMPACT_STATS,
  SPENDING,
} from './data.js';

import {
  orphanCard,
  programCard,
  storyCard,
  faqItem,
  postCard,
  statCard,
  partnerItem,
  spendingDonut,
  spendingLegend,
  renderInto,
} from './render.js';

export function renderHomeSections() {
  /* الإحصاءات */
  renderInto('#statsGrid', IMPACT_STATS, statCard);

  /* شريط الشركاء */
  renderInto('#partnersTrack', PARTNERS, partnerItem);

  /* البرامج */
  renderInto('#programsGrid', PROGRAMS, programCard);

  /* الحالات العاجلة على الصفحة الرئيسية — أكثرها احتياجاً أولاً */
  const urgentHost = document.querySelector('#urgentOrphans');
  if (urgentHost) {
    const urgent = [...ORPHANS]
      .sort((a, b) => Number(b.urgent) - Number(a.urgent) || a.funded - b.funded)
      .slice(0, 6);

    urgentHost.innerHTML = urgent
      .map((o) => `<div class="slider__slide">${orphanCard(o)}</div>`)
      .join('');
  }

  /* قصص الكفلاء */
  const storiesHost = document.querySelector('#storiesTrack');
  if (storiesHost) {
    storiesHost.innerHTML = STORIES
      .map((s, index) => `<div class="slider__slide slider__slide--wide">${storyCard(s, { index })}</div>`)
      .join('');
  }

  /* الأسئلة الشائعة — يقبل حدّاً أقصى عبر data-limit */
  const faqHost = document.querySelector('#faqList');
  if (faqHost) {
    const limit = Number(faqHost.dataset.limit) || FAQS.length;
    faqHost.innerHTML = FAQS.slice(0, limit).map(faqItem).join('');
  }

  /* المدونة */
  const postsHost = document.querySelector('#postsGrid');
  if (postsHost) {
    const limit = Number(postsHost.dataset.limit) || POSTS.length;
    renderInto('#postsGrid', POSTS.slice(0, limit), postCard);
  }

  /* حلقة الشفافية */
  const donutHost = document.querySelector('#spendingDonut');
  if (donutHost) donutHost.innerHTML = spendingDonut(SPENDING);

  const legendHost = document.querySelector('#spendingLegend');
  if (legendHost) legendHost.innerHTML = spendingLegend(SPENDING);
}

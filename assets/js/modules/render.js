/**
 * قوالب العرض — تبني بطاقات الموقع من البيانات.
 *
 * المحتوى كله يمر عبر escapeHtml قبل الحقن. القوالب نفسها ثابتة نكتبها هنا،
 * والمتغيّر الوحيد هو النص القادم من data.js، وهو مُهرَّب دائماً.
 */

import { escapeHtml, formatMoney } from './utils.js';
import { getProgram } from './data.js';

/** بطاقة يتيم. */
export function orphanCard(orphan, { delay = 0 } = {}) {
  const program = getProgram(orphan.program);
  const name = escapeHtml(orphan.name);
  const remaining = Math.max(0, 100 - orphan.funded);

  const urgentTag = orphan.urgent
    ? '<span class="badge badge--urgent"><span class="pulse-dot"></span> حالة عاجلة</span>'
    : `<span class="badge badge--glass"><i class="fa-solid ${escapeHtml(program.icon)}"></i> ${escapeHtml(program.name)}</span>`;

  return `
<article class="orphan-card" data-reveal data-reveal-delay="${delay}">
  <div class="orphan-card__media">
    <img class="orphan-card__img" src="${escapeHtml(orphan.photo)}"
         alt="صورة ${name}" loading="lazy" width="400" height="300">
    <div class="orphan-card__tag">${urgentTag}</div>
    <div class="orphan-card__identity">
      <h3 class="orphan-card__name">${name}</h3>
      <div class="orphan-card__meta">
        <span><i class="fa-solid fa-cake-candles" aria-hidden="true"></i>${orphan.age} سنوات</span>
        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>${escapeHtml(orphan.country)} — ${escapeHtml(orphan.city)}</span>
      </div>
    </div>
  </div>

  <div class="orphan-card__body">
    <p class="orphan-card__need">${escapeHtml(orphan.story)}</p>

    <div class="progress" data-value="${orphan.funded}">
      <div class="progress__label">
        <span>مُغطّى من الكفالة</span>
        <strong>${orphan.funded}٪</strong>
      </div>
      <div class="progress__track">
        <div class="progress__fill"></div>
      </div>
      <div class="progress__label">
        <span>يتبقى ${remaining}٪ لاكتمال كفالته</span>
      </div>
    </div>

    <div class="orphan-card__foot">
      <div class="orphan-card__price">
        <strong class="num">${formatMoney(orphan.monthly)}</strong>
        <span>شهرياً</span>
      </div>
      <a class="btn btn--primary btn--sm" href="orphan.html?id=${encodeURIComponent(orphan.id)}">
        اكفل ${name} <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
      </a>
    </div>
  </div>
</article>`;
}

/** بطاقة برنامج كفالة. */
export function programCard(program, { delay = 0 } = {}) {
  const features = program.features
    .map((f) => `<li>${escapeHtml(f)}</li>`)
    .join('');

  return `
<article class="program-card${program.featured ? ' program-card--featured' : ''}"
         style="--program-accent: ${program.accent}"
         data-reveal data-reveal-delay="${delay}">
  ${program.featured ? '<span class="badge badge--accent program-card__ribbon">الأكثر أثراً</span>' : ''}

  <div class="icon-box icon-box--arch">
    <i class="fa-solid ${escapeHtml(program.icon)}" aria-hidden="true"></i>
  </div>

  <h3 class="card__title">${escapeHtml(program.name)}</h3>
  <p class="card__text">${escapeHtml(program.summary)}</p>

  <ul class="check-list" role="list">${features}</ul>

  <div class="program-card__price">
    <strong class="num">${formatMoney(program.price)}</strong>
    <span>/ شهرياً</span>
  </div>

  <a class="btn ${program.featured ? 'btn--primary' : 'btn--outline'} btn--block"
     href="orphans.html?program=${encodeURIComponent(program.id)}">
    اختر طفلاً لهذا البرنامج
  </a>
</article>`;
}

/** بطاقة شهادة كافل. */
export function storyCard(story, { index = 0 } = {}) {
  return `
<article class="story-card">
  <p class="story-card__quote">${escapeHtml(story.quote)}</p>
  <div class="story-card__author">
    <span class="avatar avatar--${(index % 4) + 1}" aria-hidden="true">${escapeHtml(story.name.trim()[0])}</span>
    <div>
      <strong>${escapeHtml(story.name)}</strong>
      <span>${escapeHtml(story.role)}</span>
    </div>
  </div>
</article>`;
}

/** عنصر سؤال شائع. */
export function faqItem(faq, index) {
  return `
<div class="accordion__item">
  <h3>
    <button class="accordion__trigger" type="button"
            id="faq-t-${index}" aria-expanded="false" aria-controls="faq-p-${index}">
      <span>${escapeHtml(faq.q)}</span>
      <span class="accordion__icon" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
    </button>
  </h3>
  <div class="accordion__panel" id="faq-p-${index}" role="region" aria-labelledby="faq-t-${index}">
    <div><p class="accordion__body">${escapeHtml(faq.a)}</p></div>
  </div>
</div>`;
}

/** بطاقة مقال. */
export function postCard(post, { delay = 0 } = {}) {
  return `
<article class="post-card" data-reveal data-reveal-delay="${delay}">
  <div class="post-card__media">
    <img src="${escapeHtml(post.image)}" alt="" loading="lazy" width="400" height="250">
    <span class="badge badge--glass post-card__date">${escapeHtml(post.category)}</span>
  </div>
  <div class="post-card__body">
    <h3 class="post-card__title"><a href="#">${escapeHtml(post.title)}</a></h3>
    <p class="post-card__excerpt">${escapeHtml(post.excerpt)}</p>
    <div class="post-card__foot">
      <time datetime="${escapeHtml(post.date)}">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i> ${escapeHtml(post.dateLabel)}
      </time>
      <span><i class="fa-regular fa-clock" aria-hidden="true"></i> ${post.readMin} دقائق قراءة</span>
    </div>
  </div>
</article>`;
}

/** بطاقة إحصائية. */
export function statCard(stat, { delay = 0 } = {}) {
  return `
<div class="stat" data-reveal data-reveal-delay="${delay}">
  <div class="stat__value">
    <span data-count="${stat.value}" data-suffix="${escapeHtml(stat.suffix)}">0</span>
  </div>
  <p class="stat__label">${escapeHtml(stat.label)}</p>
</div>`;
}

/** شعار شريك في شريط الثقة. */
export function partnerItem(partner) {
  return `
<div class="partner">
  <i class="fa-solid ${escapeHtml(partner.icon)}" aria-hidden="true"></i>
  <span>${escapeHtml(partner.name)}</span>
</div>`;
}

/**
 * حلقة توزيع الإنفاق — تُبنى كـ SVG خالص بلا مكتبة رسم بيانية.
 * نحسب نصيب كل قطاع من محيط الدائرة ونزيح كلاً منها بمقدار ما سبقه.
 */
export function spendingDonut(segments) {
  const RADIUS = 70;
  const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
  let offset = 0;

  const arcs = segments.map((seg, i) => {
    const length = (seg.value / 100) * CIRCUMFERENCE;
    const arc = `<circle class="donut__seg" cx="90" cy="90" r="${RADIUS}"
        stroke="${seg.color}"
        style="--seg-dash: ${length.toFixed(2)} ${(CIRCUMFERENCE - length).toFixed(2)};
               --seg-delay: ${i * 140}ms"
        stroke-dashoffset="${(-offset).toFixed(2)}"></circle>`;
    offset += length;
    return arc;
  }).join('');

  return `
<div class="donut" role="img" aria-label="توزيع الإنفاق: ${segments.map((s) => `${s.label} ${s.value}٪`).join('، ')}">
  <svg viewBox="0 0 180 180" aria-hidden="true">${arcs}</svg>
  <div class="donut__center">
    <strong>٩٣٪</strong>
    <span>يصل للطفل مباشرة</span>
  </div>
</div>`;
}

/** مفتاح ألوان الحلقة. */
export function spendingLegend(segments) {
  return segments.map((seg) => `
<li>
  <span class="legend__swatch" style="--swatch: ${seg.color}"></span>
  <span class="legend__name">${escapeHtml(seg.label)}</span>
  <span class="legend__pct num">${seg.value}٪</span>
</li>`).join('');
}

/**
 * حقن قائمة عناصر في حاوية.
 * @param {string} selector محدد الحاوية
 * @param {Array} items
 * @param {(item, meta) => string} template
 */
export function renderInto(selector, items, template) {
  const host = document.querySelector(selector);
  if (!host) return false;

  host.innerHTML = items
    .map((item, index) => template(item, { delay: index * 80, index }))
    .join('');

  return true;
}

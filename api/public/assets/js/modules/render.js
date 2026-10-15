/**
 * قوالب العرض — تبني بطاقات الموقع من البيانات.
 *
 * المحتوى كله يمر عبر escapeHtml قبل الحقن. القوالب نفسها ثابتة نكتبها هنا،
 * والمتغيّر الوحيد هو النص القادم من data.js، وهو مُهرَّب دائماً.
 */

import { escapeHtml, formatMoney, arabicDigits, readingTime } from './utils.js';

/**
 * بطاقة يتيم: قصة قصيرة لا سطر في جدول.
 *
 * في قلبها حلم الطفل، وهو مكتوب لكل طفل بينما الصور قليلة (تُرفع بعد موافقة
 * الوصي، ولا نستعير صورة طفل آخر). فبلا صورة يصير الحلم نفسه صورة البطاقة:
 * «أن تصبح معلمة» بخطٍّ كبير على لون هادئ، لا دائرةٌ بحرفٍ واحد. ومع الصورة
 * يُكتب الحلم سطراً تحت الاسم.
 *
 * ثم ما ينقصه بالمال وبشريطٍ يمتلئ حين تظهر البطاقة، وزرّ «اكفل» صريح. والاسم
 * رابطٌ ممتد إلى ملفه (البطاقة كلها تُضغط)، والزرّ فوقه إلى الكفالة.
 *
 * `spotlight`: البطاقة الأولى في الفهرس، أعرض، للأشد احتياجاً.
 */
const TINTS = ['brand', 'cta', 'growth'];

export function orphanCard(orphan, { delay = 0, spotlight = false } = {}) {
  const name = escapeHtml(orphan.name);
  const age = Number(orphan.age);
  const ageLabel = `${age} ${age >= 3 && age <= 10 ? 'سنوات' : 'سنة'}`;
  const her = ['أنثى', 'female'].includes(orphan.gender);
  const funded = Math.max(0, Math.min(100, Number(orphan.funded) || 0));
  const monthly = Number(orphan.monthly) || 0;
  const remaining = Math.max(0, Math.round((monthly * (100 - funded)) / 100));
  const id = encodeURIComponent(orphan.id);
  const dream = escapeHtml(orphan.dream ?? '');
  const place = [orphan.city, orphan.country].filter(Boolean).map(escapeHtml).join('، ');
  // لونٌ ثابت لكل طفل من رمزه: البطاقات بلا صور لا تبدو نسخة واحدة
  const tint = TINTS[[...String(orphan.id)].reduce((n, c) => n + c.charCodeAt(0), 0) % TINTS.length];

  const media = orphan.photo
    ? `<img class="kid__img" src="${escapeHtml(orphan.photo)}" alt="" loading="lazy" width="480" height="360">`
    : `<span class="kid__art kid__art--${tint}" aria-hidden="true">
         <span class="kid__art-label">${her ? 'حلمها' : 'حلمه'}</span>
         <span class="kid__art-quote">${dream || name}</span>
       </span>`;

  return `
<article class="kid${spotlight ? ' kid--spotlight' : ''}${orphan.photo ? ' kid--photo' : ''}" data-reveal data-reveal-delay="${delay}">
  <div class="kid__media">
    ${media}
    ${orphan.urgent ? '<span class="kid__flag">حالة عاجلة</span>' : ''}
  </div>

  <div class="kid__body">
    ${spotlight ? `<p class="kid__eyebrow">${orphan.urgent ? 'الأشد احتياجاً الآن' : 'ينتظر كافلاً'}</p>` : ''}
    <h3 class="kid__name">
      <a href="orphan.html?id=${id}">${name}</a><span class="kid__age">${ageLabel}</span>
    </h3>
    <p class="kid__place"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ${place}</p>
    ${orphan.photo && dream ? `<p class="kid__dream"><span>${her ? 'حلمها' : 'حلمه'}:</span> ${dream}</p>` : ''}

    <div class="kid__fund">
      <span class="kid__track" style="--v:${funded}%"><span></span></span>
      <div class="kid__fund-row">
        <span>${funded ? `مكفول <b class="num">${funded}%</b>` : 'لم يُكفل بعد'}</span>
        <span>${remaining ? `ينقصه <b class="num">$${remaining}</b> من <span class="num">$${monthly}</span>` : '<b>مكفول بالكامل</b>'}</span>
      </div>
    </div>

    <div class="kid__foot">
      <a class="btn btn--primary kid__cta" href="checkout.html?id=${id}">اكفل ${name}</a>
      <span class="kid__price"><strong class="num">${formatMoney(monthly)}</strong> شهرياً</span>
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

  <div class="icon-box icon-box--soft">
    <i class="fa-solid ${escapeHtml(program.icon)}" aria-hidden="true"></i>
  </div>

  <h3 class="card__title">${escapeHtml(program.name)}</h3>
  <p class="card__text">${escapeHtml(program.summary)}</p>

  <ul class="check-list" role="list">${features}</ul>

  <div class="program-card__price">
    <strong class="num">${formatMoney(program.price)}</strong>
    <span>/ شهرياً</span>
  </div>

  <a class="btn ${program.featured ? 'btn--cta' : 'btn--outline'} btn--block"
     href="orphans.html?program=${encodeURIComponent(program.id)}">
    اكفل يتيماً
  </a>
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
  <div class="post-card__media${post.image ? '' : ' post-card__media--empty'}">
    ${
      // مقال بلا صورة أو تصنيف وضعٌ وارد من لوحة الإدارة: لا نعرض صورة
      // مكسورة ولا كلمة «null»، بل سطحاً هادئاً بالشعار، ونُسقط الشارة.
      post.image
        ? `<img src="${escapeHtml(post.image)}" alt="" loading="lazy" width="400" height="250">`
        : '<img class="post-card__mark" src="assets/img/logo.svg" alt="" width="56" height="56">'
    }
    ${post.category ? `<span class="badge badge--glass post-card__date">${escapeHtml(post.category)}</span>` : ''}
  </div>
  <div class="post-card__body">
    <h3 class="post-card__title">${
      // البذرة (وضع بلا خادم) بلا slug — والعنوان حينها نصّ لا رابط:
      // رابط يقود إلى صفحة تقول «لم نجد المقال» أسوأ من عنوان بلا رابط.
      post.slug
        ? `<a href="post.html?slug=${encodeURIComponent(post.slug)}">${escapeHtml(post.title)}</a>`
        : escapeHtml(post.title)
    }</h3>
    <p class="post-card__excerpt">${escapeHtml(post.excerpt)}</p>
    <div class="post-card__foot">
      <time datetime="${escapeHtml(post.date)}">
        <i class="fa-regular fa-calendar" aria-hidden="true"></i> ${escapeHtml(arabicDigits(post.dateLabel ?? ''))}
      </time>
      <span><i class="fa-regular fa-clock" aria-hidden="true"></i> ${readingTime(post.readMin)} قراءة</span>
    </div>
  </div>
</article>`;
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

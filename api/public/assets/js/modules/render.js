/**
 * قوالب العرض — تبني بطاقات الموقع من البيانات.
 *
 * المحتوى كله يمر عبر escapeHtml قبل الحقن. القوالب نفسها ثابتة نكتبها هنا،
 * والمتغيّر الوحيد هو النص القادم من data.js، وهو مُهرَّب دائماً.
 */

import { escapeHtml, formatMoney, arabicDigits, readingTime, safeColor, safeNumber } from './utils.js';

const isGirl = (o) => ['أنثى', 'female'].includes(o.gender);
const ageLabel = (age) => `${age} ${age >= 3 && age <= 10 ? 'سنوات' : 'سنة'}`;

/** كم يتبقى بالدولار لاكتمال كفالة الطفل هذا الشهر. */
const remainingOf = (o) => Math.max(0, Math.round((o.monthly * (100 - o.funded)) / 100));

/**
 * بطاقة طفل: الرسم (لا صورة حقيقية على الموقع العام)، ثم الاسم والعمر والمدينة،
 * والحلم بسطر، والتمويل بشريط يمتلئ حين تظهر، وزرّ «اكفل» صريح.
 * الاسم رابطٌ ممتد إلى ملفه فالبطاقة كلها تُضغط، والزرّ فوقه إلى الكفالة.
 * واحدة للرئيسية وصفحة الأيتام، وأنماطها في kids.css.
 */
export function kidCard(o, { delay = 0, cols = 'col-sm-6 col-lg-3' } = {}) {
  const name = escapeHtml(o.name);
  const her = isGirl(o);
  const funded = safeNumber(o.funded);
  const link = `/orphan?id=${encodeURIComponent(o.id)}`;

  return `
<div class="${cols}">
  <article class="kid-card" data-reveal data-reveal-delay="${delay}">
    <div class="kid-card__media">
      ${o.photo
        ? `<img src="${escapeHtml(o.photo)}" alt="" width="400" height="400" loading="lazy">`
        : `<span class="kid-card__initial" aria-hidden="true">${escapeHtml(o.name.charAt(0))}</span>`}
      ${o.urgent ? '<span class="kid-card__urgent"><span class="kid-card__dot" aria-hidden="true"></span> حالة عاجلة</span>' : ''}
      <span class="kid-card__verified"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> ${her ? 'زارها' : 'زاره'} مشرف</span>
    </div>
    <div class="kid-card__body">
      <h3 class="kid-card__name"><a href="${link}">${name}</a><span>، ${ageLabel(o.age)}</span></h3>
      <p class="kid-card__place"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ${escapeHtml(o.city)}، ${escapeHtml(o.country)}</p>
      ${o.dream ? `<p class="kid-card__dream"><span>${her ? 'حلمها' : 'حلمه'}:</span> ${escapeHtml(o.dream)}</p>` : ''}
      <div class="kid-card__fund">
        <div class="kid-card__fund-row">
          <span>مكفول <b class="num">${funded}٪</b></span>
          <span>يتبقى <b class="num">$${remainingOf(o)}</b></span>
        </div>
        <span class="kid-card__track" role="progressbar" aria-label="نسبة كفالة ${name}" aria-valuenow="${funded}" aria-valuemin="0" aria-valuemax="100"><span style="--v:${funded}%"></span></span>
      </div>
      <a class="btn btn-cta w-100 kid-card__cta" href="/checkout?id=${encodeURIComponent(o.id)}">اكفل ${name}</a>
    </div>
  </article>
</div>`;
}

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
      <a href="/orphan?id=${id}">${name}</a><span class="kid__age">${ageLabel}</span>
    </h3>
    <p class="kid__place"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ${place}</p>
    ${orphan.photo && dream ? `<p class="kid__dream"><span>${her ? 'حلمها' : 'حلمه'}:</span> ${dream}</p>` : ''}
    ${!orphan.photo && dream ? `<p class="sr-only">${her ? 'حلمها' : 'حلمه'}: ${dream}</p>` : ''}

    <div class="kid__fund">
      <span class="kid__track" style="--v:${funded}%"><span></span></span>
      <div class="kid__fund-row">
        <span>${funded ? `مكفول <b class="num">${funded}%</b>` : 'لم يُكفل بعد'}</span>
        <span>${remaining ? `ينقصه <b class="num">$${remaining}</b> من <span class="num">$${monthly}</span>` : '<b>مكفول بالكامل</b>'}</span>
      </div>
    </div>

    <div class="kid__foot">
      <a class="btn btn--primary kid__cta" href="/checkout?id=${id}">اكفل ${name}</a>
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
         style="--program-accent: ${safeColor(program.accent)}"
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
     href="/orphans?program=${encodeURIComponent(program.id)}">
    اكفل يتيماً
  </a>
</article>`;
}

/** موضوع السؤال من كلماته: رابطٌ ثابت له (/faq#zakat) وأيقونة. */
const FAQ_TOPICS = [
  ['وصل', 'delivery', 'fa-circle-check'],
  ['الزكاة', 'zakat', 'fa-scale-balanced'],
  ['المصاريف', 'overhead', 'fa-chart-pie'],
  ['إيقاف', 'pause', 'fa-circle-pause'],
  ['الدفع', 'payment', 'fa-credit-card'],
  ['التواصل', 'contact', 'fa-comments'],
];
export function faqTopic(faq, index) {
  const hit = FAQ_TOPICS.find(([word]) => String(faq.q).includes(word));
  return { slug: hit?.[1] ?? `q-${faq.id ?? index}`, icon: hit?.[2] ?? 'fa-circle-question' };
}

/**
 * سؤال شائع قابل للطيّ (ui.js: initAccordions).
 * `icon` أيقونة الموضوع بجانب السؤال، و`open` يفتحه أولاً؛ وللسؤال رابطٌ ثابت
 * بموضوعه كي يُرسَل جوابٌ بعينه لكافل.
 */
export function faqItem(faq, index, { icon = false, open = false } = {}) {
  const topic = faqTopic(faq, index);
  return `
<div class="accordion__item${open ? ' is-open' : ''}" id="${topic.slug}">
  <h3>
    <button class="accordion__trigger" type="button"
            id="faq-t-${index}" aria-expanded="${open}" aria-controls="faq-p-${index}">
      ${icon ? `<span class="accordion__topic" aria-hidden="true"><i class="fa-solid ${topic.icon}"></i></span>` : ''}
      <span>${escapeHtml(faq.q)}</span>
      <span class="accordion__icon" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
    </button>
  </h3>
  <div class="accordion__panel" id="faq-p-${index}" role="region" aria-labelledby="faq-t-${index}">
    <div><p class="accordion__body">${escapeHtml(faq.a)}</p></div>
  </div>
</div>`;
}

/**
 * «من المدونة» في الرئيسية: شبكة Bento غير متماثلة.
 * المقال الرئيسي (المميّز وإلا الأحدث) طويل بصورة كاملة ونصّه على لوح مصمت
 * بلون الحبر فوقها، وبجانبه مقالان صغيران، وتحتها مقال عريض بمقتطفه.
 * العنوان رابطٌ ممتد فالبطاقة كلها تُضغط.
 */
export function homePosts(posts) {
  if (!posts.length) return '';
  const lead = posts.find((p) => p.featured) ?? posts[0];
  const rest = posts.filter((p) => p !== lead).slice(0, 3);
  const href = (p) => (p.slug ? `/post?slug=${encodeURIComponent(p.slug)}` : null);
  const title = (p) => (href(p) ? `<a href="${href(p)}">${escapeHtml(p.title)}</a>` : escapeHtml(p.title));
  const media = (p, w, h) => `<div class="bento__media">${p.image
    ? `<img src="${escapeHtml(p.image)}" alt="" loading="lazy" width="${w}" height="${h}">`
    : '<span class="bento__mark"></span>'}</div>`;
  const date = (p) => `<time datetime="${escapeHtml(p.date ?? '')}">${escapeHtml(arabicDigits(p.dateLabel ?? ''))}</time>`;
  const tag = (p) => (p.category ? `<span class="bento__tag">${escapeHtml(p.category)}</span>` : '');
  const kinds = ['a', 'b', 'wide'];

  return `
<article class="bento bento--lead" data-reveal>
  ${media(lead, 760, 900)}
  <div class="bento__panel">
    <p class="bento__meta">${tag(lead)}<span>${readingTime(lead.readMin)} قراءة</span></p>
    <h3 class="bento__title">${title(lead)}</h3>
    <p class="bento__excerpt">${escapeHtml(lead.excerpt ?? '')}</p>
    ${href(lead) ? '<span class="bento__cta" aria-hidden="true">اقرأ المقال <i class="fa-solid fa-arrow-left"></i></span>' : ''}
  </div>
</article>
${rest.map((p, i) => `
<article class="bento bento--${kinds[i]}" data-reveal data-reveal-delay="${120 + i * 100}">
  ${media(p, 480, 360)}
  <div class="bento__body">
    <p class="bento__meta">${tag(p)}${date(p)}</p>
    <h3 class="bento__title">${title(p)}</h3>
    ${kinds[i] === 'wide' ? `<p class="bento__excerpt">${escapeHtml(p.excerpt ?? '')}</p>` : ''}
  </div>
</article>`).join('')}`;
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

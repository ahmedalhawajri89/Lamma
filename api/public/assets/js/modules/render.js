/**
 * قوالب العرض — تبني بطاقات الموقع من البيانات.
 *
 * المحتوى كله يمر عبر escapeHtml قبل الحقن. القوالب نفسها ثابتة نكتبها هنا،
 * والمتغيّر الوحيد هو النص القادم من data.js، وهو مُهرَّب دائماً.
 */

import { escapeHtml, arabicDigits, readingTime, safeNumber } from './utils.js';

const isGirl = (o) => ['أنثى', 'female'].includes(o.gender);
const ageLabel = (age) => `${age} ${age >= 3 && age <= 10 ? 'سنوات' : 'سنة'}`;

/** كم يتبقى بالدولار لاكتمال كفالة الطفل هذا الشهر. */
const remainingOf = (o) => Math.max(0, Math.round((o.monthly * (100 - o.funded)) / 100));

/** «يكفله شخص واحد، شخصان، 3 أشخاص، 11 شخصاً» بضمير الطفل. */
function sponsorsLabel(n, her) {
  const verb = her ? 'يكفلها' : 'يكفله';
  if (n === 1) return `${verb} شخص واحد`;
  if (n === 2) return `${verb} شخصان`;
  return `${verb} <b class="num">${n}</b> ${n <= 10 ? 'أشخاص' : 'شخصاً'}`;
}

/**
 * بطاقة طفل: الرسم (لا صورة حقيقية على الموقع العام)، ثم الاسم والعمر والمدينة،
 * والحلم بسطر، والتمويل بشريط يمتلئ حين تظهر، وزرّ «اكفل» صريح.
 * الكفالة تُشارَك: من بدأ غيره كفالته تقول بطاقته كم يكفله وكم بقي، وزرّها
 * «أكمل كفالة…» لا «اكفل…»، فيعرف الزائر أن مبلغاً صغيراً يكفي.
 * الاسم رابطٌ ممتد إلى ملفه فالبطاقة كلها تُضغط، والزرّ فوقه إلى الكفالة.
 * واحدة للرئيسية وصفحة الأيتام، وأنماطها في kids.css.
 */
export function kidCard(o, { delay = 0, cols = 'col-sm-6 col-lg-3' } = {}) {
  const name = escapeHtml(o.name);
  const her = isGirl(o);
  const funded = safeNumber(o.funded);
  const sponsors = safeNumber(o.sponsors);
  const shared = funded > 0;
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
          <span>${sponsors ? sponsorsLabel(sponsors, her) : `مكفول <b class="num">${funded}٪</b>`}</span>
          <span>يتبقى <b class="num">$${remainingOf(o)}</b></span>
        </div>
        <span class="kid-card__track" role="progressbar" aria-label="نسبة كفالة ${name}" aria-valuenow="${funded}" aria-valuemin="0" aria-valuemax="100"><span style="--v:${funded}%"></span></span>
        ${shared ? `<p class="kid-card__share">شارك بأي مبلغ من <b class="num">$5</b> لإكمال ${her ? 'كفالتها' : 'كفالته'}</p>` : ''}
      </div>
      <a class="btn btn-cta w-100 kid-card__cta" href="/checkout?id=${encodeURIComponent(o.id)}">${shared ? 'أكمل كفالة' : 'اكفل'} ${name}</a>
    </div>
  </article>
</div>`;
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


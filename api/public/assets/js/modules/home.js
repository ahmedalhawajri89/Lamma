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
  SPENDING,
  PROOF,
  COUNTRIES,
} from './data.js';

import {
  faqItem,
  homePosts,
  spendingDonut,
  spendingLegend,
} from './render.js';

import { $, $$, countUp, escapeHtml, formatNumber, formatMoney, observeOnce, presetCount, safeNumber } from './utils.js';

/** عدد الأطفال في بطاقات الرئيسية: صفّ واحد على الشاشة العريضة. */
const KIDS_COUNT = 4;

const isGirl = (o) => o.gender === 'أنثى';
const ageLabel = (age) => `${age} ${age >= 3 && age <= 10 ? 'سنوات' : 'سنة'}`;

/** كم يتبقى بالدولار لاكتمال كفالة الطفل هذا الشهر. */
const remainingOf = (o) => Math.max(0, Math.round((o.monthly * (100 - o.funded)) / 100));

/**
 * بطاقة طفل: الرسم (لا صورة حقيقية على الموقع العام)، ثم الاسم والعمر والمدينة،
 * والحلم بسطر، والتمويل بشريط يمتلئ حين تظهر، وزرّ «اكفل» صريح.
 * الاسم رابطٌ ممتد إلى ملفه فالبطاقة كلها تُضغط، والزرّ فوقه إلى الكفالة.
 */
function kidCard(o, i) {
  const name = escapeHtml(o.name);
  const her = isGirl(o);
  const funded = safeNumber(o.funded);
  const link = `orphan.html?id=${encodeURIComponent(o.id)}`;

  return `
<div class="col-sm-6 col-lg-3">
  <article class="kid-card" data-reveal data-reveal-delay="${i * 90}">
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
      <a class="btn btn-cta w-100 kid-card__cta" href="checkout.html?id=${encodeURIComponent(o.id)}">اكفل ${name}</a>
    </div>
  </article>
</div>`;
}

/** «أطفالنا ينتظرون كافلاً»: العاجلون أولاً، ثم الأقل تمويلاً. */
function renderHomeOrphans() {
  const host = $('#homeOrphans');
  if (!host) return;
  host.removeAttribute('aria-busy');
  if (!ORPHANS.length) return;

  const kids = [...ORPHANS]
    .sort((a, b) => Number(b.urgent) - Number(a.urgent) || a.funded - b.funded)
    .slice(0, KIDS_COUNT);

  host.innerHTML = kids.map(kidCard).join('');
}

/**
 * البرامج كأعمدة مفتوحة بلا بطاقات، تفصلها خطوط شعرية.
 * السعر والزر في الأعلى: القرار قبل التفاصيل، والمزايا تحتهما لمن يريد.
 */
function renderPlans() {
  const html = PROGRAMS.map((p, i) => `
<article class="plan${p.featured ? ' is-featured' : ''}" data-reveal data-reveal-delay="${i * 90}">
  <p class="plan__flag">${p.featured ? 'الأكثر اختياراً' : ''}</p>
  <h3 class="plan__name">${escapeHtml(p.name)}</h3>
  <p class="plan__price"><strong class="num">${formatMoney(p.price)}</strong><span>في الشهر</span></p>
  <p class="plan__summary">${escapeHtml(p.summary)}</p>
  <a class="btn ${p.featured ? 'btn--cta' : 'btn--outline'} plan__cta"
     href="orphans.html?program=${encodeURIComponent(p.id)}">اكفل يتيماً</a>
  <ul class="plan__features" role="list">
    ${(p.features ?? []).map((f) => `<li><i class="fa-solid fa-check" aria-hidden="true"></i>${escapeHtml(f)}</li>`).join('')}
  </ul>
</article>`).join('');

  // الرئيسية وصفحة البرامج تعرضانها بالشكل نفسه
  $$('#homePlans, #programsGrid').forEach((host) => { host.innerHTML = html; });
}

/** الشهادات: اقتباس رئيسي بخط كبير، واثنان قصيران تحته. بلا بطاقات. */
function renderVoices() {
  const host = $('#homeVoices');
  if (!host || !STORIES.length) return;

  const [lead, ...rest] = STORIES;
  const by = (s) => `<figcaption><strong>${escapeHtml(s.name)}</strong><span>${escapeHtml(s.role)}</span></figcaption>`;

  host.innerHTML = `
<figure class="voice voice--lead" data-reveal>
  <blockquote>${escapeHtml(lead.quote)}</blockquote>
  ${by(lead)}
</figure>
<div class="voices__more">
  ${rest.slice(0, 2).map((s, i) => `
  <figure class="voice" data-reveal data-reveal-delay="${150 + i * 100}">
    <blockquote>${escapeHtml(s.quote)}</blockquote>
    ${by(s)}
  </figure>`).join('')}
</div>`;
}

/** أرقام القسم الداكن من بيانات الشفافية نفسها، لا من نصٍّ ثابت. */
function renderMoneyStats() {
  const host = $('#moneyStats');
  if (!host) return;

  const stats = [
    // ٩٣٪ ظاهرة في وسط الحلقة بجانبها، فلا تتكرر هنا
    { value: formatNumber(new Date().getFullYear() - PROOF.startYear), label: 'سنة من العمل الموثّق' },
    { value: formatNumber(PROOF.sponsored), label: 'يتيماً مكفولاً اليوم' },
    // العدد كل الزيارات المسجّلة لا زيارات سنة واحدة
    { value: formatNumber(PROOF.visits), label: 'زيارة ميدانية موثّقة' },
    { value: formatNumber(COUNTRIES.length), label: 'دولة نعمل فيها' },
  ];

  host.innerHTML = stats
    .map((s) => `<div><dt>${escapeHtml(s.label)}</dt><dd class="num">${escapeHtml(s.value)}</dd></div>`)
    .join('');
}

/**
 * بطاقة أرقام الهيرو: من الشفافية لحظة الفتح، تعدّ مرة واحدة حين تظهر.
 * البند الذي لا رقم له يُخفى: بطاقة تقول «0 يتيماً مكفولاً» أسوأ من غيابه،
 * ورقمٌ مكتوب باليد (كان «120,000» في التصميم المقترح) هو ما خرجنا منه.
 */
function renderHeroProof() {
  const host = $('#heroStats');
  if (!host) return;

  const stats = {
    sponsored: { value: PROOF.sponsored },
    toChild: { value: PROOF.toChild, format: (n) => `${formatNumber(n)}٪` },
    startYear: { value: PROOF.startYear, format: String, still: true },
    visits: { value: PROOF.visits },
  };

  $$('[data-hero-item]', host).forEach((item) => {
    const stat = stats[item.dataset.heroItem];
    item.hidden = !(Number(stat?.value) > 0);
  });

  const items = $$('[data-hero]', host).filter((el) => !el.closest('[hidden]'));
  // السنة تُكتب كما هي، والبقية تعدّ من الصفر حين تظهر البطاقة
  items.forEach((el) => {
    const { value, format = formatNumber, still } = stats[el.dataset.hero];
    if (still) el.textContent = format(value);
    else presetCount(el, format(value)).textContent = format(0);
  });
  observeOnce([host], () => items.forEach((el) => {
    const { value, format = formatNumber, still } = stats[el.dataset.hero];
    if (!still) countUp(el, Number(value), format);
  }), { threshold: 0.4 });
}

export function renderHomeSections() {
  renderHeroProof();
  renderHomeOrphans();
  renderMoneyStats();

  renderPlans();
  renderVoices();

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
    postsHost.innerHTML = homePosts(POSTS.slice(0, limit));
  }

  /* حلقة الشفافية */
  const donutHost = document.querySelector('#spendingDonut');
  // الرقم في الوسط من الشفافية نفسها: كان «٩٣٪» مكتوباً في القالب
  if (donutHost) donutHost.innerHTML = spendingDonut(SPENDING, PROOF.toChild);

  const legendHost = document.querySelector('#spendingLegend');
  if (legendHost) legendHost.innerHTML = spendingLegend(SPENDING);
}

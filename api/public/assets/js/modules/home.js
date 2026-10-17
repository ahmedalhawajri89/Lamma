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
} from './data.js';

import {
  faqItem,
  homePosts,
} from './render.js';

import { $, $$, countUp, escapeHtml, formatNumber, formatMoney, observeOnce, prefersReducedMotion, presetCount, safeColor, safeNumber } from './utils.js';

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

/**
 * «أين تذهب كل مئة دولار»: الحلقة والبنود متّصلتان.
 *
 * البند زرٌّ (aria-pressed): المرور عليه أو التركيز يُبرز قطعته ويُخفت
 * الباقي ويكتب حصته في وسط الحلقة، والنقر يثبّته. وفي الوسط افتراضياً ما
 * يصل للطفل. الأرقام كلها من الشفافية (SPENDING وPROOF)، لا من نصٍّ ثابت.
 */
function renderMoney() {
  const donutHost = $('#moneyDonut');
  const listHost = $('#moneyList');
  if (!donutHost || !listHost || !SPENDING.length) return;

  const R = 78;
  const C = 2 * Math.PI * R;
  const GAP = 3; // فاصل بين القطع
  let offset = 0;
  const arcs = SPENDING.map((seg, i) => {
    const len = Math.max(0, (safeNumber(seg.value) / 100) * C - GAP);
    const arc = `<circle class="money-donut__seg" data-i="${i}" cx="100" cy="100" r="${R}" stroke="${safeColor(seg.color)}"
      style="--len:${len.toFixed(2)};--gap:${(C - len).toFixed(2)};--d:${i * 160}ms" stroke-dashoffset="${(-offset).toFixed(2)}"></circle>`;
    offset += (safeNumber(seg.value) / 100) * C;
    return arc;
  }).join('');

  const toChild = safeNumber(PROOF.toChild);
  donutHost.innerHTML = `
<div class="money-donut__chart" role="img" aria-label="توزيع الإنفاق: ${escapeHtml(SPENDING.map((s) => `${s.label} ${safeNumber(s.value)}٪`).join('، '))}">
  <svg viewBox="0 0 200 200" aria-hidden="true"><circle class="money-donut__track" cx="100" cy="100" r="${R}"></circle>${arcs}</svg>
  <div class="money-donut__center" aria-hidden="true">
    <strong class="num" data-money-value>${toChild}٪</strong>
    <span data-money-label>من كفالتك تصل للطفل</span>
  </div>
</div>`;

  listHost.innerHTML = SPENDING.map((seg, i) => {
    const v = safeNumber(seg.value);
    return `
<li data-reveal data-reveal-delay="${i * 80}">
  <button class="money-row" type="button" data-i="${i}" aria-pressed="false" style="--c:${safeColor(seg.color)};--v:${v}%">
    <span class="money-row__swatch" aria-hidden="true"></span>
    <span class="money-row__name">${escapeHtml(seg.label)}</span>
    <span class="money-row__bar" aria-hidden="true"><span></span></span>
    <b class="money-row__amount num">$${formatNumber(v)}</b>
  </button>
</li>`;
  }).join('');

  const chart = $('.money-donut__chart', donutHost);
  const valueEl = $('[data-money-value]', donutHost);
  const labelEl = $('[data-money-label]', donutHost);
  const rows = $$('.money-row', listHost);
  let pinned = -1;

  const show = (i) => {
    chart.classList.toggle('is-focused', i >= 0);
    $$('.money-donut__seg', chart).forEach((seg) => seg.classList.toggle('is-on', Number(seg.dataset.i) === i));
    rows.forEach((row, j) => row.classList.toggle('is-on', j === i));
    if (i < 0) { valueEl.textContent = `${toChild}٪`; labelEl.textContent = 'من كفالتك تصل للطفل'; return; }
    valueEl.textContent = `${formatNumber(safeNumber(SPENDING[i].value))}٪`;
    labelEl.textContent = SPENDING[i].label;
  };

  rows.forEach((row, i) => {
    row.addEventListener('mouseenter', () => show(i));
    row.addEventListener('focus', () => show(i));
    row.addEventListener('mouseleave', () => show(pinned));
    row.addEventListener('blur', () => show(pinned));
    row.addEventListener('click', () => {
      pinned = pinned === i ? -1 : i;
      rows.forEach((r, j) => r.setAttribute('aria-pressed', String(j === pinned)));
      show(pinned === -1 ? i : pinned);
    });
  });

  // تُرسم القطع وتعدّ النسبة حين تظهر الحلقة، مرة واحدة
  observeOnce([chart], () => {
    chart.classList.add('is-drawn');
    // عدّ بسيط للنص مباشرة: الوسط زينة (aria-hidden) والقائمة تحمل الأرقام
    // للقارئ الصوتي، وcountUp يغلّف النص بعناصر يمحوها show() عند المرور
    if (prefersReducedMotion()) return;
    const start = performance.now();
    const tick = (now) => {
      if (chart.classList.contains('is-focused')) return;
      const t = Math.min(1, (now - start) / 1100);
      valueEl.textContent = `${formatNumber(Math.round(toChild * (1 - (1 - t) ** 3)))}٪`;
      if (t < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }, { threshold: 0.35 });
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

/**
 * نموذج التقرير: كل بند (الدراسة، الصحة، كفالتك) مربوط بجزئه من التقرير.
 * المرور أو التركيز يُبرز الجزء ويُخفت الباقي، والنقر يثبّته (aria-pressed).
 */
function linkReportPoints() {
  const mock = $('#reportMock');
  const points = $$('.report-point');
  if (!mock || !points.length) return;

  let pinned = null;
  const show = (key) => {
    mock.classList.toggle('is-focused', Boolean(key));
    $$('[data-block]', mock).forEach((b) => b.classList.toggle('is-on', b.dataset.block === key));
    points.forEach((p) => p.classList.toggle('is-on', p.dataset.block === key));
  };

  points.forEach((point) => {
    const key = point.dataset.block;
    point.addEventListener('mouseenter', () => show(key));
    point.addEventListener('focus', () => show(key));
    point.addEventListener('mouseleave', () => show(pinned));
    point.addEventListener('blur', () => show(pinned));
    point.addEventListener('click', () => {
      pinned = pinned === key ? null : key;
      points.forEach((p) => p.setAttribute('aria-pressed', String(p.dataset.block === pinned)));
      show(pinned ?? key);
    });
  });
}

export function renderHomeSections() {
  renderHeroProof();
  renderHomeOrphans();
  renderMoney();
  linkReportPoints();

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

}

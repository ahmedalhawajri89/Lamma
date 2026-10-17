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

/** عدد الأطفال الذين يُعرَّف بهم في الرئيسية: الأول مفتوحاً والباقون وجوهاً. */
const MEET_COUNT = 6;

const isGirl = (o) => o.gender === 'أنثى';
const ageLabel = (age) => `${age} ${age >= 3 && age <= 10 ? 'سنوات' : 'سنة'}`;
const programOf = (o) => PROGRAMS.find((p) => p.id === o.program);

/** كم يتبقى بالدولار لاكتمال كفالة الطفل هذا الشهر. */
const remainingOf = (o) => Math.max(0, Math.round((o.monthly * (100 - o.funded)) / 100));

function photo(o, cls) {
  return o.photo
    ? `<img class="${cls}" src="${escapeHtml(o.photo)}" alt="" loading="lazy" width="600" height="600">`
    : `<span class="${cls} ${cls}--empty" aria-hidden="true">${escapeHtml(o.name.charAt(0))}</span>`;
}

/**
 * لوحة طفل واحد، مرتّبة كبطاقة تعريف: الاسم، ثم صفّ حقائق، ثم الحلم
 * اقتباساً، ثم القصة، ثم كتلة التمويل، ثم القرار.
 * والصورة وثيقة لا زينة: زوايا شبه حادة، وكتلة ملوّنة خلفها، وسطر الملف تحتها.
 */
function meetPanel(o, i) {
  const name = escapeHtml(o.name);
  const her = isGirl(o);
  const program = programOf(o);
  const link = `orphan.html?id=${encodeURIComponent(o.id)}`;

  return `
<article class="meet__panel${i === 0 ? ' is-active' : ''}" role="tabpanel" id="meetPanel${i}"
         aria-labelledby="meetTab${i}"${i === 0 ? '' : ' aria-hidden="true" inert'}>
  <figure class="meet__figure">
    <a class="meet__photo" href="${link}" tabindex="-1" aria-hidden="true">${photo(o, 'meet__img')}</a>
    <figcaption class="meet__file">
      <span>ملف <b class="num">${escapeHtml(o.id)}</b></span>
      <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> ${her ? 'زارها' : 'زاره'} مشرف ميداني</span>
    </figcaption>
  </figure>

  <div class="meet__text">
    <div class="meet__head">
      <h3 class="meet__name">${name}</h3>
      ${o.urgent ? '<span class="meet__urgent"><span class="pulse-dot"></span> حالة عاجلة</span>' : ''}
    </div>

    <dl class="meet__facts">
      <div><dt>العمر</dt><dd>${ageLabel(o.age)}</dd></div>
      <div><dt>المدينة</dt><dd>${escapeHtml(o.city)}، ${escapeHtml(o.country)}</dd></div>
      ${program ? `<div><dt>البرنامج</dt><dd>${escapeHtml(program.name)}</dd></div>` : ''}
    </dl>

    ${o.dream ? `<blockquote class="meet__dream">
      <p>${escapeHtml(o.dream)}</p>
      <footer>حلم ${name}</footer>
    </blockquote>` : ''}

    <p class="meet__story">${escapeHtml(o.story ?? '')}</p>

    <div class="meet__fund">
      <div class="meet__fund-head">
        <span>${her ? 'تحتاج' : 'يحتاج'} <b class="num">${formatMoney(o.monthly)}</b> في الشهر</span>
        <span>مكفول <b class="num">${safeNumber(o.funded)}٪</b> · يتبقى <b class="num">$${remainingOf(o)}</b></span>
      </div>
      <span class="meet__track" style="--v:${safeNumber(o.funded)}%"><span></span></span>
    </div>

    <div class="meet__actions">
      <a class="btn btn--cta" href="checkout.html?id=${encodeURIComponent(o.id)}">اكفل ${name}</a>
      <a class="meet__more" href="${link}">${her ? 'اقرأ ملفها كاملاً' : 'اقرأ ملفه كاملاً'}</a>
    </div>
  </div>
</article>`;
}

/** وجه في الصف: زر تبويب يفتح لوحة الطفل. */
function meetFace(o, i) {
  return `
<button class="meet__face" type="button" role="tab" id="meetTab${i}" aria-controls="meetPanel${i}"
        aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}">
  ${photo(o, 'meet__face-img')}
  <span>${escapeHtml(o.name)}</span>
</button>`;
}

/**
 * «تعرّف على طفل»: طفل واحد مفتوح، ووجوه الباقين تحته.
 *
 * ليس فهرساً (صفحة الأيتام فيها البحث والتصفية)، بل تعارف: اسم وحلم وقصة.
 * اللوحات مكدّسة في خلية واحدة فلا يتغيّر ارتفاع القسم عند التبديل.
 */
function renderHomeOrphans() {
  const host = $('#homeOrphans');
  if (!host) return;
  host.removeAttribute('aria-busy');
  if (!ORPHANS.length) return;

  const kids = [...ORPHANS]
    .sort((a, b) => Boolean(b.photo) - Boolean(a.photo)
      || Number(b.urgent) - Number(a.urgent) || a.funded - b.funded)
    .slice(0, MEET_COUNT);

  const others = Math.max(0, ORPHANS.length - kids.length);

  host.innerHTML = `
<div class="meet">
  <div class="meet__stage">${kids.map(meetPanel).join('')}</div>

  <div class="meet__faces">
    <p class="meet__faces-title">تعرّف على غيرهم</p>
    <div class="meet__row" role="tablist" aria-label="أطفال ينتظرون كافلاً">
      ${kids.map(meetFace).join('')}
      <a class="meet__all" href="orphans.html">
        <span>${others ? `<b class="num">+${others}</b>` : '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>'}</span>
        كل الأطفال
      </a>
    </div>
  </div>
</div>`;

  const tabs = $$('.meet__face', host);
  const panels = $$('.meet__panel', host);
  let current = 0;

  const select = (index, focus = false) => {
    current = index;
    tabs.forEach((t, i) => {
      t.setAttribute('aria-selected', String(i === index));
      t.tabIndex = i === index ? 0 : -1;
    });
    panels.forEach((p, i) => {
      p.classList.toggle('is-active', i === index);
      p.toggleAttribute('inert', i !== index);
      if (i === index) p.removeAttribute('aria-hidden');
      else p.setAttribute('aria-hidden', 'true');
    });
    if (focus) tabs[index].focus();
  };

  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => select(i));
    tab.addEventListener('keydown', (e) => {
      if (e.key === 'Home' || e.key === 'End') {
        e.preventDefault();
        select(e.key === 'Home' ? 0 : tabs.length - 1, true);
        return;
      }
      const step = { ArrowLeft: 1, ArrowRight: -1 }[e.key];
      if (step === undefined) return;
      e.preventDefault();
      select((current + step + tabs.length) % tabs.length, true);
    });
  });
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

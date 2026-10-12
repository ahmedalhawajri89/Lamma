/**
 * لوحة الثقة والاعتمادات.
 *
 * الفكرة: الرقم المجرّد لا يُحدث أثراً. «٣٢٨٠ يتيماً» سطرٌ يُقرأ ويُنسى،
 * أما ٨٢ نقطة تظهر واحدةً تلو الأخرى فتجعل العدد محسوساً. ولذلك:
 *   • الأطفال → شبكة نقاط، كل نقطة عددٌ منهم.
 *   • النسبة  → حلقة تُرسم أمام العين.
 *   • الدول   → أسماؤها لا عددها.
 *   • السنوات → خط زمني من سنة التأسيس إلى اليوم.
 *
 * كل المرئيات SVG وCSS خالصان — بلا مكتبة رسم بيانية.
 */

import { $, $$, escapeHtml, arabicDigits } from './utils.js';
import { ANNUAL, PROOF, COUNTRIES, PARTNERS } from './data.js';
import { observeOnce } from './utils.js';

const RING_RADIUS = 52;

/** شبكة النقاط: كل نقطة تمثّل PROOF.perDot طفلاً. */
function dotGrid(total, perDot) {
  const count = Math.round(total / perDot);
  return Array.from({ length: count },
    (_, i) => `<span style="--i:${i}"></span>`).join('');
}

/**
 * صياغة المعدود بالعربية.
 *
 * العدد يأتي من الخادم مشتقاً من عدد المكفولين، فقد يكون ١ أو ٢ أو ٥٠.
 * وقالبٌ واحد يُخرج «كل نقطة تمثّل 1 طفلاً» — عربية مكسورة تُفسد أثر
 * الرقم كله. والقاعدة هنا مبسّطة لكنها تغطي كل ما يُنتجه سلّم المقياس.
 */
/**
 * كم دولاراً تمثّل النقطة الواحدة في شبكة المال.
 *
 * المنطق نفسه المستعمل في شبكة الأطفال، وللسبب نفسه: المقياس يُشتق من
 * المبلغ لا يُفترض. وسلّم الأرقام مألوف لأن التسمية تحت الشبكة تُقرأ.
 */
function moneyScale(total) {
  for (const step of [10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 25000, 50000]) {
    if (total / step <= 120) return step;
  }
  return 100000;
}

/** ٥٠٠ → «٥٠٠ دولار» · ١٠٠٠ → «ألف دولار» — أقصر وأوضح في تسمية. */
function money(n) {
  if (n >= 1000 && n % 1000 === 0) {
    const k = n / 1000;
    if (k === 1) return 'ألف دولار';
    if (k === 2) return 'ألفي دولار';
    return `${arabicDigits(k)} آلاف دولار`;
  }
  return `${arabicDigits(n)} دولار`;
}

function children(n) {
  if (n === 1) return 'طفلاً واحداً';
  if (n === 2) return 'طفلين';
  if (n <= 10) return `${n} أطفال`;
  return `${n} طفلاً`;
}

/** حلقة النسبة — تُرسم بـ stroke-dashoffset عند دخولها الشاشة. */
function ring(percent) {
  const circumference = 2 * Math.PI * RING_RADIUS;
  const offset = circumference * (1 - percent / 100);

  return `
<div class="ring" style="--circ:${circumference.toFixed(1)}; --offset:${offset.toFixed(1)}">
  <svg viewBox="0 0 128 128" aria-hidden="true">
    <defs>
      <linearGradient id="ringGradient" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" style="stop-color: var(--chart-1)"/>
        <stop offset="100%" style="stop-color: var(--chart-3)"/>
      </linearGradient>
    </defs>
    <circle class="ring__track" cx="64" cy="64" r="${RING_RADIUS}"></circle>
    <circle class="ring__bar" cx="64" cy="64" r="${RING_RADIUS}"></circle>
  </svg>
  <div class="ring__center">
    <strong class="num">${percent}٪</strong>
  </div>
</div>`;
}

/** أسماء الدول كشرائح — أوقع من رقم مجرّد. */
function countryChips(countries) {
  return countries
    .map((c, i) => `<span style="--i:${i}">${escapeHtml(c)}</span>`)
    .join('');
}

export function initProofBoard() {
  const host = $('#proofBoard');
  if (!host) return;

  const nowYear = new Date().getFullYear();
  const years = nowYear - PROOF.startYear;

  host.innerHTML = `
<article class="bento__tile bento__tile--hero">
  <div>
    <div class="bento__value">
      <span class="num" data-count="${PROOF.sponsored}" data-suffix="+">0</span>
    </div>
    <p class="bento__label">يتيماً مكفولاً اليوم</p>
  </div>

  <div class="dot-grid" role="img"
       aria-label="شبكة تمثّل ${PROOF.sponsored} يتيماً مكفولاً، كل نقطة ${children(PROOF.perDot)}">
    ${dotGrid(PROOF.sponsored, PROOF.perDot)}
  </div>

  <p class="bento__note">
    كل نقطة تمثّل ${children(PROOF.perDot)}، ولكل واحد منهم اسم وملف وتقرير.
  </p>
</article>

<article class="bento__tile">
  ${ring(PROOF.toChild)}
  <p class="bento__label text-center">يصل للطفل مباشرة</p>
  <p class="bento__note text-center">والباقي مصاريف تشغيل موثّقة</p>
</article>

<article class="bento__tile">
  <div>
    <div class="bento__value"><span class="num" data-count="${COUNTRIES.length}">0</span></div>
    <p class="bento__label">دولة نعمل فيها</p>
  </div>
  <div class="country-chips">${countryChips(COUNTRIES)}</div>
</article>

<article class="bento__tile bento__tile--wide">
  <div>
    <div class="bento__value"><span class="num" data-count="${years}">0</span></div>
    <p class="bento__label">سنة من العمل الموثّق</p>
  </div>

  <div class="timeline-bar">
    <span class="timeline-bar__year">${PROOF.startYear}</span>
    <div class="timeline-bar__track"><div class="timeline-bar__fill"></div></div>
    <span class="timeline-bar__year timeline-bar__year--now">${nowYear}</span>
  </div>

  <p class="bento__note">
    بدأنا بأربعة عشر طفلاً في مخيم واحد، و${PROOF.visits.toLocaleString('en-US')} زيارة ميدانية موثّقة العام الماضي وحده.
  </p>
</article>`;

  // كل بلاطة تُشغّل مرئيّها عند دخولها الشاشة، لا عند تحميل الصفحة
  observeOnce([...host.children], (el) => el.classList.add('is-visible'), { threshold: 0.25 });
}

/** شريط الاعتمادات — ساكن ومقروء، بلا حركة. */
export function initCredentials() {
  const host = $('#credentials');
  if (!host) return;

  host.innerHTML = PARTNERS.slice(0, 4).map((p) => `
<div class="credential">
  <span class="credential__icon"><i class="fa-solid ${escapeHtml(p.icon)}" aria-hidden="true"></i></span>
  <span class="credential__text">
    <strong>${escapeHtml(p.name)}</strong>
    <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> موثّق</span>
  </span>
</div>`).join('');
}

/**
 * لوحة الشفافية المالية — نفس نظام البلاطات ببيانات مختلفة،
 * ليثبت أن اللوحة نظامٌ قابل لإعادة الاستخدام لا قالبٌ منسوخ.
 */
export function initFinanceBoard() {
  const host = $('#financeBoard');
  if (!host) return;

  const perDollar = moneyScale(ANNUAL.spent);

  // السنة تظهر خارج هذه اللوحة أيضاً (عنوان القسم وفوق الحلقة)
  $$('[data-annual-year]').forEach((el) => {
    el.textContent = arabicDigits(ANNUAL.year);
  });

  host.innerHTML = `
<article class="bento__tile bento__tile--hero">
  <div>
    <div class="bento__value">
      <span class="num">$<span data-count="${Math.round(ANNUAL.spent)}">0</span></span>
    </div>
    <p class="bento__label">صُرف على المستفيدين في ${arabicDigits(ANNUAL.year)}</p>
  </div>

  <div class="dot-grid" role="img"
       aria-label="شبكة تمثّل توزيع المبلغ، كل نقطة ${money(perDollar)}">
    ${dotGrid(ANNUAL.spent, perDollar)}
  </div>

  <p class="bento__note">كل نقطة تمثّل ${money(perDollar)} صُرفت ووُثّقت.</p>
</article>

<article class="bento__tile">
  ${ring(PROOF.toChild)}
  <p class="bento__label text-center">يصل للطفل مباشرة</p>
  <p class="bento__note text-center">٧٪ مصاريف إدارية مغطاة من داعمين مخصصين</p>
</article>

<article class="bento__tile">
  <div>
    <div class="bento__value"><span class="num" data-count="${PROOF.visits}">0</span></div>
    <p class="bento__label">زيارة ميدانية موثّقة</p>
  </div>
  <p class="bento__note">بمعدل زيارة لكل طفل كل تسعين يوماً، بلا استثناء.</p>
</article>

<article class="bento__tile bento__tile--wide">
  <div>
    <div class="bento__value"><span class="num" data-count="0">0</span></div>
    <p class="bento__label">ملاحظة جوهرية من المدقق الخارجي</p>
  </div>
  <p class="bento__note">
    أربع سنوات متتالية بتقرير تدقيق نظيف. التقارير منشورة كاملة أدناه.
  </p>
</article>`;

  observeOnce([...host.children], (el) => el.classList.add('is-visible'), { threshold: 0.25 });
}

/**
 * صفحة الشفافية: تقرير مالي حيّ.
 *
 * كانت الصفحة تكتب «المصاريف الإدارية ٧٪» بيدها والفعلي 6.9، وتعرض «0
 * ملاحظة من المدقق» و«أربع سنوات بتدقيق نظيف» بلا مصدر، وأربعة أزرار «تحميل
 * PDF» إلى `#`. هنا كل رقم من الخادم لحظة الفتح:
 *  1. من كل دولار كم يصل الطفل: رقمٌ يعدّ ومئة خانة تمتلئ.
 *  2. أرقام السنة المختارة: الدخل، والصرف، والمكفولون، والزيارات.
 *  3. الدخل والصرف شهراً بشهر: أعمدة SVG (لا مكتبة رسوم في الموقع).
 *  4. أين يذهب المال: شريط مكدّس، والمرور على بندٍ يبرزه.
 *  5. التشغيل مقابل هدفه، بجملة محسوبة.
 *  6. الوثائق التي رفعها المدير، وما لم يُرفع يُقال عنه ذلك.
 *
 * بلا خادم: البيانات المبدئية في data.js، والوثائق «تُنشر قريباً».
 */

import { $, $$, countUp, escapeHtml, formatNumber, keepFocus, observeOnce, prefersReducedMotion, presetCount, safeColor, safeNumber } from './utils.js';
import { ANNUAL, PROOF, SPENDING } from './data.js';
import { apiEnabled, request } from './api.js';

const DOC_META = {
  audited_budget: { icon: 'fa-file-invoice-dollar', label: 'الميزانية المدقّقة', hint: 'بيان الإيرادات والمصروفات كاملاً، معتمداً من مكتب تدقيق مستقل.' },
  annual_report: { icon: 'fa-chart-line', label: 'التقرير السنوي', hint: 'ما أنجزناه وما أخفقنا فيه، بالأرقام والصور والدروس المستفادة.' },
  zakat_report: { icon: 'fa-hand-holding-heart', label: 'تقرير الزكاة المستقل', hint: 'سجل منفصل لأموال الزكاة: ما جُمع، ولمن صُرف، بلا أي خصم إداري.' },
  licenses: { icon: 'fa-certificate', label: 'التراخيص والاعتمادات', hint: 'شهادة التسجيل، رخصة العمل الخيري، واعتمادات الجهات الشريكة.' },
};

const usd = (n) => `$${formatNumber(Math.round(n))}`;
const monthFmt = new Intl.DateTimeFormat('ar-u-nu-latn', { month: 'short' });
const monthLong = new Intl.DateTimeFormat('ar-u-nu-latn', { month: 'long', year: 'numeric' });
const dateFmt = new Intl.DateTimeFormat('ar-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' });

export function initTransparency() {
  const root = $('#tpCells');
  if (!root) return;

  let year = Number(new URLSearchParams(window.location.search).get('year')) || null;
  let animated = false;

  load();

  // «رجوع» يعيد السنة السابقة: كل اختيار سنةٍ خطوةٌ في السجل
  window.addEventListener('popstate', () => {
    year = Number(new URLSearchParams(window.location.search).get('year')) || null;
    load();
  });

  async function load() {
    let data = null;
    if (apiEnabled()) {
      try {
        data = (await request(`/public/transparency${year ? `?year=${year}` : ''}`)).data;
      } catch {
        data = null;
      }
    }
    // بلا خادم: البذرة المضمَّنة، بلا شهور ولا وثائق مرفوعة
    data ??= {
      spending: SPENDING, proof: PROOF, annual: ANNUAL,
      overhead: { year: ANNUAL.year, actual: 100 - (PROOF.toChild ?? 93), target: 7 },
      monthly: [], years: [ANNUAL.year], documents: [],
    };
    year = data.annual?.year ?? year;
    render(data);
  }

  function render(d) {
    const actual = d.overhead?.actual;
    const cents = actual === null || actual === undefined ? (d.proof?.toChild ?? 93) : Math.round(100 - actual);

    $$('[data-tp-year]').forEach((el) => { el.textContent = String(year); });
    renderYears(d.years ?? []);
    renderCents(cents);
    renderStats(d);
    renderMonthly(d.monthly ?? []);
    renderStack(d.spending ?? [], d.annual?.spent ?? 0);
    renderOverhead(d.overhead, d.annual);
    renderDocs(d.documents ?? []);
    animated = true;
  }

  /* ── السنة ── */
  function renderYears(years) {
    const host = $('#tpYears');
    if (years.length < 2) { host.hidden = true; return; }
    host.hidden = false;
    const restoreFocus = keepFocus();
    host.innerHTML = years.map((y) => `<button type="button" class="tp-year" aria-pressed="${y === year}" data-year="${y}"><span class="num">${y}</span></button>`).join('');
    $$('[data-year]', host).forEach((b) => b.addEventListener('click', () => {
      if (Number(b.dataset.year) === year) return;
      year = Number(b.dataset.year);
      const url = new URL(window.location.href);
      url.searchParams.set('year', String(year));
      history.pushState(null, '', url);
      load();
    }));
    restoreFocus();
  }

  /* ── من كل دولار: رقمٌ يعدّ ومئة خانة ── */
  function renderCents(cents) {
    countUp($('#tpCents'), cents);
    root.setAttribute('aria-label', `${cents} سنتاً من كل دولار تصل الطفل`);
    $('#tpKeyChild').textContent = String(cents);
    $('#tpKeyOps').textContent = String(100 - cents);
    root.innerHTML = Array.from({ length: 100 }, (_, i) => `<span class="${i < cents ? 'is-child' : 'is-ops'}" style="--i:${i}"></span>`).join('');
    requestAnimationFrame(() => root.classList.add('is-in'));
  }

  /* ── أرقام السنة ── */
  function renderStats(d) {
    const v = { income: d.annual?.income ?? 0, spent: d.annual?.spent ?? 0, sponsored: d.proof?.sponsored ?? 0, visits: d.proof?.visits ?? 0, countries: d.proof?.countries ?? 0 };
    const host = $('#tpStats');
    const run = () => $$('[data-tp-stat]', host).forEach((el) => {
      const k = el.dataset.tpStat;
      countUp(el, v[k], ['income', 'spent'].includes(k) ? usd : formatNumber);
    });
    if (!animated) {
      $$('[data-tp-stat]', host).forEach((el) => {
        const k = el.dataset.tpStat;
        presetCount(el, (['income', 'spent'].includes(k) ? usd : formatNumber)(v[k]));
      });
    }
    if (animated) run(); else observeOnce([host], run, { threshold: 0.3 });
  }

  /* ── شهراً بشهر: أعمدة SVG، الأقدم يميناً كما يُقرأ السطر ── */
  function renderMonthly(months) {
    const section = $('#tpMonthlySection');
    if (!months.length) { section.hidden = true; return; }
    section.hidden = false;

    // الأرقام على الحافة اليمنى (بداية السطر)، والأعمدة بعدها
    const W = 760; const H = 280; const padL = 8; const padR = 64; const padTop = 16; const padBottom = 30;
    const max = Math.max(1, ...months.flatMap((m) => [m.income, m.spent]));
    const nice = niceCeil(max);
    const step = (W - padL - padR) / months.length;
    const bw = Math.min(16, step * 0.28);
    const y = (v) => padTop + (H - padTop - padBottom) * (1 - v / nice);

    const grid = [0, 0.25, 0.5, 0.75, 1].map((f) => {
      const yy = y(nice * f);
      return `<line x1="${padL}" x2="${W - padR}" y1="${yy}" y2="${yy}" class="tp-chart__grid"/>
        <text x="${W - 4}" y="${yy + 4}" class="tp-chart__tick" text-anchor="end">${f ? usd(nice * f) : ''}</text>`;
    }).join('');

    const bars = months.map((m, i) => {
      // اليمين أولاً: الشهر الأقدم عند بداية السطر العربي
      const cx = W - padR - step * (i + 0.5);
      const [yy, mm] = m.month.split('-').map(Number);
      const label = monthFmt.format(new Date(yy, mm - 1, 1));
      const base = H - padBottom;
      return `<g class="tp-chart__col" data-i="${i}" style="--i:${i}">
        <rect class="tp-chart__hit" x="${cx - step / 2}" y="${padTop}" width="${step}" height="${base - padTop}"/>
        <rect class="tp-chart__bar tp-chart__bar--in" x="${cx + 1}" y="${y(m.income)}" width="${bw}" height="${base - y(m.income)}" rx="3"/>
        <rect class="tp-chart__bar tp-chart__bar--out" x="${cx - bw - 1}" y="${y(m.spent)}" width="${bw}" height="${base - y(m.spent)}" rx="3"/>
        <text x="${cx}" y="${H - 8}" class="tp-chart__label" text-anchor="middle">${label}</text>
      </g>`;
    }).join('');

    /*
     * أرقام كل شهر كانت في تلميحٍ يظهر بمرور الفأرة وحده، فلا يصلها من يلمس
     * الشاشة أو يتنقّل بلوحة المفاتيح أو يسمع الصفحة. الجدول تحت المخطط يحمل
     * الأرقام نفسها لهم جميعاً، مطويّاً كي لا يزاحم المخطط بصرياً.
     */
    const rows = months.map((m) => {
      const [yy, mm] = m.month.split('-').map(Number);
      return `<tr><th scope="row">${monthLong.format(new Date(yy, mm - 1, 1))}</th>
        <td class="num">${usd(m.income)}</td><td class="num">${usd(m.spent)}</td></tr>`;
    }).join('');

    const host = $('#tpMonthly');
    host.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="الدخل والصرف في آخر ${months.length} شهراً، والأرقام في الجدول بعده">${grid}${bars}</svg>
      <div class="tp-chart__tip" hidden></div>
      <details class="tp-chart__data">
        <summary>عرض الأرقام شهراً بشهر</summary>
        <div class="table-responsive tp-table">
          <table class="table mb-0">
            <caption class="visually-hidden">الدخل والصرف في آخر ${months.length} شهراً</caption>
            <thead><tr><th scope="col">الشهر</th><th scope="col">الدخل</th><th scope="col">الصرف على الأطفال</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </details>`;

    const tip = $('.tp-chart__tip', host);
    $$('.tp-chart__col', host).forEach((g) => {
      const m = months[Number(g.dataset.i)];
      const [yy, mm] = m.month.split('-').map(Number);
      // pointerenter لا mouseenter: يطلق مع لمسة الإصبع أيضاً، فيظهر التلميح عند النقر على العمود
      g.addEventListener('pointerenter', () => {
        $$('.tp-chart__col.is-hover', host).forEach((o) => o.classList.remove('is-hover'));
        g.classList.add('is-hover');
        tip.innerHTML = `<b>${monthLong.format(new Date(yy, mm - 1, 1))}</b>
          <span><i class="tp-key__dot tp-key__dot--in"></i>الدخل <b class="num">${usd(m.income)}</b></span>
          <span><i class="tp-key__dot tp-key__dot--out"></i>الصرف <b class="num">${usd(m.spent)}</b></span>`;
        const box = host.getBoundingClientRect();
        const r = g.getBoundingClientRect();
        tip.style.setProperty('--x', `${r.left + r.width / 2 - box.left}px`);
        tip.hidden = false;
      });
      g.addEventListener('pointerleave', (e) => {
        // مع اللمس يبقى التلميح حتى يُلمس عمود آخر، وإلا اختفى فور ظهوره
        if (e.pointerType === 'touch') return;
        g.classList.remove('is-hover');
        tip.hidden = true;
      });
    });
    observeOnce([host], () => host.classList.add('is-in'), { threshold: 0.25 });

    const totalIn = months.reduce((s, m) => s + m.income, 0);
    const totalOut = months.reduce((s, m) => s + m.spent, 0);
    // الأرقام وحدها: لا تفسيرٌ للفرق لا تقوله البيانات
    $('#tpMonthlyNote').innerHTML = `في اثني عشر شهراً دخل <b class="num">${usd(totalIn)}</b> وصُرف على الأطفال <b class="num">${usd(totalOut)}</b>. التفصيل في الميزانية المدقّقة أدناه.`;
  }

  /* ── أين يذهب المال: شريط مكدّس ومفتاح، والمرور يبرز البند ── */
  function renderStack(items, spent) {
    const stack = $('#tpStack');
    const legend = $('#tpLegend');
    if (!items.length) {
      stack.innerHTML = '';
      legend.innerHTML = '<li class="tp-legend__empty">لا صرف معتمد في هذه السنة بعد.</li>';
      return;
    }
    stack.innerHTML = items.map((s, i) => `<span data-k="${i}" style="--w:${safeNumber(s.value)}%; --c:${safeColor(s.color)}; --i:${i}" title="${escapeHtml(s.label)}: ${safeNumber(s.value)}%"></span>`).join('');
    legend.innerHTML = items.map((s, i) => `<li data-k="${i}">
        <span class="tp-legend__dot" style="background:${safeColor(s.color)}"></span>
        <span class="tp-legend__label">${escapeHtml(s.label)}</span>
        <span class="tp-legend__amount num">${spent ? usd((s.value / 100) * spent) : ''}</span>
        <span class="tp-legend__pct num">${safeNumber(s.value)}%</span>
      </li>`).join('');

    const focus = (k) => {
      $$('[data-k]', stack).forEach((el) => el.classList.toggle('is-dim', k !== null && el.dataset.k !== k));
      $$('[data-k]', legend).forEach((el) => el.classList.toggle('is-dim', k !== null && el.dataset.k !== k));
    };
    [...$$('[data-k]', stack), ...$$('[data-k]', legend)].forEach((el) => {
      el.addEventListener('mouseenter', () => focus(el.dataset.k));
      el.addEventListener('mouseleave', () => focus(null));
    });
    observeOnce([stack], () => stack.classList.add('is-in'), { threshold: 0.3 });
  }

  /* ── التشغيل مقابل هدفه ── */
  function renderOverhead(o, annual) {
    const host = $('#tpOverhead');
    if (!o || o.actual === null || o.actual === undefined) {
      host.innerHTML = '<p class="tp-overhead__title">نسبة التشغيل</p><p class="tp-overhead__text">لا صرف معتمد في هذه السنة بعد.</p>';
      return;
    }
    const over = o.actual > o.target;
    const pos = (p) => `${Math.min(100, Math.max(0, (p / 30) * 100))}%`;
    host.innerHTML = `
      <p class="tp-overhead__title">نسبة التشغيل في <span class="num">${o.year}</span></p>
      <p class="tp-overhead__value num ${over ? 'is-over' : ''}">${o.actual}%</p>
      <div class="tp-gauge" aria-hidden="true">
        <span class="tp-gauge__fill ${over ? 'is-over' : ''}" style="--w:${pos(o.actual)}"></span>
        <span class="tp-gauge__target" style="--x:${pos(o.target)}"><span>الهدف <b class="num">${o.target}%</b></span></span>
      </div>
      <p class="tp-overhead__text">
        ${over
          ? `فوق هدفنا (<span class="num">${o.target}%</span>). ننشره كما هو، ونشرح سببه في التقرير السنوي.`
          : `ضمن هدفنا (<span class="num">${o.target}%</span>).`}
        رواتب المشرفين والنقل والمكتب، من <span class="num">${usd(annual?.spent ?? 0)}</span> صُرفت هذه السنة.
      </p>`;
    observeOnce([host], () => host.classList.add('is-in'), { threshold: 0.3 });
  }

  /* ── الوثائق: المرفوع بزرّ تحميل حقيقي، وغيره يُقال عنه ذلك ── */
  function renderDocs(docs) {
    const byKind = Object.fromEntries(docs.map((d) => [d.kind, d]));
    $('#tpDocs').innerHTML = Object.entries(DOC_META).map(([kind, m], i) => {
      const d = byKind[kind];
      const size = d ? (d.size_bytes >= 1048576 ? `${(d.size_bytes / 1048576).toFixed(1)} م.ب` : `${Math.max(1, Math.round(d.size_bytes / 1024))} ك.ب`) : '';
      return `<li class="tp-doc ${d ? '' : 'is-pending'}" data-reveal data-reveal-delay="${i * 70}">
        <span class="tp-doc__icon" aria-hidden="true"><i class="fa-solid ${m.icon}"></i></span>
        <div class="tp-doc__body">
          <h3>${escapeHtml(d?.title ?? m.label)}</h3>
          <p>${m.hint}</p>
          ${d ? `<p class="tp-doc__meta">PDF · <span class="num">${size}</span> · نُشر ${dateFmt.format(new Date(d.published_at))}</p>` : ''}
        </div>
        ${d
          ? `<a class="btn btn-primary tp-doc__cta" href="${escapeHtml(d.url)}" target="_blank" rel="noopener" download>
               <i class="fa-solid fa-arrow-down" aria-hidden="true"></i> تحميل PDF</a>`
          : `<span class="tp-doc__pending">يُنشر بعد اعتماد المدقق · <a href="/contact">اطلب نسخة</a></span>`}
      </li>`;
    }).join('');
    // البطاقات تُكتب بعد الإقلاع: تُكشف هنا لا بانتظار initReveal
    if (prefersReducedMotion()) $$('#tpDocs [data-reveal]').forEach((el) => el.classList.add('is-visible'));
    else observeOnce($$('#tpDocs [data-reveal]'), (el) => {
      el.style.setProperty('--reveal-delay', `${el.dataset.revealDelay}ms`);
      el.classList.add('is-visible');
    }, { threshold: 0.2 });
  }
}

/** سقفٌ مقروء للمحور: 1، 2، 2.5، 5 مضروبة في قوى العشرة */
function niceCeil(v) {
  const p = 10 ** Math.floor(Math.log10(v));
  return [1, 2, 2.5, 5, 10].map((m) => m * p).find((x) => x >= v);
}

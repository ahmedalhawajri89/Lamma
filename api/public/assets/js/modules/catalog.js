/**
 * صفحة الأيتام: فهرسٌ يُصفّح ويُفلتر على الخادم.
 *
 * كانت الصفحة تطلب أول مئة طفل وتفلترهم في المتصفح، فمن بعد المئة لا يظهر
 * لأحد أبداً (137 طفلاً موثّقاً، 37 منهم بلا نافذة)، وتعرضهم كلهم في صفحة
 * واحدة بطول ثمانية عشر ألف بكسل، تتأخّر بطاقاتها تراكمياً حتى يبدو آخرها
 * فارغاً. هنا: الخادم يفلتر ويعدّ ويصفّح، والصفحة تعرض اثنتي عشرة بطاقة،
 * وبجانب كل خيار عدد ما فيه، وكل فلتر وصفحة في الرابط (يُشارَك ويُرجَع إليه).
 */

import { $, $$, escapeHtml, debounce, formatNumber } from './utils.js';
import { ORPHANS, PROGRAMS } from './data.js';
import { orphanCard } from './render.js';
import { apiEnabled, request } from './api.js';
import { initReveal } from './motion.js';

const PER_PAGE = 12;
const KEYS = ['q', 'program', 'country', 'gender', 'age', 'urgent', 'sort', 'page'];
const GENDERS = [{ value: 'male', label: 'ولد' }, { value: 'female', label: 'بنت' }];
const AGES = [{ value: '0-5', label: 'حتى 5 سنوات' }, { value: '6-10', label: '6 إلى 10' }, { value: '11-18', label: '11 فأكبر' }];
const SORTS = [
  { value: 'urgent', label: 'الأشد احتياجاً' },
  { value: 'funded_asc', label: 'الأقل تغطية' },
  { value: 'monthly_asc', label: 'الأقل مبلغاً' },
  { value: 'monthly_desc', label: 'الأعلى مبلغاً' },
  { value: 'age_asc', label: 'الأصغر سناً' },
  { value: 'newest', label: 'الأحدث إضافة' },
];

export function initCatalog() {
  const grid = $('#catalogGrid');
  if (!grid) return;

  const state = readUrl();
  let facets = null;
  let sequence = 0;

  const panels = $$('[data-filters]');           // الشريط الجانبي ولوحة الجوال: نسختان من الفلاتر نفسها
  const sortSelect = $('#catalogSort');
  const searchInputs = $$('[data-filter-search]');
  const sheet = $('#filterSheet');

  if (sortSelect) {
    sortSelect.innerHTML = SORTS.map((s) => `<option value="${s.value}">${s.label}</option>`).join('');
    sortSelect.value = state.sort || 'urgent';
    sortSelect.addEventListener('change', () => update({ sort: sortSelect.value === 'urgent' ? '' : sortSelect.value }));
  }

  searchInputs.forEach((input) => {
    input.value = state.q || '';
    input.addEventListener('input', debounce(() => {
      searchInputs.forEach((other) => { if (other !== input) other.value = input.value; });
      update({ q: input.value.trim() });
    }, 300));
  });

  /* ── الفلاتر: نقرة على خيار، والنقرة على المختار تلغيه ─────────────── */
  document.addEventListener('click', (e) => {
    const opt = e.target.closest('[data-filter]');
    if (opt) {
      const { filter, value } = opt.dataset;
      update({ [filter]: state[filter] === value ? '' : value });
      return;
    }
    const remove = e.target.closest('[data-remove]');
    if (remove) update({ [remove.dataset.remove]: '' });
    if (e.target.closest('[data-reset]')) {
      searchInputs.forEach((i) => { i.value = ''; });
      if (sortSelect) sortSelect.value = 'urgent';
      update(Object.fromEntries(KEYS.map((k) => [k, ''])));
    }
    const page = e.target.closest('[data-page]');
    if (page && !page.disabled) {
      state.page = page.dataset.page === '1' ? '' : page.dataset.page;
      writeUrl(state, true);
      load({ scroll: true });
    }
  });

  /* ── لوحة الفلاتر على الجوال ────────────────────────────────────────── */
  $('#openFilters')?.addEventListener('click', () => sheet?.showModal());
  $$('[data-close-sheet]').forEach((b) => b.addEventListener('click', () => sheet?.close()));
  sheet?.addEventListener('click', (e) => { if (e.target === sheet) sheet.close(); });

  window.addEventListener('popstate', () => {
    Object.assign(state, readUrl());
    searchInputs.forEach((i) => { i.value = state.q || ''; });
    if (sortSelect) sortSelect.value = state.sort || 'urgent';
    load();
  });

  function update(patch) {
    Object.assign(state, patch, { page: '' });
    writeUrl(state, false);
    load();
  }

  /* ── الجلب والعرض ───────────────────────────────────────────────────── */
  async function load({ scroll = false } = {}) {
    const mine = ++sequence;
    grid.setAttribute('aria-busy', 'true');

    const params = new URLSearchParams({ per_page: PER_PAGE, facets: 1, sort: state.sort || 'urgent' });
    for (const k of ['q', 'program', 'country', 'gender', 'age']) if (state[k]) params.set(k, state[k]);
    if (state.urgent) params.set('urgent', 1);
    if (state.page) params.set('page', state.page);

    let payload;
    try {
      payload = apiEnabled() ? await request(`/public/orphans?${params}`) : null;
    } catch {
      payload = null;
    }
    if (mine !== sequence) return;
    // الموقع ملفاتٌ ساكنة قد تُفتح والخادم متوقف: الفلاتر والصفحات نفسها على
    // البيانات المضمَّنة، لا شاشة خطأ
    if (!payload) payload = localQuery(state);

    const { data = [], meta = {} } = payload;
    facets = meta.facets ?? facets;

    // صفحةٌ بعد آخر الصفحات (رابط قديم أو فلتر ضيّق): إلى الأخيرة
    if (!data.length && meta.current_page > 1 && meta.last_page >= 1) {
      state.page = meta.last_page > 1 ? String(meta.last_page) : '';
      writeUrl(state, false);
      load();
      return;
    }

    grid.removeAttribute('aria-busy');
    grid.innerHTML = data.length
      // التأخير في الصفحة الواحدة وحدها: اثنتا عشرة بطاقة لا مئة
      ? data.map((o, i) => orphanCard(o, { delay: i * 40 })).join('')
      : `<div class="catalog-empty full-bleed">
           <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
           <h3>لا أطفال بهذه الفلاتر</h3>
           <p>جرّب دولة أخرى أو عمراً أوسع، أو أزل فلتراً.</p>
           <button class="btn btn--outline" type="button" data-reset>أزل كل الفلاتر</button>
         </div>`;

    renderSummary(meta);
    renderPanels();
    renderChips();
    renderPager(meta);
    initReveal();

    if (scroll) $('#catalogTop')?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  }

  function renderSummary(meta) {
    const total = meta.total ?? 0;
    const from = total ? (meta.current_page - 1) * meta.per_page + 1 : 0;
    const to = Math.min(total, meta.current_page * meta.per_page);
    $('#catalogCount').innerHTML = `<strong class="num">${formatNumber(total)}</strong> ${total === 1 ? 'طفل' : 'طفلاً'}`;
    // «من 13 إلى 24» لا «13–24»: الشرطة بين رقمين تنقلب في سطرٍ عربي فتُقرأ «24–13»
    $('#catalogRange').textContent = total > PER_PAGE ? `يُعرض من ${from} إلى ${to}` : '';
    const n = activeCount();
    $$('[data-active-count]').forEach((el) => { el.textContent = n ? String(n) : ''; el.hidden = !n; });
    $$('[data-sheet-total]').forEach((el) => { el.textContent = formatNumber(total); });
  }

  /* ── الفلاتر بأعدادها: تُبنى من النتائج نفسها ───────────────────────── */
  function renderPanels() {
    if (!facets) return;
    const programName = (slug) => PROGRAMS.find((p) => p.id === slug)?.name ?? slug;

    const option = (filter, value, label, count) => {
      const on = state[filter] === value;
      const empty = !count && !on;
      return `<li><button type="button" class="facet" data-filter="${filter}" data-value="${escapeHtml(value)}"
        aria-pressed="${on}" ${empty ? 'disabled' : ''}>
        <span class="facet__label">${escapeHtml(label)}</span><span class="facet__count num">${formatNumber(count ?? 0)}</span>
      </button></li>`;
    };

    const programs = PROGRAMS.map((p) => option('program', p.id, p.name, facets.program?.[p.id] ?? 0)).join('');
    const countries = Object.entries(facets.country ?? {}).map(([c, n]) => option('country', c, c, n)).join('');
    const genders = GENDERS.map((g) => option('gender', g.value, g.label, facets.gender?.[g.value] ?? 0)).join('');
    const ages = AGES.map((a) => option('age', a.value, a.label, facets.age?.[a.value] ?? 0)).join('');

    panels.forEach((panel) => {
      $('[data-group="program"]', panel).innerHTML = programs;
      $('[data-group="country"]', panel).innerHTML = countries || '<li class="facet-none">لا دول</li>';
      $('[data-group="gender"]', panel).innerHTML = genders;
      $('[data-group="age"]', panel).innerHTML = ages;
      const urgent = $('[data-filter="urgent"]', panel);
      urgent.setAttribute('aria-checked', String(Boolean(state.urgent)));
      $('[data-urgent-count]', panel).textContent = formatNumber(facets.urgent ?? 0);
    });

    $$('[data-program-name]').forEach((el) => { el.textContent = state.program ? programName(state.program) : ''; });
  }

  function renderChips() {
    const programName = (slug) => PROGRAMS.find((p) => p.id === slug)?.name ?? slug;
    const chips = [
      state.q && ['q', `«${state.q}»`],
      state.program && ['program', programName(state.program)],
      state.country && ['country', state.country],
      state.gender && ['gender', GENDERS.find((g) => g.value === state.gender)?.label],
      state.age && ['age', AGES.find((a) => a.value === state.age)?.label],
      state.urgent && ['urgent', 'حالات عاجلة'],
    ].filter(Boolean);

    const box = $('#activeFilters');
    box.hidden = !chips.length;
    box.innerHTML = chips.map(([k, label]) => `<button type="button" class="active-chip" data-remove="${k}" aria-label="إزالة فلتر ${escapeHtml(label)}">
        ${escapeHtml(label)} <i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`).join('')
      + (chips.length > 1 ? '<button type="button" class="active-chip active-chip--clear" data-reset>مسح الكل</button>' : '');
  }

  /* ── التصفيح: أرقام بفواصل، والسابقة والتالية ──────────────────────── */
  function renderPager(meta) {
    const nav = $('#catalogPager');
    const last = meta.last_page ?? 1;
    const cur = meta.current_page ?? 1;
    if (last <= 1) { nav.hidden = true; nav.innerHTML = ''; return; }

    const pages = new Set([1, last, cur, cur - 1, cur + 1]);
    if (cur <= 3) [2, 3, 4].forEach((p) => pages.add(p));
    if (cur >= last - 2) [last - 1, last - 2, last - 3].forEach((p) => pages.add(p));
    const list = [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);

    let html = `<button type="button" class="pager__step" data-page="${cur - 1}" ${cur === 1 ? 'disabled' : ''}>
      <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> السابقة</button><ol class="pager__pages">`;
    list.forEach((p, i) => {
      if (i && p - list[i - 1] > 1) html += '<li class="pager__gap" aria-hidden="true">…</li>';
      html += `<li><button type="button" class="pager__page num" data-page="${p}" ${p === cur ? 'aria-current="page"' : ''}
        aria-label="الصفحة ${p}">${p}</button></li>`;
    });
    html += `</ol><button type="button" class="pager__step" data-page="${cur + 1}" ${cur === last ? 'disabled' : ''}>
      التالية <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>`;

    nav.hidden = false;
    nav.innerHTML = html;
  }

  function activeCount() {
    return ['q', 'program', 'country', 'gender', 'age', 'urgent'].filter((k) => state[k]).length;
  }

  // من صفحة البرامج: ?program=education يصل كما هو
  load();
}

/* ── بلا خادم: الاستعلام نفسه على البيانات المضمَّنة ───────────────────── */
function localQuery(state) {
  const genderOf = (o) => (['أنثى', 'female'].includes(o.gender) ? 'female' : 'male');
  const ageOf = (o) => (o.age <= 5 ? '0-5' : o.age <= 10 ? '6-10' : '11-18');
  const q = (state.q || '').toLowerCase();
  const match = (o, except = '') => (
    (except === 'program' || !state.program || o.program === state.program)
    && (except === 'country' || !state.country || o.country === state.country)
    && (except === 'gender' || !state.gender || genderOf(o) === state.gender)
    && (except === 'age' || !state.age || ageOf(o) === state.age)
    && (except === 'urgent' || !state.urgent || o.urgent)
    && (!q || `${o.name} ${o.city} ${o.country} ${o.id}`.toLowerCase().includes(q))
  );
  const tally = (except, key) => ORPHANS.filter((o) => match(o, except)).reduce((t, o) => ({ ...t, [key(o)]: (t[key(o)] ?? 0) + 1 }), {});

  const SORT = {
    urgent: (a, b) => Number(b.urgent) - Number(a.urgent) || a.funded - b.funded,
    funded_asc: (a, b) => a.funded - b.funded,
    monthly_asc: (a, b) => a.monthly - b.monthly,
    monthly_desc: (a, b) => b.monthly - a.monthly,
    age_asc: (a, b) => a.age - b.age,
    newest: () => 0,
  };
  const all = ORPHANS.filter((o) => match(o)).sort(SORT[state.sort] ?? SORT.urgent);
  const last = Math.max(1, Math.ceil(all.length / PER_PAGE));
  const page = Math.min(last, Math.max(1, Number(state.page) || 1));

  return {
    data: all.slice((page - 1) * PER_PAGE, page * PER_PAGE),
    meta: {
      total: all.length, per_page: PER_PAGE, current_page: page, last_page: last,
      facets: {
        program: tally('program', (o) => o.program),
        country: tally('country', (o) => o.country),
        gender: tally('gender', genderOf),
        age: tally('age', ageOf),
        urgent: ORPHANS.filter((o) => match(o, 'urgent') && o.urgent).length,
        all: ORPHANS.length,
      },
    },
  };
}

/* ── الرابط: كل فلتر وصفحة فيه ───────────────────────────────────────── */
function readUrl() {
  const params = new URLSearchParams(window.location.search);
  return Object.fromEntries(KEYS.map((k) => [k, params.get(k) ?? '']));
}

function writeUrl(state, push) {
  const params = new URLSearchParams();
  for (const k of KEYS) if (state[k]) params.set(k, state[k]);
  const url = `${window.location.pathname}${params.toString() ? `?${params}` : ''}`;
  // الصفحة تُضاف إلى السجل (زرّ الرجوع يعيد السابقة)، والفلتر يُستبدل
  history[push ? 'pushState' : 'replaceState'](null, '', url);
}

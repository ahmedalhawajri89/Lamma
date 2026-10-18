/**
 * صفحة الأيتام: فهرسٌ يُفلتر ويُصفّح على الخادم.
 *
 * الشريط يلصق تحت الترويسة: البرامج شرائح بأعدادها، والجنس والعمر والدولة
 * قوائم منسدلة على الشاشة العريضة ولوحٌ من الأسفل على الجوال، و«عاجلة»
 * والترتيب. النتائج دفعاتٌ من اثنتي عشرة بطاقة، و«عرض المزيد» يضيف الدفعة
 * التالية تحت ما قرأه الزائر بدل أن يقلب الصفحة عليه.
 *
 * وكل فلتر في الرابط، ومعه عدد الدفعات المعروضة (`page`): الرابط المُشارك
 * أو زرّ الرجوع يعيدان القائمة كما كانت، بطولها.
 */

import { $, $$, countUp, escapeHtml, debounce, formatNumber, keepFocus, prefersReducedMotion } from './utils.js';
import { ORPHANS, PROGRAMS } from './data.js';
import { kidCard } from './render.js';
import { apiEnabled, request } from './api.js';
import { initReveal } from './motion.js';
import { t, plural } from './i18n.js';

const PER_PAGE = 12;
/** أقصى ما يُطلب دفعةً واحدة حين يُفتح رابطٌ بعدة دفعات (حدّ الخادم 100). */
const MAX_BATCHES = 8;
const COLS = 'col-sm-6 col-lg-4 col-xl-3';
const KEYS = ['q', 'program', 'country', 'gender', 'age', 'urgent', 'sort', 'page'];
const FILTER_KEYS = ['q', 'program', 'country', 'gender', 'age', 'urgent'];
const GENDERS = [{ value: 'male', label: t('ولد') }, { value: 'female', label: t('بنت') }];
const AGES = [{ value: '0-5', label: t('حتى 5 سنوات') }, { value: '6-10', label: t('من 6 إلى 10') }, { value: '11-18', label: t('11 سنة فأكبر') }];
const SORTS = [
  { value: 'urgent', label: t('الأشد احتياجاً') },
  { value: 'funded_asc', label: t('الأقل تغطية') },
  { value: 'monthly_asc', label: t('الأقل مبلغاً') },
  { value: 'monthly_desc', label: t('الأعلى مبلغاً') },
  { value: 'age_asc', label: t('الأصغر سناً') },
  { value: 'newest', label: t('الأحدث إضافة') },
];
const TITLES = { gender: t('الجنس'), age: t('العمر'), country: t('الدولة') };

/** «1 طفل، 2 طفلان، 3 أطفال، 11 طفلاً» و«1 child، 3 children» */
const KIDS = {
  ar: { one: '{c} طفل', two: '{c} طفلان', few: '{c} أطفال', other: '{c} طفلاً' },
  en: { one: '{c} child', other: '{c} children' },
};
const kids = (n, c = formatNumber(n)) => plural(n, KIDS).replace('{c}', c);

export function initCatalog() {
  const grid = $('#catalogGrid');
  if (!grid) return;

  const state = readUrl();
  let facets = null;
  let sequence = 0;
  let loaded = 0;
  let total = 0;

  const sortSelect = $('#catalogSort');
  const search = $('[data-filter-search]');
  const more = $('#catalogMore');
  const moreBtn = $('[data-load-more]');

  sortSelect.innerHTML = SORTS.map((s) => `<option value="${s.value}">${s.label}</option>`).join('');
  // ترتيبٌ يقبله الخادم وليس في القائمة (رابطٌ يدوي) يُعرض «الأشد احتياجاً» لا خانةً فارغة
  sortSelect.value = SORTS.some((x) => x.value === state.sort) ? state.sort : 'urgent';
  sortSelect.addEventListener('change', () => update({ sort: sortSelect.value === 'urgent' ? '' : sortSelect.value }));

  search.value = state.q || '';
  search.addEventListener('input', debounce(() => update({ q: search.value.trim() }, { push: false }), 350));
  $('[data-catalog-search]').addEventListener('submit', (e) => {
    e.preventDefault();
    update({ q: search.value.trim() }, { scroll: true });
  });

  /* ── النقرات: خيار، إزالة، مسح، المزيد ─────────────────────────────── */
  document.addEventListener('click', (e) => {
    const inSheet = Boolean(e.target.closest('#filterSheet'));
    const opt = e.target.closest('[data-filter]');
    if (opt) {
      const { filter, value } = opt.dataset;
      update({ [filter]: state[filter] === value ? '' : value }, { scroll: !inSheet });
      return;
    }
    const remove = e.target.closest('[data-remove]');
    if (remove) { update({ [remove.dataset.remove]: '' }, { scroll: !inSheet }); return; }
    if (e.target.closest('[data-reset]')) {
      search.value = '';
      sortSelect.value = 'urgent';
      update(Object.fromEntries(KEYS.map((k) => [k, ''])), { scroll: !inSheet });
      return;
    }
    if (e.target.closest('[data-load-more]')) loadMore();
  });

  // يُغلق اللوح فتعود إلى أول النتائج التي اخترتها
  $('#filterSheet')?.addEventListener('hidden.bs.offcanvas', scrollToResults);

  window.addEventListener('popstate', () => {
    Object.assign(state, readUrl());
    search.value = state.q || '';
    sortSelect.value = state.sort || 'urgent';
    load();
  });

  function update(patch, { scroll = false, push = true } = {}) {
    Object.assign(state, patch, { page: '' });
    writeUrl(state, push);
    load().then(() => { if (scroll) scrollToResults(); });
  }

  /** إلى أول النتائج، إن كان الزائر قد نزل تحتها. */
  function scrollToResults() {
    const top = $('#catalogTop');
    if (top && window.scrollY > top.offsetTop) {
      top.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
    }
  }

  /* ── الجلب ───────────────────────────────────────────────────────────── */
  async function fetchPage(page, perPage) {
    const params = new URLSearchParams({ per_page: perPage, page, facets: 1, sort: state.sort || 'urgent' });
    for (const k of ['q', 'program', 'country', 'gender', 'age']) if (state[k]) params.set(k, state[k]);
    if (state.urgent) params.set('urgent', 1);
    try {
      if (apiEnabled()) return await request(`/public/orphans?${params}`);
    } catch { /* الخادم متوقف: البيانات المضمَّنة بالفلاتر نفسها، لا شاشة خطأ */ }
    return localQuery({ ...state, page }, perPage);
  }

  /** من الأول: كل الدفعات التي في الرابط بطلبٍ واحد. */
  async function load() {
    const mine = ++sequence;
    const restoreFocus = keepFocus();
    const batches = Math.min(MAX_BATCHES, Math.max(1, Number(state.page) || 1));
    grid.setAttribute('aria-busy', 'true');
    grid.classList.add('is-loading');

    const { data = [], meta = {} } = await fetchPage(1, PER_PAGE * batches);
    if (mine !== sequence) return;

    facets = meta.facets ?? facets;
    total = meta.total ?? data.length;
    loaded = data.length;

    grid.classList.remove('is-loading');
    grid.removeAttribute('aria-busy');
    grid.innerHTML = data.length
      ? data.map((o, i) => kidCard(o, { delay: Math.min(i, 7) * 60, cols: COLS })).join('')
      : `<div class="col-12"><div class="cat-empty">
           <span class="cat-empty__icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
           <h3>${t('لا أطفال بهذه الفلاتر')}</h3>
           <p>${t('جرّب دولة أخرى أو عمراً أوسع، أو أزل فلتراً.')}</p>
           <button class="btn btn-outline-primary" type="button" data-reset>${t('أزل كل الفلاتر')}</button>
         </div></div>`;

    renderAll();
    initReveal();
    restoreFocus($('#catalogTop'));
  }

  /** الدفعة التالية تُضاف تحت ما سبق، والتركيز على أول بطاقة جديدة. */
  async function loadMore() {
    if (loaded >= total) return;
    const mine = ++sequence;
    moreBtn.disabled = true;
    moreBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ' + t('جارٍ التحميل');

    const { data = [], meta = {} } = await fetchPage(Math.floor(loaded / PER_PAGE) + 1, PER_PAGE);
    moreBtn.disabled = false;
    moreBtn.textContent = t('عرض المزيد');
    if (mine !== sequence) return;

    total = meta.total ?? total;
    const first = loaded;
    grid.insertAdjacentHTML('beforeend', data.map((o, i) => kidCard(o, { delay: Math.min(i, 7) * 60, cols: COLS })).join(''));
    loaded += data.length;
    state.page = String(Math.ceil(loaded / PER_PAGE));
    writeUrl(state, false);

    renderMore();
    initReveal();
    $$('.kid-card__name a', grid)[first]?.focus({ preventScroll: true });
  }

  /* ── العرض ───────────────────────────────────────────────────────────── */
  function renderAll() {
    const any = FILTER_KEYS.some((k) => state[k]);
    const count = kids(total, `<strong class="num">${formatNumber(total)}</strong>`);
    $('#catalogCount').innerHTML = t(any ? '{kids} يطابقون اختيارك' : '{kids} ينتظرون كافلاً', { kids: count });
    renderPrograms();
    renderMenus();
    renderSheet();
    renderChips();
    renderMore();
  }

  function renderPrograms() {
    if (!facets) return;
    const all = Object.values(facets.program ?? {}).reduce((a, b) => a + b, 0);
    $('[data-group="program"]').innerHTML = [['', t('كل البرامج'), all], ...PROGRAMS.map((p) => [p.id, p.name, facets.program?.[p.id] ?? 0])]
      .map(([value, label, n]) => {
        const on = (state.program || '') === value;
        return `<button type="button" class="cat-program" ${value ? `data-filter="program" data-value="${escapeHtml(value)}"` : 'data-remove="program"'}
          aria-pressed="${on}" ${!n && !on && value ? 'disabled' : ''}>${escapeHtml(label)} <span class="num">${formatNumber(n)}</span></button>`;
      }).join('');
  }

  function optionsOf(key) {
    if (key === 'gender') return GENDERS.map((g) => [g.value, g.label, facets?.gender?.[g.value] ?? 0]);
    if (key === 'age') return AGES.map((a) => [a.value, a.label, facets?.age?.[a.value] ?? 0]);
    // القيمة الاسم العربي (ثابتة في الرابط لكل اللغات)، والمعروض بلغة الصفحة
    return Object.entries(facets?.country ?? {}).map(([c, n]) => [c, labelOf('country', c), n]);
  }

  function labelOf(key, value) {
    if (key === 'gender') return GENDERS.find((g) => g.value === value)?.label ?? value;
    if (key === 'age') return AGES.find((a) => a.value === value)?.label ?? value;
    return facets?.country_labels?.[value] ?? value;
  }

  /** القوائم المنسدلة: «الكل» ثم الخيارات بأعدادها، والمختار عليه علامة. */
  function renderMenus() {
    for (const key of Object.keys(TITLES)) {
      const menu = $(`[data-menu-for="${key}"]`);
      if (!menu) continue;
      menu.innerHTML = `<button type="button" class="dropdown-item cat-menu__opt" data-remove="${key}" aria-pressed="${!state[key]}">
          <i class="fa-solid fa-check" aria-hidden="true"></i><span>${t('الكل')}</span></button>
        ${optionsOf(key).map(([value, label, n]) => {
          const on = state[key] === value;
          return `<button type="button" class="dropdown-item cat-menu__opt" data-filter="${key}" data-value="${escapeHtml(value)}" aria-pressed="${on}" ${!n && !on ? 'disabled' : ''}>
            <i class="fa-solid fa-check" aria-hidden="true"></i><span>${escapeHtml(label)}</span><span class="num">${formatNumber(n)}</span></button>`;
        }).join('')}`;
      $(`[data-pill-value="${key}"]`).textContent = state[key] ? labelOf(key, state[key]) : '';
      $(`[data-pill="${key}"]`).classList.toggle('is-set', Boolean(state[key]));
    }
    $$('.cat-pill--urgent').forEach((b) => b.setAttribute('aria-pressed', String(Boolean(state.urgent))));
    $$('[data-urgent-count]').forEach((el) => { el.textContent = formatNumber(facets?.urgent ?? 0); });
  }

  /** لوح الجوال: الخيارات شرائح تُضغط، والنتائج تتحدّث خلفه. */
  function renderSheet() {
    const body = $('[data-sheet]');
    if (!body) return;
    const restore = keepFocus();
    const group = (key) => `<fieldset class="cat-sheet__group"><legend>${TITLES[key]}</legend><div class="cat-sheet__chips">
        ${optionsOf(key).map(([value, label, n]) => {
          const on = state[key] === value;
          return `<button type="button" class="cat-program" data-filter="${key}" data-value="${escapeHtml(value)}" aria-pressed="${on}" ${!n && !on ? 'disabled' : ''}>
            ${escapeHtml(label)} <span class="num">${formatNumber(n)}</span></button>`;
        }).join('')}</div></fieldset>`;
    body.innerHTML = `${group('gender')}${group('age')}${group('country')}
      <fieldset class="cat-sheet__group"><legend>${t('الحالة')}</legend><div class="cat-sheet__chips">
        <button type="button" class="cat-pill cat-pill--urgent" data-filter="urgent" data-value="1" aria-pressed="${Boolean(state.urgent)}">
          <span class="cat-pill__dot" aria-hidden="true"></span> ${t('عاجلة فقط')} <span class="num">${formatNumber(facets?.urgent ?? 0)}</span></button>
      </div></fieldset>`;
    restore();

    const apply = $('[data-sheet-apply]');
    if (apply) apply.textContent = total ? t('اعرض {kids}', { kids: kids(total) }) : t('لا نتائج');
    const badge = $('[data-filter-count]');
    const n = ['gender', 'age', 'country', 'urgent'].filter((k) => state[k]).length;
    badge.hidden = !n;
    badge.textContent = formatNumber(n);
  }

  function renderChips() {
    const programName = (slug) => PROGRAMS.find((p) => p.id === slug)?.name ?? slug;
    const chips = [
      state.q && ['q', t('«{q}»', { q: state.q })],
      state.program && ['program', programName(state.program)],
      state.gender && ['gender', labelOf('gender', state.gender)],
      state.age && ['age', labelOf('age', state.age)],
      state.country && ['country', labelOf('country', state.country)],
      state.urgent && ['urgent', t('حالات عاجلة')],
    ].filter(Boolean);

    const box = $('#activeFilters');
    box.hidden = !chips.length;
    box.innerHTML = chips.map(([k, label]) => `<button type="button" class="cat-chip" data-remove="${k}" aria-label="${escapeHtml(t('إزالة فلتر {label}', { label }))}">
        ${escapeHtml(label)} <i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`).join('')
      + (chips.length > 1 ? `<button type="button" class="cat-chip cat-chip--clear" data-reset>${t('مسح الكل')}</button>` : '');
  }

  /** «عرضنا 24 من 137» بشريط يمتلئ، والزرّ يختفي حين لا يبقى شيء. */
  function renderMore() {
    more.hidden = loaded >= total && total <= PER_PAGE;
    $('#catalogRange').textContent = t('عرضنا {loaded} من {kids}', { loaded: formatNumber(loaded), kids: kids(total) });
    $('#catalogProgress').style.setProperty('--v', `${total ? Math.round((loaded / total) * 100) : 0}%`);
    moreBtn.hidden = loaded >= total;
  }

  initHero();
  load();
}

/* ── الصدر: أرقامٌ تعدّ صعوداً مرة واحدة ─────────────────────────────── */
async function initHero() {
  const stats = $$('[data-hero-stat]');
  if (!stats.length) return;

  let all;
  try {
    if (!apiEnabled()) throw new Error('offline');
    all = await request('/public/orphans?per_page=1&facets=1');
  } catch {
    all = localQuery({});
  }

  const f = all.meta?.facets ?? {};
  const values = { all: f.all ?? all.meta?.total ?? 0, urgent: f.urgent ?? 0, countries: Object.keys(f.country ?? {}).length };
  stats.forEach((el) => countUp(el, values[el.dataset.heroStat] ?? 0));
}

/* ── بلا خادم: الاستعلام نفسه على البيانات المضمَّنة ───────────────────── */
function localQuery(state, perPage = PER_PAGE) {
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
  const last = Math.max(1, Math.ceil(all.length / perPage));
  const page = Math.min(last, Math.max(1, Number(state.page) || 1));

  return {
    data: all.slice((page - 1) * perPage, page * perPage),
    meta: {
      total: all.length, per_page: perPage, current_page: page, last_page: last,
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

/* ── الرابط: كل فلتر فيه، و`page` عدد الدفعات المعروضة ───────────────── */
function readUrl() {
  const params = new URLSearchParams(window.location.search);
  return Object.fromEntries(KEYS.map((k) => [k, params.get(k) ?? '']));
}

function writeUrl(state, push) {
  const params = new URLSearchParams();
  for (const k of KEYS) if (state[k]) params.set(k, state[k]);
  const url = `${window.location.pathname}${params.toString() ? `?${params}` : ''}`;
  // الفلتر يُضاف إلى السجل (زرّ الرجوع يعيد ما قبله)، والبحث أثناء الكتابة و«المزيد» يُستبدلان
  history[push ? 'pushState' : 'replaceState'](null, '', url);
}

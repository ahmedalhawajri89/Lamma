/**
 * صفحة الأيتام: فهرسٌ يُصفّح ويُفلتر على الخادم.
 *
 * كانت الصفحة تطلب أول مئة طفل وتفلترهم في المتصفح، فمن بعد المئة لا يظهر
 * لأحد (137 طفلاً موثّقاً، 37 منهم بلا نافذة)، وتعرضهم كلهم في صفحة بطول
 * ثمانية عشر ألف بكسل. هنا الخادم يفلتر ويعدّ ويصفّح، والصفحة تعرض اثنتي
 * عشرة بطاقة، أولاها أعرض للأشد احتياجاً.
 *
 * الاختيار شريطٌ أفقي فوق النتائج: البرامج أزرارٌ بأعدادها، والجنس والعمر
 * والدولة أزرارٌ تفتح قوائم صغيرة (من الأسفل على الجوال)، و«عاجلة»، والبحث
 * والترتيب. وكل فلتر وصفحة في الرابط: يُشارَك ويُرجَع إليه بزرّ الرجوع.
 */

import { $, $$, escapeHtml, debounce, formatNumber, prefersReducedMotion } from './utils.js';
import { ORPHANS, PROGRAMS } from './data.js';
import { orphanCard } from './render.js';
import { apiEnabled, request } from './api.js';
import { initReveal } from './motion.js';

const PER_PAGE = 12;
const KEYS = ['q', 'program', 'country', 'gender', 'age', 'urgent', 'sort', 'page'];
const GENDERS = [{ value: 'male', label: 'ولد' }, { value: 'female', label: 'بنت' }];
const AGES = [{ value: '0-5', label: 'حتى 5 سنوات' }, { value: '6-10', label: 'من 6 إلى 10' }, { value: '11-18', label: '11 سنة فأكبر' }];
const SORTS = [
  { value: 'urgent', label: 'الأشد احتياجاً' },
  { value: 'funded_asc', label: 'الأقل تغطية' },
  { value: 'monthly_asc', label: 'الأقل مبلغاً' },
  { value: 'monthly_desc', label: 'الأعلى مبلغاً' },
  { value: 'age_asc', label: 'الأصغر سناً' },
  { value: 'newest', label: 'الأحدث إضافة' },
];
const MENU_TITLES = { gender: 'الجنس', age: 'العمر', country: 'الدولة' };

export function initCatalog() {
  const grid = $('#catalogGrid');
  if (!grid) return;

  const state = readUrl();
  let facets = null;
  let sequence = 0;
  let openMenu = null;

  const sortSelect = $('#catalogSort');
  const search = $('[data-filter-search]');
  const menu = $('#filterMenu');

  if (sortSelect) {
    sortSelect.innerHTML = SORTS.map((s) => `<option value="${s.value}">${s.label}</option>`).join('');
    sortSelect.value = state.sort || 'urgent';
    sortSelect.addEventListener('change', () => update({ sort: sortSelect.value === 'urgent' ? '' : sortSelect.value }));
  }
  if (search) {
    search.value = state.q || '';
    search.addEventListener('input', debounce(() => update({ q: search.value.trim() }), 300));
  }

  /* ── النقرات: خيار، إزالة، صفحة، قائمة ──────────────────────────────── */
  document.addEventListener('click', (e) => {
    const pill = e.target.closest('[data-menu]');
    if (pill) { toggleMenu(pill); return; }

    const opt = e.target.closest('[data-filter]');
    if (opt) {
      const { filter, value } = opt.dataset;
      closeMenu();
      update({ [filter]: state[filter] === value ? '' : value });
      return;
    }
    const remove = e.target.closest('[data-remove]');
    if (remove) { closeMenu(); update({ [remove.dataset.remove]: '' }); return; }
    if (e.target.closest('[data-reset]')) {
      if (search) search.value = '';
      if (sortSelect) sortSelect.value = 'urgent';
      update(Object.fromEntries(KEYS.map((k) => [k, ''])));
      return;
    }
    const page = e.target.closest('[data-page]');
    if (page && !page.disabled) {
      state.page = page.dataset.page === '1' ? '' : page.dataset.page;
      writeUrl(state, true);
      load({ scroll: true });
      return;
    }
    if (openMenu && !e.target.closest('#filterMenu')) closeMenu();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && openMenu) { const p = openMenu; closeMenu(); p.focus(); }
  });

  window.addEventListener('popstate', () => {
    Object.assign(state, readUrl());
    if (search) search.value = state.q || '';
    if (sortSelect) sortSelect.value = state.sort || 'urgent';
    load();
  });

  function update(patch) {
    Object.assign(state, patch, { page: '' });
    writeUrl(state, false);
    load();
  }

  /* ── قائمة الجنس والعمر والدولة ─────────────────────────────────────── */
  function toggleMenu(pill) {
    if (openMenu === pill) { closeMenu(); return; }
    closeMenu();
    const key = pill.dataset.menu;
    const options = key === 'gender' ? GENDERS.map((g) => [g.value, g.label, facets?.gender?.[g.value]])
      : key === 'age' ? AGES.map((a) => [a.value, a.label, facets?.age?.[a.value]])
        : Object.entries(facets?.country ?? {}).map(([c, n]) => [c, c, n]);

    menu.innerHTML = `<p class="fmenu__title">${MENU_TITLES[key]}</p>
      <ul class="fmenu__list">${options.map(([value, label, n]) => {
        const on = state[key] === value;
        return `<li><button type="button" class="fmenu__opt" data-filter="${key}" data-value="${escapeHtml(value)}" aria-pressed="${on}" ${!n && !on ? 'disabled' : ''}>
          <span><i class="fa-solid fa-check" aria-hidden="true"></i>${escapeHtml(label)}</span><span class="num">${formatNumber(n ?? 0)}</span></button></li>`;
      }).join('')}</ul>
      ${state[key] ? `<button type="button" class="fmenu__clear" data-remove="${key}">إلغاء اختيار ${MENU_TITLES[key]}</button>` : ''}`;

    // تحت زرّه، محاذاةً لبدايته (يمين الصفحة العربية)
    const bar = pill.closest('.fbar').getBoundingClientRect();
    const r = pill.getBoundingClientRect();
    menu.style.setProperty('--menu-top', `${r.bottom - bar.top + 8}px`);
    menu.style.setProperty('--menu-right', `${bar.right - r.right}px`);
    menu.hidden = false;
    menu.setAttribute('aria-label', MENU_TITLES[key]);
    requestAnimationFrame(() => menu.classList.add('is-open'));
    pill.setAttribute('aria-expanded', 'true');
    openMenu = pill;
    $('.fmenu__opt:not(:disabled)', menu)?.focus({ preventScroll: true });
  }

  function closeMenu() {
    if (!openMenu) return;
    openMenu.setAttribute('aria-expanded', 'false');
    openMenu = null;
    menu.classList.remove('is-open');
    menu.hidden = true;
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

    // الأولى أعرض حين تُعرض الصفحة الأولى بالترتيب الافتراضي: الأشد احتياجاً
    const spotlight = !state.page && !state.sort && data.length > 2;
    grid.removeAttribute('aria-busy');
    grid.classList.remove('is-swapping');
    void grid.offsetWidth; // يعيد تشغيل الانتقال الهادئ بين النتائج
    grid.classList.add('is-swapping');
    grid.innerHTML = data.length
      // التأخير في الصفحة الواحدة وحدها، وبسقف: اثنتا عشرة بطاقة لا مئة
      ? data.map((o, i) => orphanCard(o, { delay: Math.min(i, 8) * 55, spotlight: spotlight && i === 0 })).join('')
      : `<div class="catalog-empty">
           <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
           <h3>لا أطفال بهذه الفلاتر</h3>
           <p>جرّب دولة أخرى أو عمراً أوسع، أو أزل فلتراً.</p>
           <button class="btn btn--outline" type="button" data-reset>أزل كل الفلاتر</button>
         </div>`;

    renderSummary(meta);
    renderBar();
    renderChips();
    renderPager(meta);
    initReveal();

    if (scroll) $('#catalogTop')?.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
  }

  function renderSummary(meta) {
    const total = meta.total ?? 0;
    const from = total ? (meta.current_page - 1) * meta.per_page + 1 : 0;
    const to = Math.min(total, meta.current_page * meta.per_page);
    $('#catalogCount').innerHTML = `<strong class="num">${formatNumber(total)}</strong> ${total === 1 ? 'طفل' : 'طفلاً'}`;
    // «من 13 إلى 24» لا «13–24»: الشرطة بين رقمين تنقلب في سطرٍ عربي فتُقرأ «24–13»
    $('#catalogRange').textContent = total > PER_PAGE ? `يُعرض من ${from} إلى ${to}` : '';
  }

  /* ── الشريط: البرامج بأعدادها، وقيم الأزرار، و«عاجلة» ──────────────── */
  function renderBar() {
    if (!facets) return;
    const programs = $('[data-group="program"]');
    const allCount = Object.values(facets.program ?? {}).reduce((a, b) => a + b, 0);
    programs.innerHTML = [['', 'كل البرامج', allCount], ...PROGRAMS.map((p) => [p.id, p.name, facets.program?.[p.id] ?? 0])]
      .map(([value, label, n]) => {
        const on = (state.program || '') === value;
        return `<button type="button" class="seg" ${value ? `data-filter="program" data-value="${escapeHtml(value)}"` : 'data-remove="program"'}
          aria-pressed="${on}" ${!n && !on && value ? 'disabled' : ''}>${escapeHtml(label)} <span class="seg__count num">${formatNumber(n)}</span></button>`;
      }).join('');

    const labelOf = { gender: GENDERS, age: AGES };
    for (const key of ['gender', 'age', 'country']) {
      const v = state[key];
      const text = !v ? '' : key === 'country' ? v : labelOf[key].find((x) => x.value === v)?.label ?? v;
      $(`[data-pill-value="${key}"]`).textContent = text ? `: ${text}` : '';
      $(`[data-menu="${key}"]`).classList.toggle('is-set', Boolean(v));
    }
    $('.pill--urgent')?.setAttribute('aria-pressed', String(Boolean(state.urgent)));
    $('[data-urgent-count]').textContent = formatNumber(facets.urgent ?? 0);
  }

  function renderChips() {
    const programName = (slug) => PROGRAMS.find((p) => p.id === slug)?.name ?? slug;
    const chips = [
      state.q && ['q', `«${state.q}»`],
      state.program && ['program', programName(state.program)],
      state.gender && ['gender', GENDERS.find((g) => g.value === state.gender)?.label],
      state.age && ['age', AGES.find((a) => a.value === state.age)?.label],
      state.country && ['country', state.country],
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

  initHero();
  load();
}

/* ── الصدر: أرقامٌ تعدّ صعوداً، ووجوهٌ ممن لهم صور ───────────────────── */
async function initHero() {
  const stats = $$('[data-hero-stat]');
  const faces = $('#heroFaces');
  if (!stats.length) return;

  let all; let photos = [];
  try {
    if (!apiEnabled()) throw new Error('offline');
    [all, photos] = await Promise.all([
      request('/public/orphans?per_page=1&facets=1'),
      request('/public/orphans?per_page=4&photo=1').then((r) => r.data ?? []).catch(() => []),
    ]);
  } catch {
    all = localQuery({});
    photos = ORPHANS.filter((o) => o.photo).slice(0, 4);
  }

  const f = all.meta?.facets ?? {};
  const values = { all: f.all ?? all.meta?.total ?? 0, urgent: f.urgent ?? 0, countries: Object.keys(f.country ?? {}).length };
  stats.forEach((el) => countUp(el, values[el.dataset.heroStat] ?? 0));

  if (faces && photos.length) {
    faces.innerHTML = photos.slice(0, 4).map((o, i) => `<figure class="kids-hero__face" style="--i:${i}">
        <img src="${escapeHtml(o.photo)}" alt="" loading="lazy" width="240" height="300">
        <figcaption>${escapeHtml(o.name)}، <span class="num">${Number(o.age)}</span></figcaption></figure>`).join('');
    requestAnimationFrame(() => faces.classList.add('is-in'));
  } else if (faces) {
    faces.remove();
  }
}

/** عدٌّ صاعد مرة واحدة، أقل من ثانية، وبلا حركة لمن طلب تقليلها */
function countUp(el, target) {
  if (prefersReducedMotion() || !target) { el.textContent = formatNumber(target); return; }
  const start = performance.now();
  const dur = 900;
  const tick = (now) => {
    const t = Math.min(1, (now - start) / dur);
    el.textContent = formatNumber(Math.round(target * (1 - (1 - t) ** 3)));
    if (t < 1) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
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

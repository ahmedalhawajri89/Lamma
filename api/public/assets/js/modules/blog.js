/**
 * المدونة: مجلة تحريرية.
 *
 * كانت شبكة بطاقات وزرّ «تحميل مقالات أقدم»، بلا مقال مميّز ولا تصنيفات ولا
 * بحث. هنا: المقال المميّز عريضاً فوق، ثم التصنيفات بأعدادها وبحث، ثم شبكة
 * بطاقات بصفحات مرقّمة. التصنيف والبحث والصفحة في الرابط (?cat=&q=&page=)،
 * وزرّ الرجوع يعيد ما كان.
 *
 * المميّز لا يتكرر في الشبكة (exclude)، ويختفي حين يُبحث أو يُختار تصنيف:
 * عندها النتائج هي الصفحة.
 *
 * بلا خادم: المقالات المضمَّنة في data.js تُفلتر وتُصفّح هنا.
 */

import { $, $$, arabicDigits, debounce, escapeHtml, formatNumber, keepFocus, prefersReducedMotion, readingTime } from './utils.js';
import { POSTS } from './data.js';
import { apiEnabled, request } from './api.js';
import { initReveal } from './motion.js';

const PER_PAGE = 9;
const KEYS = ['cat', 'q', 'page'];

export function initBlog() {
  const grid = $('#blogGrid');
  if (!grid) return;

  const state = readUrl();
  let facets = null;
  let sequence = 0;
  const search = $('#blogSearch');

  if (search) {
    search.value = state.q;
    search.addEventListener('input', debounce(() => update({ q: search.value.trim() }), 300));
  }

  document.addEventListener('click', (e) => {
    const cat = e.target.closest('[data-cat]');
    if (cat) { update({ cat: cat.dataset.cat === state.cat ? '' : cat.dataset.cat }); return; }
    if (e.target.closest('[data-blog-reset]')) {
      if (search) search.value = '';
      update({ cat: '', q: '' });
      return;
    }
    const page = e.target.closest('#blogPager [data-page]');
    if (page && !page.disabled) {
      state.page = page.dataset.page === '1' ? '' : page.dataset.page;
      writeUrl(state, true);
      load({ scroll: true });
    }
  });

  window.addEventListener('popstate', () => {
    Object.assign(state, readUrl());
    if (search) search.value = state.q;
    load();
  });

  function update(patch) {
    Object.assign(state, patch, { page: '' });
    writeUrl(state, false);
    load();
  }

  /* ── أول تحميل: التصنيفات والمميّز، ثم المقالات ── */
  (async () => {
    facets = await fetchFacets();
    load();
  })();

  async function fetchFacets() {
    if (apiEnabled()) {
      try {
        const { meta } = await request('/public/posts?per_page=1&facets=1');
        if (meta?.categories) return meta;
      } catch { /* بلا خادم: البذرة */ }
    }
    return localFacets();
  }

  async function load({ scroll = false } = {}) {
    const mine = ++sequence;
    const restoreFocus = keepFocus();
    grid.setAttribute('aria-busy', 'true');
    const filtered = Boolean(state.cat || state.q);
    const featured = !filtered ? facets?.featured : null;

    const params = new URLSearchParams({ per_page: PER_PAGE });
    if (state.cat) params.set('category', state.cat);
    if (state.q) params.set('q', state.q);
    if (state.page) params.set('page', state.page);
    if (featured?.id) params.set('exclude', featured.id);

    let payload = null;
    if (apiEnabled()) {
      try { payload = await request(`/public/posts?${params}`); } catch { payload = null; }
    }
    if (mine !== sequence) return;
    payload ??= localQuery(state, featured);

    const { data = [], meta = {} } = payload;

    // صفحةٌ بعد آخر الصفحات (رابط قديم): إلى الأخيرة
    if (!data.length && meta.current_page > 1) {
      state.page = meta.last_page > 1 ? String(meta.last_page) : '';
      writeUrl(state, false);
      load();
      return;
    }

    renderFeatured(!state.page ? featured : null);
    renderCats();
    renderStatus(meta, filtered);

    grid.removeAttribute('aria-busy');
    grid.innerHTML = data.length
      ? data.map((p, i) => magCard(p, Math.min(i, 6) * 60)).join('')
      : `<div class="blog-empty">
           <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
           <h3>لا مقالات بهذا البحث</h3>
           <p>جرّب كلمة أخرى، أو تصفّح كل المقالات.</p>
           <button type="button" class="btn btn--outline" data-blog-reset>كل المقالات</button>
         </div>`;
    renderPager(meta);
    initReveal();

    if (scroll) $('#blogTop')?.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'start' });
    restoreFocus($('#blogTop'), scroll);
  }

  /* ── المميّز: عريض، الصورة في نصف والقصة في نصف ── */
  function renderFeatured(post) {
    const host = $('#blogFeatured');
    if (!post) { host.innerHTML = ''; return; }
    const href = post.slug ? `/post?slug=${encodeURIComponent(post.slug)}` : null;
    host.innerHTML = `
      <article class="feature" data-reveal>
        <div class="feature__media">${post.image ? `<img src="${escapeHtml(post.image)}" alt="" width="900" height="600">` : '<span class="feature__mark"></span>'}</div>
        <div class="feature__body">
          <p class="feature__eyebrow"><span class="feature__tag">مقال مميّز</span>${post.category ? ` · ${escapeHtml(post.category)}` : ''}</p>
          <h2 class="feature__title">${href ? `<a href="${href}">${escapeHtml(post.title)}</a>` : escapeHtml(post.title)}</h2>
          <p class="feature__excerpt">${escapeHtml(post.excerpt ?? '')}</p>
          <p class="feature__meta">${meta(post)}</p>
          ${href ? `<span class="feature__cta" aria-hidden="true">اقرأ المقال <i class="fa-solid fa-arrow-left"></i></span>` : ''}
        </div>
      </article>`;
  }

  /* ── التصنيفات بأعدادها ── */
  function renderCats() {
    const host = $('#blogCats');
    if (!facets) return;
    const all = [['', 'الكل', facets.all_count ?? 0], ...(facets.categories ?? []).map((c) => [c.slug, c.name, c.count])];
    host.innerHTML = all.map(([slug, name, n]) => `<button type="button" class="seg" data-cat="${escapeHtml(slug)}" aria-pressed="${(state.cat || '') === slug}">
        ${escapeHtml(name)} <span class="seg__count num">${formatNumber(n)}</span></button>`).join('');
    $('#blogTotal').textContent = formatNumber(facets.all_count ?? 0);
  }

  function renderStatus(meta, filtered) {
    const el = $('#blogStatus');
    if (!filtered) { el.textContent = ''; return; }
    const cat = facets?.categories?.find((c) => c.slug === state.cat)?.name;
    const parts = [cat && `في «${cat}»`, state.q && `عن «${state.q}»`].filter(Boolean).join(' ');
    el.innerHTML = `<b class="num">${formatNumber(meta.total ?? 0)}</b> ${meta.total === 1 ? 'مقال' : 'مقالاً'} ${escapeHtml(parts)}
      <button type="button" class="blog-status__clear" data-blog-reset>مسح</button>`;
  }

  function renderPager(meta) {
    const nav = $('#blogPager');
    const last = meta.last_page ?? 1;
    const cur = meta.current_page ?? 1;
    if (last <= 1) { nav.hidden = true; nav.innerHTML = ''; return; }
    const pages = [...Array(last).keys()].map((i) => i + 1).filter((p) => p === 1 || p === last || Math.abs(p - cur) <= 1);
    let html = `<button type="button" class="pager__step" data-page="${cur - 1}" ${cur === 1 ? 'disabled' : ''}><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> الأحدث</button><ol class="pager__pages">`;
    pages.forEach((p, i) => {
      if (i && p - pages[i - 1] > 1) html += '<li class="pager__gap" aria-hidden="true">…</li>';
      html += `<li><button type="button" class="pager__page num" data-page="${p}" ${p === cur ? 'aria-current="page"' : ''} aria-label="الصفحة ${p}">${p}</button></li>`;
    });
    html += `</ol><button type="button" class="pager__step" data-page="${cur + 1}" ${cur === last ? 'disabled' : ''}>الأقدم <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>`;
    nav.hidden = false;
    nav.innerHTML = html;
  }
}

/** التاريخ وزمن القراءة */
function meta(post) {
  return `<time datetime="${escapeHtml(post.date ?? '')}">${escapeHtml(arabicDigits(post.dateLabel ?? ''))}</time>
    <span aria-hidden="true">·</span> <span>${readingTime(post.readMin)} قراءة</span>`;
}

/**
 * بطاقة مجلة: صورة 16:10 بشارة التصنيف، ثم العنوان والمقتطف بسطرين، والتاريخ
 * وزمن القراءة. البطاقة كلها تُضغط عبر رابط العنوان الممتد.
 */
function magCard(post, delay) {
  const href = post.slug ? `/post?slug=${encodeURIComponent(post.slug)}` : null;
  return `
<article class="mag-card" data-reveal data-reveal-delay="${delay}">
  <div class="mag-card__media">
    ${post.image ? `<img src="${escapeHtml(post.image)}" alt="" loading="lazy" width="480" height="300">` : '<span class="mag-card__mark"></span>'}
    ${post.category ? `<span class="mag-card__tag">${escapeHtml(post.category)}</span>` : ''}
  </div>
  <div class="mag-card__body">
    <h3 class="mag-card__title">${href ? `<a href="${href}">${escapeHtml(post.title)}</a>` : escapeHtml(post.title)}</h3>
    <p class="mag-card__excerpt">${escapeHtml(post.excerpt ?? '')}</p>
    <p class="mag-card__meta">${meta(post)}</p>
  </div>
</article>`;
}

/* ── بلا خادم: المقالات المضمَّنة ── */
function localFacets() {
  const counts = POSTS.reduce((t, p) => (p.category ? { ...t, [p.category]: (t[p.category] ?? 0) + 1 } : t), {});
  return {
    all_count: POSTS.length,
    categories: Object.entries(counts).map(([name, count]) => ({ slug: name, name, count })),
    featured: POSTS.find((p) => p.featured) ?? POSTS[0] ?? null,
  };
}

function localQuery(state, featured) {
  const q = state.q.toLowerCase();
  const list = POSTS.filter((p) => p !== featured
    && (!state.cat || (p.category_slug ?? p.category) === state.cat)
    && (!q || `${p.title} ${p.excerpt}`.toLowerCase().includes(q)));
  const last = Math.max(1, Math.ceil(list.length / PER_PAGE));
  const page = Math.min(last, Math.max(1, Number(state.page) || 1));
  return { data: list.slice((page - 1) * PER_PAGE, page * PER_PAGE), meta: { total: list.length, current_page: page, last_page: last } };
}

function readUrl() {
  const params = new URLSearchParams(window.location.search);
  return Object.fromEntries(KEYS.map((k) => [k, params.get(k) ?? '']));
}

function writeUrl(state, push) {
  const params = new URLSearchParams();
  for (const k of KEYS) if (state[k]) params.set(k, state[k]);
  const url = `${window.location.pathname}${params.toString() ? `?${params}` : ''}`;
  history[push ? 'pushState' : 'replaceState'](null, '', url);
}

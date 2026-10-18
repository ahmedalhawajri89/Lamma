/**
 * البحث السريع: نافذة تُفتح بـ Ctrl/⌘+K أو «/» أو زر الترويسة.
 *
 * قبل الكتابة تعرض اختصارات الموقع (يرسمها الخادم في <template> بلغة
 * الصفحة)، وأثناءها أطفالاً من /public/orphans?q= (الاسم أو المدينة أو
 * الدولة، باللغتين). الحقل combobox والقائمة listbox: الأسهم تنقل الاختيار
 * (aria-activedescendant) والتركيز يبقى في الحقل، وEnter يفتح المختار، وبلا
 * اختيار يرسل النموذج إلى الفهرس بالكلمة نفسها.
 *
 * <dialog> الأصلي يحبس التركيز ويغلق بـ Escape ويعيده لما فتحه.
 */

import { $, $$, escapeHtml, debounce } from './utils.js';
import { request, apiEnabled } from './api.js';
import { t, plural, url } from './i18n.js';

const PER_PAGE = 6;

const ageLabel = (age) => plural(age, {
  ar: { few: '{n} سنوات', other: '{n} سنة' },
  en: { one: '{n} year old', other: '{n} years old' },
});

export function initSearch() {
  const dialog = $('#siteSearch');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  const form = $('[data-search-form]', dialog);
  const input = $('#siteSearchInput', dialog);
  const list = $('#siteSearchList', dialog);
  const heading = $('[data-search-heading]', dialog);
  const status = $('[data-search-status]', dialog);
  const shortcuts = $('[data-search-shortcuts]', dialog);
  const shortcutsLabel = heading.textContent;

  let active = -1;
  let sequence = 0;

  // ⌘K على Mac وCtrl K على غيره
  const mac = /Mac|iPhone|iPad/.test(navigator.userAgentData?.platform ?? navigator.platform ?? '');
  $$('[data-search-kbd]').forEach((el) => { el.textContent = mac ? '⌘ K' : 'Ctrl K'; });

  /* ── الفتح والإغلاق ─────────────────────────────────────────────────── */
  let opener = null;

  function open() {
    if (dialog.open) return;
    opener = document.activeElement;
    // من الدرج: يُغلق أولاً فلا تتراكب نافذتان
    const drawer = $('#siteNav.show');
    if (drawer) window.bootstrap?.Offcanvas.getInstance(drawer)?.hide();
    dialog.showModal();
    input.select();
    render(input.value.trim());
  }

  function close() {
    if (dialog.open) dialog.close();
  }

  $$('[data-search-open]').forEach((btn) => btn.addEventListener('click', open));
  $('[data-search-close]', dialog).addEventListener('click', close);
  // نقرة على الخلفية (خارج اللوح) تغلق
  dialog.addEventListener('click', (e) => { if (e.target === dialog) close(); });

  // التركيز يعود لما فتح النافذة؛ وإن كان مخفياً (زر الدرج بعد إغلاقه) فلزرّ الترويسة الظاهر
  dialog.addEventListener('close', () => {
    const back = opener?.isConnected && opener.checkVisibility?.() !== false
      ? opener
      : $$('[data-search-open]').find((b) => b.checkVisibility?.() !== false);
    back?.focus({ preventScroll: true });
  });

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && !e.altKey && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      dialog.open ? close() : open();
      return;
    }
    // «/» إلا وأنت تكتب في حقلٍ آخر
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !dialog.open && !typing(e.target)) {
      e.preventDefault();
      open();
    }
  });

  /* ── الكتابة والنتائج ───────────────────────────────────────────────── */
  input.addEventListener('input', debounce(() => render(input.value.trim()), 200));

  async function render(q) {
    const mine = ++sequence;
    setActive(-1);

    if (!q) {
      heading.textContent = shortcutsLabel;
      list.replaceChildren(shortcuts.content.cloneNode(true));
      finish();
      return;
    }

    heading.textContent = t('أطفال');
    list.innerHTML = Array.from({ length: 3 }, () => '<li class="kf-search__skel" aria-hidden="true"><span></span><span></span></li>').join('');

    let data = [];
    let total = 0;
    try {
      if (!apiEnabled()) throw new Error('offline');
      const res = await request(`/public/orphans?${new URLSearchParams({ q, per_page: PER_PAGE })}`);
      data = res.data ?? [];
      total = res.meta?.total ?? data.length;
    } catch {
      if (mine !== sequence) return;
      list.innerHTML = `<li class="kf-search__empty">${escapeHtml(t('تعذّر البحث الآن. حاول بعد قليل.'))}</li>`;
      status.textContent = t('تعذّر البحث الآن. حاول بعد قليل.');
      return;
    }
    if (mine !== sequence) return;

    list.innerHTML = data.length
      ? data.map(kidOption).join('')
        + (total > data.length ? linkOption(`/orphans?q=${encodeURIComponent(q)}`, t('كل النتائج لـ «{q}»', { q })) : '')
      : `<li class="kf-search__empty">${escapeHtml(t('لا طفل بهذا الاسم أو المكان.'))}</li>${linkOption('/orphans', t('تصفّح كل الأطفال'))}`;

    status.textContent = data.length
      ? plural(total, { ar: { one: 'نتيجة واحدة', two: 'نتيجتان', few: '{n} نتائج', other: '{n} نتيجة' }, en: { one: '1 result', other: '{n} results' } })
      : t('لا طفل بهذا الاسم أو المكان.');
    finish();
  }

  function linkOption(path, label) {
    return `<li class="kf-search__opt kf-search__opt--all" role="option">
      <a href="${url(path)}" tabindex="-1">
        <span class="kf-search__opt-icon" aria-hidden="true"><i class="fa-solid fa-arrow-left kf-dir-icon"></i></span>
        <span class="kf-search__opt-text"><strong>${escapeHtml(label)}</strong></span>
      </a></li>`;
  }

  function kidOption(o) {
    const place = [o.city, o.country].filter(Boolean).map(escapeHtml).join(escapeHtml(t('، ')));
    return `<li class="kf-search__opt" role="option">
      <a href="${url(`/orphan?id=${encodeURIComponent(o.id)}`)}" tabindex="-1">
        <img class="kf-search__kid" src="${escapeHtml(o.photo ?? '')}" alt="" width="40" height="40" loading="lazy">
        <span class="kf-search__opt-text">
          <strong>${escapeHtml(o.name)}<span class="kf-search__age">${escapeHtml(t('، '))}${escapeHtml(ageLabel(o.age))}</span></strong>
          <small>${place}</small>
        </span>
        <span class="kf-search__funded">${t('مكفول {p}', { p: `<b class="num">${Number(o.funded) || 0}${escapeHtml(t('٪'))}</b>` })}</span>
      </a></li>`;
  }

  /** معرّفات الخيارات لـ aria-activedescendant، والنقر بالفأرة يحدّد الخيار. */
  function finish() {
    options().forEach((li, i) => {
      li.id = `siteSearchOpt${i}`;
      li.addEventListener('mousemove', () => { if (active !== i) setActive(i, false); });
    });
  }

  const options = () => $$('.kf-search__opt', list);

  function setActive(i, scroll = true) {
    const opts = options();
    active = opts.length ? Math.max(-1, Math.min(i, opts.length - 1)) : -1;
    opts.forEach((li, n) => {
      li.classList.toggle('is-active', n === active);
      li.setAttribute('aria-selected', String(n === active));
    });
    if (active >= 0) {
      input.setAttribute('aria-activedescendant', opts[active].id);
      if (scroll) opts[active].scrollIntoView({ block: 'nearest' });
    } else {
      input.removeAttribute('aria-activedescendant');
    }
  }

  input.addEventListener('keydown', (e) => {
    const count = options().length;
    // حقل البحث يبتلع أول Escape ليمسح نصّه؛ هنا يغلق النافذة مباشرة
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1 >= count ? 0 : active + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active <= 0 ? count - 1 : active - 1); }
    else if (e.key === 'Enter' && active >= 0) {
      e.preventDefault();
      const link = $('a', options()[active]);
      if (link) window.location.assign(link.href);
    }
  });

  // Enter بلا اختيار: الفهرس بالكلمة، أو الفهرس كله إن كان الحقل فارغاً
  form.addEventListener('submit', (e) => {
    if (!input.value.trim()) {
      e.preventDefault();
      window.location.assign(url('/orphans'));
    }
  });
}

function typing(el) {
  return el instanceof HTMLElement && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
}

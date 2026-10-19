/**
 * بوابة الكافل: ما يبني `/dashboard`.
 *
 * كل رقم وصفّ هنا من `/portal/*`، فيصدق مع كل كافل لا مع كافل وهمي.
 * والصفحة تُتيح فعلاً ما يعد به الموقع: قراءة التقرير، ومراسلة الطفل عبر
 * المشرف، والإيقاف المؤقت أو الإنهاء بلا اتصال ولا إحراج، وتعديل الحساب.
 *
 * خمسة أقسام يظهر منها واحد (#overview و#children و#reports و#payments
 * و#account)، يتبع الرابط فيعمل زرّ الرجوع ويُفتح القسم من رابطٍ مباشر.
 * والشريط الجانبي والنوافذ من Bootstrap (يحمّله القالب قبل هذه الوحدة).
 */

import { $, $$, escapeHtml, formatMoney, formatNumber } from './utils.js';
import {
  me, logout, isSponsor, isAuthLoss, portalOverview, portalPayments, markReportSeen, markSignedIn, clearSignedIn,
  portalMessages, sendPortalMessage, portalLedger, updateSponsorshipCard, cancelSponsorship, setSponsorshipStatus, updateProfile, updatePassword, forgotPassword,
} from './auth.js';
import { fetchCheckoutConfig } from './api.js';
import { toast } from './ui.js';
import { validateInput, tokenizeCard } from './forms.js';
import { url } from './i18n.js';

const dateFmt = new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'long', year: 'numeric' });
const shortFmt = new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'long' });
const todayFmt = new Intl.DateTimeFormat('ar', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const fmtDate = (v) => (v ? dateFmt.format(new Date(v)) : 'غير محدد');
const fmtShort = (v) => (v ? shortFmt.format(new Date(v)) : 'غير محدد');

/** العمر بصيغة العدد العربية: سنة، سنتان، ٣ إلى ١٠ سنوات، ثم ١١ سنة. */
function ageLabel(age) {
  if (!age) return '';
  if (age === 1) return 'سنة واحدة';
  if (age === 2) return 'سنتان';
  return `${age} ${age <= 10 ? 'سنوات' : 'سنة'}`;
}

/** «اليوم»، «غداً»، «بعد ٣ أيام»: أقرب إلى الكافل من تاريخٍ مجرّد. */
function inDays(value) {
  if (!value) return '';
  const day = 86_400_000;
  const start = new Date(); start.setHours(0, 0, 0, 0);
  const n = Math.round((new Date(value).setHours(0, 0, 0, 0) - start) / day);
  if (n < 0) return 'متأخر';
  if (n === 0) return 'اليوم';
  if (n === 1) return 'غداً';
  if (n === 2) return 'بعد يومين';
  return `بعد ${n} ${n <= 10 ? 'أيام' : 'يوماً'}`;
}

const CYCLE = { 1: 'شهرياً', 3: 'كل ثلاثة أشهر', 12: 'سنوياً' };

/** بنود الإنفاق في التقرير كما يحفظها الخادم (نسبٌ مئوية). */
const SPENDING = {
  living: { label: 'معيشة وغذاء', color: 'var(--chart-1)' },
  education: { label: 'تعليم', color: 'var(--chart-4)' },
  health: { label: 'صحة', color: 'var(--chart-2)' },
  psychosocial: { label: 'دعم نفسي', color: 'var(--chart-5)' },
  overhead: { label: 'تشغيل', color: 'var(--chart-muted)' },
};

const SECTIONS = { overview: 'نظرة عامة', children: 'أطفالي', reports: 'التقارير', payments: 'المدفوعات', account: 'حسابي' };

/** يملأ كل عنصرٍ يطابق المحدِّد بالنص، ويتجاهله بهدوء إن لم يكن في الصفحة. */
function setText(selector, value) {
  $$(selector).forEach((el) => { el.textContent = value; });
}

/**
 * صورة الطفل: الحقيقية لكافله وحده، وإلا رسمه الثابت. وإن تعذّر تحميلها
 * يحلّ الرسم محلها (initImageFallback) فلا تبقى دائرةٌ فارغة.
 */
function kidImage(orphan, size, cls = '') {
  const fallback = orphan?.illustration_url ?? '';
  const src = orphan?.photo_url || fallback;
  if (!src) return '';
  return `<img class="${cls}" src="${escapeHtml(src)}" data-fallback="${escapeHtml(fallback)}" alt="" width="${size}" height="${size}" loading="lazy">`;
}

function initImageFallback() {
  document.addEventListener('error', (e) => {
    const img = e.target;
    if (img instanceof HTMLImageElement && img.dataset.fallback && !img.src.endsWith(img.dataset.fallback)) {
      img.src = img.dataset.fallback;
    }
  }, true);
}

/* -------------------------------------------------------------- النوافذ */

const modalOf = (id) => {
  const el = document.getElementById(id);
  return el && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(el) : null;
};
const openModal = (id) => modalOf(id)?.show();

/**
 * يغلق النافذة ويعود حين تكتمل، فيصحّ نقل التركيز بعدها لا قبلها.
 *
 * Bootstrap يتجاهل hide() أثناء حركة الفتح، فإجراءٌ سريع (نقرٌ على «أنهِ
 * الكفالة» والنافذة ما زالت تظهر) كان يترك الوعد معلّقاً للأبد. ننتظر اكتمال
 * الفتح أولاً ثم نغلق.
 */
function closeModal(id) {
  const el = document.getElementById(id);
  if (!el?.classList.contains('show')) return Promise.resolve();
  return new Promise((resolve) => {
    const hide = () => {
      el.addEventListener('hidden.bs.modal', () => resolve(), { once: true });
      modalOf(id)?.hide();
    };
    if (el.dataset.shown) hide();
    else el.addEventListener('shown.bs.modal', hide, { once: true });
  });
}

/** أيّ النوافذ اكتمل فتحها: يقرؤه closeModal. */
function trackModals() {
  document.addEventListener('shown.bs.modal', (e) => { e.target.dataset.shown = '1'; });
  document.addEventListener('hide.bs.modal', (e) => { delete e.target.dataset.shown; });
}

/* --------------------------------------------------------------- الجلسة */

/** انتهت الجلسة أثناء التصفح: إلى الدخول ثم العودة إلى هنا. */
function toLogin() {
  clearSignedIn();
  window.location.replace(url(`/login?redirect=${encodeURIComponent(url('/dashboard'))}`));
}

/** خطأ طلبٍ في البوابة: الجلسة المنتهية تُعيد للدخول، وغيرها يُقال. */
function failed(error, fallback) {
  if (isAuthLoss(error)) {
    toLogin();
    return true;
  }
  toast(error?.message ?? fallback, 'error');
  return false;
}

/** حالة خطأ داخل القسم نفسه، بزرّ يعيد المحاولة. */
const errorBlock = (text) => `
<div class="pt-empty" role="alert">
  <i class="fa-solid fa-plug-circle-exclamation" aria-hidden="true"></i>
  <p>${escapeHtml(text)}</p>
  <button class="btn btn-outline-primary btn-sm" type="button" data-portal-retry>أعد المحاولة</button>
</div>`;

let sponsorships = [];
let reports = [];
let overview = null;

export async function initPortal() {
  const root = $('[data-portal]');
  if (!root) return;

  initViews();
  initRail();
  trackModals();
  setText('[data-portal-today]', todayFmt.format(new Date()));

  /**
   * الحارس.
   *
   * إخفاء البوابة عن غير الداخل ليس أمناً، فالخادم يرفض `/portal/*` بلا جلسة
   * على أي حال. هذا لتفادي عرض صفحة تنتهي بخطأ.
   */
  let user;
  try {
    user = await me({ strict: true });
  } catch {
    // الخادم متعثّر لا الجلسة: لا نُخرج الكافل، نقول ما حدث
    attention('error', 'fa-plug-circle-exclamation', 'تعذّر الوصول إلى الخادم الآن.', 'تحقّق من اتصالك ثم أعد المحاولة.',
      '<button class="btn btn-outline-primary btn-sm" type="button" data-portal-retry>أعد المحاولة</button>');
    $('[data-portal-retry]')?.addEventListener('click', () => window.location.reload());
    root.removeAttribute('data-portal-loading');
    return;
  }

  if (!user) {
    toLogin();
    return;
  }

  // جلسة قائمة لحساب غير كافل (موظف مثلاً): لا مكان له هنا
  if (!isSponsor(user)) {
    await logout().catch(() => {});
    clearSignedIn();
    window.location.replace(url('/login'));
    return;
  }

  markSignedIn();
  showUser(user);
  fillProfile(user);

  $$('[data-portal-logout], [data-logout]').forEach((button) => button.addEventListener('click', async (e) => {
    e.preventDefault();
    busy(button, true, 'جارٍ الخروج…');
    await logout().catch(() => {});
    clearSignedIn();
    window.location.href = '/';
  }));

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-portal-retry]')) loadOverview();
  });

  initImageFallback();
  initStatusTabs();
  initReports();
  initLedger();
  initMessages();
  initStatusActions();
  initCancel();
  initBank();
  initCard();
  initAccount();

  // الدفعات بعد الكفالات: صورة كل طفل في سطر دفعته تأتي من كفالته
  await loadOverview();
  initPayments();
  root.removeAttribute('data-portal-loading');
}

/** الاسم في كل مكانٍ يظهر فيه، والتحية بحسب ساعة اليوم. */
function showUser(user) {
  const first = (user.name ?? '').split(' ')[0];
  const hour = new Date().getHours();
  setText('[data-portal-name]', user.name ?? '');
  setText('[data-portal-greeting]', `${hour >= 4 && hour < 12 ? 'صباح الخير' : 'مساء الخير'}، ${first}`);
  setText('[data-portal-avatar]', (user.name ?? '·').charAt(0));
  setText('[data-portal-email]', user.email ?? '');
}

async function loadOverview() {
  try {
    const { data } = await portalOverview();
    overview = data;
    sponsorships = data.sponsorships ?? [];
    reports = data.latest_reports ?? [];
    renderStats(data);
    renderKids();
    renderSponsorships();
    renderPast(data.past_sponsorships ?? []);
    renderReports();
    renderLatest();
    renderAttention();
  } catch (error) {
    if (isAuthLoss(error)) {
      toLogin();
      return;
    }

    // لا أرقام وهمية ولا «لا كفالات» كاذبة: القسم يقول إنه لم يُحمَّل
    $$('[data-stat]').forEach((el) => { if (el.tagName === 'DD') el.textContent = '·'; });
    attention('error', 'fa-plug-circle-exclamation', 'تعذّر تحميل بوابتك الآن.', error?.message ?? '',
      '<button class="btn btn-outline-primary btn-sm" type="button" data-portal-retry>أعد المحاولة</button>');
    const list = $('[data-portal-sponsorships]');
    if (list) list.innerHTML = errorBlock(error?.message ?? 'تعذّر تحميل كفالاتك.');
    const kids = $('[data-portal-kids]');
    if (kids) kids.innerHTML = '<li class="pt-empty"><p>لم يُحمَّل.</p></li>';
    const host = $('[data-portal-reports]');
    if (host) host.innerHTML = '<li class="pt-empty"><p>لم تُحمَّل التقارير.</p></li>';
    const latest = $('[data-portal-latest]');
    if (latest) latest.innerHTML = '<p class="pt-muted mb-0">لم يُحمَّل.</p>';
  }
}

/* -------------------------------------------------------------- الأقسام */

/**
 * قسمٌ واحد ظاهر يتبع الرابط. التركيز ينتقل إلى عنوانه حين يبدّله الزائر
 * (لا عند فتح الصفحة)، فيسمع قارئ الشاشة أين صار.
 */
function initViews() {
  const views = $$('[data-pt-view]');
  if (!views.length) return;

  const show = (focus) => {
    const id = location.hash.slice(1);
    const view = views.find((v) => v.id === id) ?? views[0];
    views.forEach((v) => { v.hidden = v !== view; });

    $$('.pt-nav__link, .pt-tabbar__link').forEach((link) => {
      if (link.hash === `#${view.id}`) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
    // الاسم من الإعدادات (head.blade يكتبه في application-name)، لا مكتوباً هنا
    document.title = `${SECTIONS[view.id] ?? 'بوابتي'} | ${document.querySelector('meta[name="application-name"]')?.content ?? ''}`;
    setText('[data-pt-title]', SECTIONS[view.id] ?? '');

    if (focus) {
      window.scrollTo({ top: 0 });
      view.querySelector('h1')?.focus({ preventScroll: true });
    }
  };

  // القسم في الرابط يمنع المتصفح من القفز إلى موضعه: نعرضه من أعلاه
  if (location.hash) history.scrollRestoration = 'manual';
  show(false);
  window.addEventListener('hashchange', () => show(true));

  // على الجوال الشريط درج: يُغلق حين يُختار منه قسم
  document.addEventListener('click', (e) => {
    if (!e.target.closest('#ptSide a[href]')) return;
    const side = document.getElementById('ptSide');
    if (side?.classList.contains('show')) window.bootstrap?.Offcanvas.getInstance(side)?.hide();
  });
}

/**
 * طيّ الشريط إلى عمود أيقونات، كما في لوحة الإدارة. الاختيار يُحفظ في هذا
 * المتصفح، وفي الوضع المطويّ يظهر اسم كل رابط عند المرور عليه أو التركيز.
 */
const RAIL_KEY = 'kafala-portal-rail';

function initRail() {
  const root = $('.pt');
  const toggle = $('[data-rail-toggle]');
  if (!root || !toggle) return;

  const apply = (on) => {
    root.classList.toggle('is-rail', on);
    toggle.setAttribute('aria-expanded', String(!on));
    toggle.setAttribute('aria-label', on ? 'توسيع القائمة' : 'طيّ القائمة');
    toggle.title = on ? 'توسيع القائمة' : 'طيّ القائمة';
  };

  try { apply(localStorage.getItem(RAIL_KEY) === '1'); } catch { /* تخزين محجوب */ }

  toggle.addEventListener('click', () => {
    const on = !root.classList.contains('is-rail');
    apply(on);
    try { localStorage.setItem(RAIL_KEY, on ? '1' : '0'); } catch { /* تخزين محجوب */ }
  });

  const tip = $('[data-pt-tip]');
  if (!tip) return;
  const show = (e) => {
    const link = e.target.closest?.('[data-tip]');
    if (!link || !root.classList.contains('is-rail') || window.innerWidth < 992) return;
    const r = link.getBoundingClientRect();
    tip.textContent = link.dataset.tip;
    tip.style.top = `${r.top + r.height / 2}px`;
    tip.style.right = `${window.innerWidth - r.left + 10}px`;
    tip.classList.add('is-shown');
  };
  const hide = () => tip.classList.remove('is-shown');
  const side = $('#ptSide');
  side?.addEventListener('mouseover', show);
  side?.addEventListener('focusin', show);
  side?.addEventListener('mouseout', hide);
  side?.addEventListener('focusout', hide);
  $('.pt-nav')?.addEventListener('scroll', hide);
}

/* ------------------------------------------------------- ما يحتاج انتباهك */

function attention(tone, icon, title, text, action = '') {
  const box = $('[data-portal-attention]');
  if (!box) return;
  box.dataset.tone = tone;
  const i = $('[data-attention-icon]', box);
  if (i) i.className = `fa-solid ${icon}`;
  setText('[data-attention-title]', title);
  setText('[data-portal-subtitle]', text);
  const slot = $('[data-attention-action]', box);
  if (slot) slot.innerHTML = action;
}

/** الأهم أولاً: حجزٌ يسقط إن لم يصل تحويله، ثم تقريرٌ لم يُقرأ، ثم الطمأنينة. */
function renderAttention() {
  const s = overview?.stats ?? {};
  const next = overview?.next_payment;
  const fresh = reports.filter((r) => r.is_new);
  const pending = sponsorships.filter((x) => x.status === 'pending');
  const failing = sponsorships.filter((x) => x.payment_issue);

  // قسطٌ لم يُخصم أهمّ من كل شيء: كل يوم تأخيرٍ يقرّب الطفل من صندوق الاستمرارية
  if (failing.length) {
    const f = failing[0];
    attention('error', 'fa-credit-card', failing.length === 1
      ? `تعذّر خصم قسط ${f.orphan?.name ?? 'الطفل'}`
      : `تعذّر خصم ${formatNumber(failing.length)} أقساط`,
    `${issueText(f)} حدّث البطاقة فيصل القسط فوراً.`,
    `<button class="btn btn-cta btn-sm" type="button" data-card-open="${f.id}">حدّث البطاقة</button>`);
    return;
  }

  if (s.pending_count > 0 && pending.length) {
    const p = pending[0];
    attention('wait', 'fa-building-columns', 'حوّل قسطك الأول', s.pending_count === 1
      ? `كفالة ${p.orphan?.name ?? 'الطفل'} محجوزة بانتظار تحويلك حتى ${fmtShort(p.hold_expires_at)}.`
      : `لديك ${formatNumber(s.pending_count)} كفالات محجوزة بانتظار تحويلك.`,
    `<button class="btn btn-cta btn-sm" type="button" data-bank-open="${p.id}">بيانات التحويل</button>`);
    return;
  }

  if (fresh.length) {
    attention('report', 'fa-file-lines', fresh.length === 1 ? 'تقريرٌ جديد لم تقرأه' : `${formatNumber(fresh.length)} تقارير جديدة`, fresh.length === 1
      ? `زيارة ${fresh[0].orphan?.name ?? ''} الميدانية عن ${fresh[0].period_label}.`
      : 'زياراتٌ ميدانية لأطفالك لم تقرأ تقاريرها بعد.',
    `<button class="btn btn-primary btn-sm" type="button" data-report-open="${fresh[0].id}">اقرأ التقرير</button>`);
    return;
  }

  attention('calm', 'fa-check', 'لا شيء ينتظرك اليوم', next
    ? `قرأت كل التقارير، وقسطك القادم ${fmtShort(next.due_at)} يُخصم تلقائياً.`
    : 'قرأت كل التقارير، وستظهر هنا أي مهمة جديدة.');
}

/* ------------------------------------------------------------- المؤشرات */

function renderStats(data) {
  const s = data.stats ?? {};

  setText('[data-stat="orphans"]', formatNumber(s.orphans_sponsored ?? 0));
  setText('[data-stat="given"]', formatMoney(s.total_given ?? 0));
  setText('[data-stat="commitment"]', formatMoney(s.monthly_commitment ?? 0));
  setText('[data-stat="payments-count"]', formatNumber(s.payments_count ?? 0));

  const next = data.next_payment;
  setText('[data-stat="next"]', next ? fmtShort(next.due_at) : 'لا أقساط');
  setText('[data-stat="payments-next"]', next ? fmtShort(next.due_at) : 'لا أقساط');
  setText('[data-stat="next-hint"]', next
    ? `${formatMoney(next.amount, next.currency)}${next.interval_months > 1 ? ` ${CYCLE[next.interval_months] ?? ''}` : ''} · ${inDays(next.due_at)}`
    : '');

  setText('[data-stat="orphans-hint"]', s.pending_count > 0 ? `و${formatNumber(s.pending_count)} بانتظار التحويل` : 'كفالات نشطة');
  setText('[data-stat="given-hint"]', `في ${formatNumber(s.payments_count ?? 0)} دفعة`);

  const since = data.sponsor?.since;
  setText('[data-portal-since]', since ? `كافلٌ منذ ${new Date(since).getFullYear()}` : '');
  const chip = $('[data-portal-since-chip]');
  if (chip && since) { chip.hidden = false; chip.textContent = `كافلٌ منذ ${fmtDate(since)}`; }
  $$('[data-pending-count]').forEach((el) => { el.hidden = !(s.pending_count > 0); el.textContent = formatNumber(s.pending_count ?? 0); });
  setText('[data-portal-code]', data.sponsor?.code ?? '');

  syncReportBadges();
}

/** عدد التقارير غير المقروءة في الشريط الجانبي والجرس وشريط التبويب. */
function syncReportBadges() {
  const fresh = reports.filter((r) => r.is_new).length;
  $$('[data-reports-count]').forEach((el) => {
    el.hidden = fresh === 0;
    if (el.classList.contains('pt-count')) el.textContent = formatNumber(fresh);
  });
}

/* ------------------------------------------------------------- الكفالات */

const STATUS_CHIP = {
  active: '<span class="pt-chip pt-chip--ok">نشطة</span>',
  paused: '<span class="pt-chip">موقوفة مؤقتاً</span>',
  pending: '<span class="pt-chip pt-chip--wait">بانتظار التحويل</span>',
};

/** شارة الكفالة: المتعثّرة نشطةٌ لكنها تحتاج الكافل، فتقول ذلك. */
const chipFor = (s) => (s.payment_issue ? '<span class="pt-chip pt-chip--bad">القسط متعثّر</span>' : STATUS_CHIP[s.status] ?? '');

/** ما جرى للقسط ومتى المحاولة التالية، بلغة الكافل. */
function issueText(s) {
  const issue = s.payment_issue;
  const next = issue.next_retry_at
    ? `نعيد المحاولة ${fmtShort(issue.next_retry_at)}.`
    : 'توقّفت المحاولات حتى تحدّث البطاقة.';
  return `لم يُخصم قسط ${fmtShort(s.next_due_at ?? issue.since)}${issue.card_last4 ? ` من البطاقة المنتهية بـ⁦${issue.card_last4}⁩` : ''}. ${next}`;
}

/** ما تعنيه الحالة للكافل، حين تحتاج شرحاً. */
function statusLine(s) {
  if (s.payment_issue) {
    return `<p class="pt-kid__state pt-kid__state--bad">
      <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
      <span><b>${escapeHtml(issueText(s))}</b>${s.payment_issue.reason ? ` السبب: ${escapeHtml(s.payment_issue.reason)}` : ''}</span>
    </p>`;
  }
  if (s.status === 'pending') {
    return `<p class="pt-kid__state pt-kid__state--wait">
      <i class="fa-regular fa-clock" aria-hidden="true"></i>
      <span><b>بانتظار تحويلك.</b> نحجز الطفل لك حتى ${fmtShort(s.hold_expires_at)}، وتبدأ الكفالة حين يصل التحويل.</span>
    </p>`;
  }
  if (s.status === 'paused') {
    return `<p class="pt-kid__state">
      <i class="fa-solid fa-pause" aria-hidden="true"></i>
      <span><b>موقوفة مؤقتاً${s.paused_at ? ` منذ ${fmtShort(s.paused_at)}` : ''}.</b> لا خصم الآن، والطفل محجوز لك.</span>
    </p>`;
  }
  return '';
}

/** أطفالك في الرئيسية: صفٌّ لكلٍّ بحالته وقسطه، والتفاصيل في «أطفالي». */
function renderKids() {
  const host = $('[data-portal-kids]');
  if (!host) return;
  if (!sponsorships.length) {
    host.innerHTML = `<li class="pt-empty">
      <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
      <p>لا كفالات قائمة الآن. طفلٌ واحد يكفي لتبدأ.</p>
      <a class="btn btn-cta btn-sm" href="${url('/orphans')}">اختر طفلاً تقف معه</a>
    </li>`;
    return;
  }
  host.innerHTML = sponsorships.map((s) => {
    const o = s.orphan ?? {};
    const funded = Math.min(100, Math.max(0, Number(o.funded_percent ?? 0)));
    const interval = Number(s.interval_months ?? 1);
    return `
<li><a class="pt-row" href="#children" data-pt-link>
  ${kidImage(o, 44)}
  <span class="pt-row__who"><b>${escapeHtml(o.name ?? '')}${o.age ? `، ${ageLabel(o.age)}` : ''}</b><span>${escapeHtml(s.program?.name ?? '')}</span></span>
  ${s.status === 'pending' ? '' : `<span class="pt-row__fund"><span>مغطّاة <b class="num">${funded}٪</b></span><span class="pt-bar"><span style="--v:${funded}%"></span></span></span>`}
  <span class="pt-row__end">
    <span class="num">${formatMoney(interval > 1 ? s.cycle_amount : s.monthly_amount, s.currency)}</span>
    ${chipFor(s)}
  </span>
</a></li>`;
  }).join('');
}

const STATUS_TABS = [['all', 'الكل'], ['active', 'نشطة'], ['paused', 'موقوفة مؤقتاً'], ['pending', 'بانتظار التحويل']];
let statusFilter = 'all';

/** تبويبات الحالة في «أطفالي»: لا تظهر إلا حين تختلف الحالات فعلاً. */
function renderStatusTabs() {
  const tabs = $('[data-status-tabs]');
  if (!tabs) return;
  const count = (k) => sponsorships.filter((x) => k === 'all' || x.status === k).length;
  const kinds = new Set(sponsorships.map((x) => x.status));
  if (statusFilter !== 'all' && !kinds.has(statusFilter)) statusFilter = 'all';
  tabs.hidden = kinds.size < 2;
  tabs.innerHTML = STATUS_TABS.filter(([k]) => k === 'all' || kinds.has(k)).map(([k, label]) =>
    `<button type="button" data-status="${k}" aria-pressed="${statusFilter === k}">${label} <span class="num">${formatNumber(count(k))}</span></button>`).join('');

  const active = sponsorships.filter((x) => x.status === 'active').length;
  setText('[data-children-sub]', sponsorships.length
    ? `${formatNumber(sponsorships.length)} ${sponsorships.length === 1 ? 'كفالة' : 'كفالات'}${active && active !== sponsorships.length ? `، ${formatNumber(active)} منها نشطة` : ''}. راسل الطفل، أو أوقف كفالته مؤقتاً، من بطاقته.`
    : '');
}

function initStatusTabs() {
  $('[data-status-tabs]')?.addEventListener('click', (e) => {
    const button = e.target.closest('[data-status]');
    if (!button) return;
    statusFilter = button.dataset.status;
    renderSponsorships();
  });
}

function renderSponsorships() {
  const list = $('[data-portal-sponsorships]');
  if (!list) return;
  renderStatusTabs();
  const items = sponsorships.filter((x) => statusFilter === 'all' || x.status === statusFilter);

  if (!sponsorships.length) {
    list.innerHTML = `
<div class="pt-empty">
  <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
  <p>لا كفالات قائمة الآن. طفلٌ واحد يكفي لتبدأ.</p>
  <a class="btn btn-cta" href="${url('/orphans')}">اختر طفلاً تقف معه</a>
</div>`;
    return;
  }

  list.innerHTML = items.map((s) => {
    const orphan = s.orphan ?? {};
    const name = escapeHtml(orphan.name ?? 'غير معروف');
    const funded = Math.min(100, Math.max(0, Number(orphan.funded_percent ?? 0)));
    const interval = Number(s.interval_months ?? 1);
    const pending = s.status === 'pending';
    const paused = s.status === 'paused';
    const profile = url(`/orphan?id=${encodeURIComponent(orphan.reference_code ?? '')}`);
    const latest = reports.find((r) => r.orphan?.id === orphan.id);

    // ما يظهر من الإجراءات يتبع الحالة: الحجز يُستكمل أو يُلغى، والموقوفة تُستأنف
    const actions = pending
      ? `<button class="btn btn-cta btn-sm" type="button" data-bank-open="${s.id}">
          <i class="fa-solid fa-building-columns" aria-hidden="true"></i> بيانات التحويل
        </button>
        <a class="btn btn-soft btn-sm" href="${profile}">ملفه</a>
        <button class="pt-kid__stop" type="button" data-cancel-open="${s.id}">ألغِ الحجز</button>`
      : `${s.payment_issue ? `<button class="btn btn-cta btn-sm" type="button" data-card-open="${s.id}">
          <i class="fa-solid fa-credit-card" aria-hidden="true"></i> حدّث البطاقة
        </button>` : ''}${paused ? `<button class="btn btn-primary btn-sm" type="button" data-resume="${s.id}">
          <i class="fa-solid fa-play" aria-hidden="true"></i> استأنف
        </button>` : ''}
        <button class="btn ${paused || s.payment_issue ? 'btn-soft' : 'btn-primary'} btn-sm" type="button" data-message-open="${s.id}">
          <i class="fa-regular fa-envelope" aria-hidden="true"></i> رسالة
        </button>
        <button class="btn btn-soft btn-sm" type="button" data-ledger-open="${s.id}">
          <i class="fa-solid fa-route" aria-hidden="true"></i> أين ذهب مالك
        </button>
        ${latest ? `<button class="btn btn-soft btn-sm" type="button" data-report-open="${latest.id}">
          <i class="fa-regular fa-file-lines" aria-hidden="true"></i> آخر تقرير
        </button>` : `<a class="btn btn-soft btn-sm" href="${profile}">ملفه</a>`}
        <button class="pt-kid__stop" type="button" data-cancel-open="${s.id}">${paused ? 'إنهاء' : 'إيقاف أو إنهاء'}</button>`;

    // الصنف sponsor-row يبقى: يعتمد عليه اختبار e2e للبوابة
    return `
<article class="pt-kid sponsor-row" data-sponsorship="${s.id}" data-status="${escapeHtml(s.status)}">
  <div class="pt-kid__head">
    ${kidImage(orphan, 60, 'pt-kid__photo')}
    <div class="pt-kid__who">
      <h3>${name}${orphan.age ? `<span>، ${ageLabel(orphan.age)}</span>` : ''}</h3>
      <p>${escapeHtml(s.program?.name ?? '')}${s.is_zakat ? '<span class="pt-tag">من الزكاة</span>' : ''}</p>
    </div>
    ${chipFor(s)}
  </div>

  ${statusLine(s)}

  <dl class="pt-kid__facts">
    <div><dt>${interval > 1 ? CYCLE[interval] : 'شهرياً'}</dt>
      <dd class="num">${formatMoney(interval > 1 ? s.cycle_amount : s.monthly_amount, s.currency)}</dd></div>
    <div><dt>${pending ? 'أول دفعة' : s.payment_issue ? 'مستحق منذ' : 'القسط القادم'}</dt><dd>${pending ? 'عند التحويل' : paused ? 'بعد الاستئناف' : fmtShort(s.next_due_at)}</dd></div>
    <div><dt>قدّمتَ له</dt><dd class="num">${formatMoney(s.total_paid ?? 0, s.currency)}</dd></div>
  </dl>

  ${pending ? '' : `<div class="pt-kid__fund">
    <span>كفالته مغطّاة بنسبة <b class="num">${funded}٪</b></span>
    <span class="pt-bar" role="img" aria-label="مغطّاة ${funded}٪"><span style="--v:${funded}%"></span></span>
  </div>`}

  <div class="pt-kid__actions">${actions}</div>
</article>`;
  }).join('');
}

/** الكفالات المنتهية: سطر لكلٍّ، بما قدّمه ومتى انتهت. */
function renderPast(items) {
  const host = $('[data-portal-past]');
  if (!host) return;

  host.hidden = !items.length;
  if (!items.length) return;

  setText('[data-portal-past-count]', formatNumber(items.length));
  const list = $('[data-portal-past-list]');
  list.innerHTML = items.map((s) => `
<li>
  <strong>${escapeHtml(s.orphan?.name ?? 'غير معروف')}</strong>
  <span>${escapeHtml(s.status_label ?? '')}${s.ended_at ? ` في ${fmtDate(s.ended_at)}` : ''}</span>
  <span>قدّمتَ له <b class="num">${formatMoney(s.total_paid ?? 0, s.currency)}</b></span>
</li>`).join('');
}

/* -------------------------------------------------------------- التقارير */

const NEW_CHIP = '<span class="pt-chip pt-chip--new">جديد</span>';

/** شريط «كيف صُرفت الكفالة» ومفتاحه، من نسب الخادم. */
/** legend=false في البطاقات المتكررة: المفتاح نفسه تحت كل شريط ضجيجٌ لا معلومة. */
function spendBar(report, legend = true) {
  const parts = Object.entries(report.spending_breakdown ?? {})
    .filter(([key, v]) => SPENDING[key] && Number(v) > 0);
  if (!parts.length) return '';
  return `
<div class="pt-split" role="img" aria-label="${parts.map(([k, v]) => `${SPENDING[k].label} ${v}٪`).join('، ')}">
  ${parts.map(([k, v]) => `<span style="--w:${Number(v)}%; --c:${SPENDING[k].color}"></span>`).join('')}
</div>
${legend ? `<ul class="pt-legend" aria-hidden="true">
  ${parts.map(([k, v]) => `<li><i style="--c:${SPENDING[k].color}"></i>${SPENDING[k].label} <b class="num">${Number(v)}٪</b></li>`).join('')}
</ul>` : ''}`;
}

/** أحدث تقرير في الرئيسية: من زار الطفل ومتى، وخلاصته، وكيف صُرفت كفالته. */
function renderLatest() {
  const host = $('[data-portal-latest]');
  if (!host) return;
  const r = reports.find((x) => x.is_new) ?? reports[0];

  if (!r) {
    host.innerHTML = '<p class="pt-muted mb-0">يصلك أول تقرير بعد ثلاثة أشهر من بدء الكفالة، بعد زيارةٍ ميدانية للطفل.</p>';
    return;
  }

  host.innerHTML = `
<div class="pt-latest" data-report="${r.id}">
  <div class="pt-who">
    ${kidImage(r.orphan, 44)}
    <div><b>${escapeHtml(r.orphan?.name ?? '')}</b><span>${escapeHtml(r.period_label ?? '')} · زيارة ${fmtShort(r.visit_date)}</span></div>
    ${r.is_new ? NEW_CHIP : ''}
  </div>
  ${r.summary ? `<p class="pt-latest__summary">${escapeHtml(r.summary)}</p>` : ''}
  ${spendBar(r)}
  <button class="btn btn-soft btn-sm w-100" type="button" data-report-open="${r.id}">اقرأ التقرير كاملاً</button>
</div>`;
}

let reportFilter = 'all';

function renderReports() {
  const list = $('[data-portal-reports]');
  if (!list) return;

  if (!reports.length) {
    list.innerHTML = `<li class="pt-empty">
      <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
      <p>لا تقارير منشورة بعد. يصلك أول تقرير بعد ثلاثة أشهر من بدء الكفالة.</p>
    </li>`;
    return;
  }

  const fresh = reports.filter((r) => r.is_new).length;
  setText('[data-reports-sub]', `${formatNumber(reports.length)} ${reports.length === 1 ? 'تقرير' : 'تقارير'}${fresh ? `، ${formatNumber(fresh)} منها ${fresh === 1 ? 'جديد' : 'جديدة'}` : ''}. زيارةٌ ميدانية لكل طفل كل ثلاثة أشهر.`);

  // تصفية بالطفل حين يكفل أكثر من واحد
  const kids = [...new Map(reports.map((r) => [r.orphan?.id, r.orphan])).values()].filter(Boolean);
  const filter = $('[data-report-filter]');
  if (filter) {
    filter.hidden = kids.length < 2;
    if (!kids.some((k) => String(k.id) === reportFilter)) reportFilter = 'all';
    filter.innerHTML = kids.length < 2 ? '' : [
      `<button type="button" data-filter="all" aria-pressed="${reportFilter === 'all'}">الكل <span class="num">${formatNumber(reports.length)}</span></button>`,
      ...kids.map((k) => `<button type="button" data-filter="${k.id}" aria-pressed="${reportFilter === String(k.id)}">${kidImage(k, 26)}${escapeHtml(k.name ?? '')} <span class="num">${formatNumber(reports.filter((r) => r.orphan?.id === k.id).length)}</span></button>`),
    ].join('');
  }

  list.innerHTML = reports.map((r) => `
<li class="pt-report${r.is_new ? ' is-new' : ''}" data-report="${r.id}" data-orphan="${r.orphan?.id ?? ''}"${reportFilter !== 'all' && String(r.orphan?.id) !== reportFilter ? ' hidden' : ''}>
  <div class="pt-who">
    ${kidImage(r.orphan, 44)}
    <div><b>${escapeHtml(r.orphan?.name ?? '')}</b><span>زيارة ${fmtDate(r.visit_date)}</span></div>
    ${r.is_new ? NEW_CHIP : ''}
  </div>
  <h3>${escapeHtml(r.period_label ?? r.title ?? '')}</h3>
  ${r.summary ? `<p class="pt-report__summary">${escapeHtml(r.summary)}</p>` : ''}
  ${spendBar(r, false)}
  <button class="btn ${r.is_new ? 'btn-primary' : 'btn-soft'} btn-sm" type="button" data-report-open="${r.id}">
    اقرأ التقرير <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
  </button>
</li>`).join('');
}

function initReports() {
  $('[data-report-filter]')?.addEventListener('click', (e) => {
    const button = e.target.closest('[data-filter]');
    if (!button) return;
    reportFilter = button.dataset.filter;
    $$('[data-filter]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
    $$('[data-portal-reports] [data-report]').forEach((li) => {
      li.hidden = reportFilter !== 'all' && li.dataset.orphan !== reportFilter;
    });
  });

  /**
   * فتح التقرير يسجّل القراءة على الخادم، فتُطفأ علامة «جديد» عند الجميع،
   * لا في هذا المتصفح وحده. والزرّ قد يكون في أي قسم (البطاقة، والرئيسية،
   * والتقارير، وما يحتاج انتباهك)، فالالتقاط على مستوى المستند.
   */
  document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-report-open]');
    if (!button) return;
    const id = Number(button.dataset.reportOpen);
    button.disabled = true;

    try {
      const { data } = await markReportSeen(id);
      openReportModal(data);
      const cached = reports.find((r) => r.id === id);
      if (cached?.is_new) {
        cached.is_new = false;
        renderReports();
        renderLatest();
        renderAttention();
        syncReportBadges();
      }
    } catch (error) {
      failed(error, 'تعذّر فتح التقرير.');
    } finally {
      if (button.isConnected) button.disabled = false;
    }
  });
}

/** التقرير كما يُقرأ: من زاره ومتى، وخلاصته، وملاحظاته، وكيف صُرفت الكفالة. */
function openReportModal(report) {
  const host = $('[data-report-content]');
  if (!host) return;

  const notes = [
    ['الدراسة', 'fa-book-open', report.academic_note],
    ['الصحة', 'fa-heart-pulse', report.health_note],
    ['الحال النفسية', 'fa-face-smile', report.psychosocial_note],
  ].filter(([, , v]) => v);

  const photo = report.media?.find((m) => m.type === 'image') ?? null;
  const breakdown = spendBar(report);

  host.innerHTML = `
<header class="pt-rep__head">
  ${kidImage(report.orphan, 56)}
  <div>
    <p>${escapeHtml(report.period_label ?? '')} · زيارة ميدانية ${fmtDate(report.visit_date)}${report.supervisor ? ` مع ${escapeHtml(report.supervisor)}` : ''}</p>
    <h2 id="reportModalTitle">${escapeHtml(report.orphan?.name ? `تقرير ${report.orphan.name}` : report.title)}</h2>
  </div>
</header>

${report.summary ? `<p class="pt-rep__summary">${escapeHtml(report.summary)}</p>` : ''}

${photo ? `<figure class="pt-rep__photo"><img src="${escapeHtml(photo.url)}" alt="${escapeHtml(photo.caption ?? '')}" loading="lazy"></figure>` : ''}

${notes.length ? `<dl class="pt-rep__notes">
  ${notes.map(([label, icon, value]) => `<div><dt><i class="fa-solid ${icon}" aria-hidden="true"></i>${label}</dt><dd>${escapeHtml(value)}</dd></div>`).join('')}
</dl>` : ''}

${breakdown ? `<div class="pt-rep__spend"><h3>كيف صُرفت الكفالة</h3>${breakdown}</div>` : ''}

<p class="pt-rep__private">
  <i class="fa-solid fa-lock" aria-hidden="true"></i>
  <span>هذا التقرير خاصٌّ بك ككافل. نرجو ألا تنشر صور الطفل أو تفاصيله خارج المنصة.</span>
</p>`;

  openModal('reportModal');
}

/* ------------------------------------------------------ أين ذهب مالك */

/**
 * سجلّ الطفل المالي (ChildLedgerService): ما وصل له، وما حُوِّل لجمعيته بعد
 * التشغيل، وما صُرف عليه بنداً بنداً، شهراً بشهر. كل رقمٍ من صفوف المال
 * على الخادم؛ هنا العرض وحده.
 */
function initLedger() {
  document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-ledger-open]');
    if (!button) return;
    button.disabled = true;
    try {
      const { data } = await portalLedger(Number(button.dataset.ledgerOpen));
      openLedgerModal(data);
    } catch (error) {
      failed(error, 'تعذّر فتح السجل.');
    } finally {
      if (button.isConnected) button.disabled = false;
    }
  });
}

/** المبلغ معزولاً باتجاهه: «$20.18» لا «20.18$» وسط جملة عربية */
const money = (v) => `<bdi class="num" dir="ltr">${formatMoney(Number(v) || 0)}</bdi>`;

function openLedgerModal(d) {
  const host = $('[data-ledger-content]');
  if (!host) return;
  const t = d.totals;
  const name = escapeHtml(d.child?.name ?? '');
  const assoc = d.association
    ? `<a href="${url(`/partners/${encodeURIComponent(d.association.slug)}`)}">${escapeHtml(d.association.name)}</a>`
    : 'جمعيته';
  const months = (d.months ?? []).filter((m) => m.received || m.transfer || m.spent_total);

  // الشهر الجاري يُحوَّل أوله التالي، والماضي ينتظر الإرسال؛ وما قبلهما بلا تحويلٍ مسجّل لا وعد له
  const [current, previous] = [0, 1].map((back) => {
    const dt = new Date();
    dt.setDate(1);
    dt.setMonth(dt.getMonth() - back);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}`;
  });
  const transferChip = (m) => {
    if (m.transfer?.status === 'confirmed') return `<span class="pt-chip pt-chip--ok">وصل للجمعية ${money(m.transfer.amount)}</span>`;
    if (m.transfer?.status === 'sent') return `<span class="pt-chip pt-chip--wait">أُرسل للجمعية ${money(m.transfer.amount)}</span>`;
    if (!m.received) return '<span></span>';
    if (m.period === current) return '<span class="pt-chip">يُحوَّل أول الشهر التالي</span>';
    if (m.period === previous) return '<span class="pt-chip">بانتظار الإرسال</span>';
    return '<span></span>';
  };

  const cats = d.categories ?? [];
  const spentAll = cats.reduce((sum, c) => sum + c.amount, 0) || 1;

  host.innerHTML = `
<header class="pt-led__head">
  <p>منذ بدأت الكفالة · المبالغ بالدولار</p>
  <h2 id="ledgerModalTitle">أين ذهب مالك لـ${name}</h2>
</header>

<ol class="pt-flow" role="list">
  <li>
    <span class="pt-flow__icon" aria-hidden="true"><i class="fa-solid fa-hand-holding-heart"></i></span>
    <span class="pt-flow__label">وصل لـ${name}</span>
    <strong>${money(t.received)}</strong>
    <small>${t.my_share_percent !== null ? `منك ${money(t.mine)}، أي <bdi class="num">${t.my_share_percent}٪</bdi> مما وصل` : 'لم تصل دفعات بعد'}</small>
  </li>
  <li>
    <span class="pt-flow__icon" aria-hidden="true"><i class="fa-solid fa-building-columns"></i></span>
    <span class="pt-flow__label">حُوِّل إلى ${assoc}</span>
    <strong>${money(t.transferred)}</strong>
    <small>بعد التشغيل ${money(t.fee)}، وهو <bdi class="num">${formatNumber(d.fee_percent)}٪</bdi> من كل دفعة</small>
  </li>
  <li>
    <span class="pt-flow__icon" aria-hidden="true"><i class="fa-solid fa-basket-shopping"></i></span>
    <span class="pt-flow__label">صُرف على ${name}</span>
    <strong>${money(t.spent)}</strong>
    <small>في <bdi class="num">${formatNumber(t.spend_items)}</bdi> بنداً${t.receipts ? `، منها <bdi class="num">${formatNumber(t.receipts)}</bdi> بإيصالٍ موثّق` : ''}</small>
  </li>
</ol>

${t.transferred > 0 && t.balance > 0 ? `<p class="pt-led__balance"><i class="fa-solid fa-piggy-bank" aria-hidden="true"></i> عند الجمعية لـ${name} <b>${money(t.balance)}</b> لم تُصرف بعد، وتُصرف عليه في الأشهر القادمة.</p>` : ''}
${t.transferred === 0 && t.received > 0 ? '<p class="pt-led__balance"><i class="fa-regular fa-clock" aria-hidden="true"></i> يُحوَّل ما يصل كل شهر لجمعية الطفل في أول الشهر التالي، ويظهر هنا حين يُرسل.</p>' : ''}

${cats.length ? `<section class="pt-rep__spend">
  <h3>على ماذا صُرف</h3>
  <div class="pt-split" role="img" aria-label="${cats.map((c) => `${escapeHtml(c.label)} ${formatMoney(c.amount)}`).join('، ')}">
    ${cats.map((c) => `<span style="--w:${(c.amount * 100 / spentAll).toFixed(1)}%; --c:${escapeHtml(c.color ?? 'var(--color-brand)')}"></span>`).join('')}
  </div>
  <ul class="pt-legend">
    ${cats.map((c) => `<li><i style="--c:${escapeHtml(c.color ?? 'var(--color-brand)')}"></i>${escapeHtml(c.label)} <b>${money(c.amount)}</b></li>`).join('')}
  </ul>
</section>` : ''}

${months.length ? `<section class="pt-led__months">
  <h3>شهراً بشهر</h3>
  <ol role="list">
    ${months.map((m) => `<li>
      <strong>${escapeHtml(m.label)}</strong>
      <span>وصل <b>${money(m.received)}</b>${m.mine && m.mine !== m.received ? ` <small>منك ${money(m.mine)}</small>` : ''}</span>
      ${transferChip(m)}
      <span>صُرف <b>${money(m.spent_total)}</b></span>
    </li>`).join('')}
  </ol>
</section>` : '<p class="pt-muted">لا حركة بعد. يظهر هنا كل شهر ما وصل وما حُوِّل وما صُرف.</p>'}

<p class="pt-rep__private">
  <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
  <span>كل رقمٍ هنا محسوبٌ من الدفعات والتحويلات والصرف المعتمَد نفسها، لا مكتوبٌ باليد. وما دفعه كفلاء آخرون يظهر مجموعاً لا بأسمائهم.</span>
</p>`;

  openModal('ledgerModal');
}

/* --------------------------------------------------------------- الرسائل */

let messagesFor = null;

/** «وافق المشرف» لا تعني «وصلت»: التسليم خطوة لاحقة يسجّلها المشرف. */
const MESSAGE_STATUS = {
  pending_review: 'بانتظار مراجعة المشرف',
  approved: 'وافق عليها المشرف، وتصل في الزيارة القادمة',
  delivered: 'وصلت للطفل',
  rejected: 'لم تُسلَّم',
};

function initMessages() {
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-message-open]');
    if (!trigger) return;
    openMessages(Number(trigger.dataset.messageOpen));
  });

  $('#messagesModal')?.addEventListener('shown.bs.modal', () => $('#messageBody')?.focus());

  const form = $('[data-messages-form]');
  const field = form?.elements.body;

  // Enter يرسل، وShift+Enter سطرٌ جديد: كما في أي محادثة
  field?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = field.value.trim();
    if (body.length < 2) {
      toast('اكتب رسالتك أولاً.', 'error');
      field.focus();
      return;
    }

    // الرسالة تذهب لمن فُتحت نافذته عند الإرسال، ولو تبدّلت النافذة أثناءه
    const target = messagesFor;
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    try {
      const res = await sendPortalMessage(target, body);
      field.value = '';
      toast(res.message ?? 'وصلتنا رسالتك.');
      if (messagesFor === target) await renderMessages(target);
    } catch (error) {
      failed(error, 'تعذّر إرسال الرسالة.');
    } finally {
      button.disabled = false;
    }
  });
}

async function openMessages(id) {
  messagesFor = id;
  const s = sponsorships.find((x) => x.id === id);
  setText('#messagesTitle', `رسائلك إلى ${s?.orphan?.name ?? 'الطفل'}`);
  const avatar = $('[data-messages-avatar]');
  if (avatar && s?.orphan) {
    avatar.dataset.fallback = s.orphan.illustration_url ?? '';
    avatar.src = s.orphan.photo_url || s.orphan.illustration_url || '';
  }
  openModal('messagesModal');
  await renderMessages(id);
}

async function renderMessages(id) {
  const list = $('[data-messages-list]');
  if (!list) return;
  list.innerHTML = '<li class="pt-muted">جارٍ التحميل…</li>';

  try {
    const { data } = await portalMessages(id);
    // ردٌّ متأخر لنافذة طفلٍ آخر لا يُكتب فوق رسائل الطفل المفتوح الآن
    if (messagesFor !== id) return;
    const items = [...(data ?? [])].reverse();

    list.innerHTML = items.length
      ? items.map((m) => {
        const mine = m.sender_type === 'sponsor';
        return `
<li class="pt-msg pt-msg--${mine ? 'me' : 'them'}">
  <p>${escapeHtml(m.body ?? '')}</p>
  <small>${fmtDate(m.created_at)}${mine ? ` · ${MESSAGE_STATUS[m.status] ?? escapeHtml(m.status_label ?? '')}` : ''}</small>
  ${m.status === 'rejected' && m.rejection_reason ? `<span class="pt-msg__why">${escapeHtml(m.rejection_reason)}</span>` : ''}
</li>`;
      }).join('')
      : `<li class="pt-empty">
          <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
          <p>لا رسائل بعد. كلمةٌ قصيرة منك تعني له الكثير.</p>
        </li>`;

    const body = list.closest('.modal-body');
    if (body) body.scrollTop = body.scrollHeight;
  } catch (error) {
    if (messagesFor !== id) return;
    if (isAuthLoss(error)) { toLogin(); return; }
    list.innerHTML = `<li class="pt-muted">${escapeHtml(error.message ?? 'تعذّر تحميل الرسائل.')}</li>`;
  }
}

/* -------------------------------------------- الإيقاف المؤقت والاستئناف */

/**
 * بعد إعادة رسم القائمة: الزر الذي ضُغط (أو فُتحت منه النافذة) لم يعد موجوداً،
 * فكان التركيز يسقط إلى أول الصفحة. يعود إلى بطاقة الطفل نفسها، أو إلى
 * عنوان القائمة إن اختفت البطاقة (كفالة أُنهيت).
 */
function refocusSponsorship(id) {
  const target = $(`[data-sponsorship="${id}"] h3`) ?? $('#childrenTitle');
  if (!target) return;
  target.tabIndex = -1;
  target.focus();
}

async function changeStatus(id, status, button) {
  if (button) busy(button, true);
  try {
    const res = await setSponsorshipStatus(id, status);
    toast(res.message ?? 'حُفظ.');
    await loadOverview();
    return true;
  } catch (error) {
    failed(error, 'تعذّر تغيير حالة الكفالة.');
    // 409: الحالة تغيّرت من مكان آخر، فالبوابة تعرض الحالة الحقيقية
    if (error?.status === 409) await loadOverview();
    return false;
  } finally {
    if (button?.isConnected) busy(button, false);
  }
}

function initStatusActions() {
  document.addEventListener('click', async (e) => {
    const trigger = e.target.closest('[data-resume]');
    if (!trigger) return;
    const id = Number(trigger.dataset.resume);
    if (await changeStatus(id, 'active', trigger)) refocusSponsorship(id);
  });
}

/* ------------------------------------------ الإنهاء (والإيقاف بدلاً منه) */

let cancelFor = null;

function initCancel() {
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-cancel-open]');
    if (!trigger) return;
    cancelFor = Number(trigger.dataset.cancelOpen);
    const s = sponsorships.find((x) => x.id === cancelFor);
    const pending = s?.status === 'pending';

    // الحجز يُلغى ولا يُوقف؛ والنشطة تُعرض عليها الاستراحة قبل الإنهاء
    setText('[data-cancel-name]', s?.orphan?.name ?? '');
    setText('[data-cancel-verb]', pending ? 'إلغاء حجز' : 'إنهاء كفالة');
    setText('[data-cancel-lede]', pending
      ? 'نُلغي الحجز فيعود الطفل متاحاً لكافل آخر. لن يُخصم منك شيء.'
      : 'الإنهاء نهائي: نتوقف عن الخصم من اليوم، ونبحث للطفل عن كافل بديل كي لا تنقطع رعايته.');
    setText('[data-cancel-submit]', pending ? 'ألغِ الحجز' : 'أنهِ الكفالة');
    const pause = $('[data-cancel-pause]');
    if (pause) pause.hidden = s?.status !== 'active';
    const reason = $('[data-cancel-reason]');
    if (reason) reason.hidden = pending;

    openModal('cancelModal');
  });

  $('[data-cancel-pause]')?.addEventListener('click', async (e) => {
    const id = cancelFor;
    const ok = await changeStatus(id, 'paused', e.currentTarget);
    if (ok) {
      await closeModal('cancelModal');
      refocusSponsorship(id);
    }
  });

  const form = $('[data-cancel-form]');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = $('[data-cancel-submit]');
    busy(button, true, 'جارٍ التنفيذ…');

    try {
      const id = cancelFor;
      const res = await cancelSponsorship(id, form.elements.reason.value.trim());
      await closeModal('cancelModal');
      form.reset();
      toast(res.message ?? 'أُنهيت الكفالة.');
      await loadOverview();
      refocusSponsorship(id);
    } catch (error) {
      failed(error, 'تعذّر إنهاء الكفالة.');
    } finally {
      busy(button, false);
    }
  });
}

/* ------------------------------------------------------- القسط المتعثّر */

let cardFor = null;

/**
 * تحديث البطاقة: يُخصم القسط المتعثّر بها فوراً وتُحفظ للأشهر القادمة. الرقم
 * لا يصل خادمنا (tokenizeCard)، ورفض البنك يُكتب تحت حقل الرقم بسببه.
 */
function initCard() {
  const form = $('[data-card-form]');
  if (!form) return;
  const inputs = $$('[data-validate]', form);

  document.addEventListener('click', async (e) => {
    const trigger = e.target.closest('[data-card-open]');
    if (!trigger) return;
    cardFor = sponsorships.find((x) => x.id === Number(trigger.dataset.cardOpen));
    if (!cardFor?.payment_issue) return;

    form.reset();
    clearFieldErrors(form);
    inputs.forEach((input) => input.closest('.field')?.classList.remove('is-invalid'));
    setText('[data-card="name"]', cardFor.orphan?.name ?? '');
    setText('[data-card="issue-text"]', issueText(cardFor));
    setText('[data-card="amount"]', formatMoney(cardFor.payment_issue.amount, cardFor.currency));
    setText('[data-card="day"]', formatNumber(cardFor.billing_day ?? 1));
    openModal('cardModal');

    // الوضع التجريبي يُقال قبل أن يكتب أحدٌ رقم بطاقته الحقيقية
    try {
      bankConfig ??= await fetchCheckoutConfig();
      const demo = $('[data-card="demo"]');
      if (demo) demo.hidden = !bankConfig?.demo;
    } catch { /* النافذة تعمل بلا الإعدادات */ }
  });

  inputs.forEach((input) => input.addEventListener('blur', () => validateInput(input)));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!cardFor) return;
    const firstInvalid = inputs.find((input) => !validateInput(input));
    if (firstInvalid) {
      firstInvalid.focus();
      return;
    }

    const button = $('[type="submit"]', form);
    const card = tokenizeCard($('#cardNumber', form).value);
    busy(button, true, 'جارٍ الخصم…');
    try {
      const res = await updateSponsorshipCard(cardFor.id, { card_token: card.token, card_last4: card.last4, card_brand: card.brand });
      await closeModal('cardModal');
      toast(res.message ?? 'وصل القسط، شكراً لك.');
      await loadOverview();
    } catch (error) {
      if (isAuthLoss(error)) toLogin();
      else showFormErrors(form, error, 'تعذّر الخصم الآن.');
    } finally {
      busy(button, false);
    }
  });
}

/* ------------------------------------------------------- بيانات التحويل */

let bankConfig = null;

function initBank() {
  document.addEventListener('click', async (e) => {
    const trigger = e.target.closest('[data-bank-open]');
    if (!trigger) return;
    const s = sponsorships.find((x) => x.id === Number(trigger.dataset.bankOpen));
    if (!s) return;

    trigger.disabled = true;
    try {
      bankConfig ??= await fetchCheckoutConfig();
    } catch (error) {
      failed(error, 'تعذّر جلب بيانات الحساب.');
      return;
    } finally {
      trigger.disabled = false;
    }

    const bank = bankConfig?.bank ?? {};
    setText('[data-bank="name"]', bank.name ?? '');
    setText('[data-bank="account"]', bank.account_name ?? '');
    setText('[data-bank="iban"]', bank.iban ?? '');
    setText('[data-bank="swift"]', bank.swift ?? '');
    setText('[data-bank="reference"]', s.reference_code ?? '');
    setText('[data-bank="amount"]', formatMoney(s.cycle_amount ?? s.monthly_amount, s.currency));
    setText('[data-bank="until"]', fmtDate(s.hold_expires_at));
    openModal('bankModal');
  });

  // المرجع والـ IBAN يُنسخان بنقرة: كتابتهما يدوياً أكثر أسباب ضياع التحويل
  document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-copy]');
    if (!button) return;
    const value = $(`[data-bank="${button.dataset.copy}"]`)?.textContent.trim();
    if (!value) return;
    try {
      await navigator.clipboard.writeText(value);
      toast('نُسخ.', 'info', 2000);
    } catch {
      toast('تعذّر النسخ. حدّد النص وانسخه يدوياً.', 'error');
    }
  });
}

/* ---------------------------------------------------------------- الحساب */

function fillProfile(user) {
  const form = $('[data-portal-profile]');
  if (!form) return;
  form.elements.name.value = user.name ?? '';
  form.elements.email.value = user.email ?? '';
  form.elements.phone.value = user.phone ?? '';
  form.dataset.email = user.email ?? '';
}

/** يعرض أول خطأ تحقّق من الخادم، أو رسالته العامة. */
const firstError = (error, fallback) =>
  Object.values(error?.errors ?? {})[0]?.[0] ?? error?.message ?? fallback;

/**
 * أخطاء الحقول تحت حقولها، لا في إشعار يختفي بعد ثوانٍ.
 *
 * «كلمة المرور الحالية غير صحيحة» في إشعار عائم كانت تترك الزائر يخمّن أي
 * الحقلين أخطأ، والتركيز باقٍ على زر الحفظ. هنا تُكتب الرسالة تحت حقلها،
 * ويُربط بها الحقل (aria-describedby و aria-invalid) وينتقل التركيز إلى
 * أول حقل خاطئ. ما لا حقل له يبقى إشعاراً.
 */
function clearFieldErrors(form) {
  $$('[aria-invalid="true"]', form).forEach((input) => input.setAttribute('aria-invalid', 'false'));
  $$('.field.is-invalid', form).forEach((field) => field.classList.remove('is-invalid'));
  $$('.field__error', form).forEach((box) => { box.textContent = ''; });
}

function showFieldError(input, message) {
  const field = input.closest('.field');
  if (!field) return false;

  let box = $('.field__error', field);
  if (!box) {
    box = document.createElement('p');
    box.className = 'field__error';
    box.id = `err-${input.id || input.name}`;
    field.append(box);
    input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), box.id].filter(Boolean).join(' '));
  }

  box.textContent = message;
  field.classList.add('is-invalid');
  input.setAttribute('aria-invalid', 'true');
  return true;
}

function showFormErrors(form, error, fallback) {
  let first = null;
  Object.entries(error?.errors ?? {}).forEach(([name, messages]) => {
    const input = form.elements[name];
    if (input instanceof HTMLElement && !input.closest('[hidden]') && showFieldError(input, messages[0])) first ??= input;
  });

  if (first) first.focus();
  else toast(firstError(error, fallback), 'error');
}

/** زرّ ينتظر الخادم: معطّل، ويقول ذلك نصّاً لا بالتعطيل وحده. */
function busy(button, on, label = 'جارٍ الحفظ…') {
  if (!button) return;
  if (on) {
    button.dataset.label = button.innerHTML;
    button.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> ${label}`;
  } else if (button.dataset.label) {
    button.innerHTML = button.dataset.label;
  }
  button.disabled = on;
  button.setAttribute('aria-busy', String(on));
}

function initAccount() {
  const profile = $('[data-portal-profile]');

  // تغيير البريد يشترط كلمة المرور على الخادم: الحقل يظهر حين يتغيّر البريد فقط
  const emailField = profile?.elements.email;
  const confirmBox = $('[data-profile-confirm]');
  emailField?.addEventListener('input', () => {
    if (confirmBox) confirmBox.hidden = emailField.value.trim() === profile.dataset.email;
  });

  profile?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = profile.querySelector('[type="submit"]');
    clearFieldErrors(profile);
    busy(button, true);

    const payload = {
      name: profile.elements.name.value.trim(),
      email: profile.elements.email.value.trim(),
      phone: profile.elements.phone.value.trim() || null,
    };
    if (payload.email !== profile.dataset.email) {
      payload.current_password = profile.elements.current_password?.value ?? '';
    }

    try {
      const res = await updateProfile(payload);
      showUser({ name: res.data?.name ?? payload.name, email: res.data?.email ?? payload.email });
      profile.dataset.email = res.data?.email ?? payload.email;
      if (profile.elements.current_password) profile.elements.current_password.value = '';
      if (confirmBox) confirmBox.hidden = true;
      toast(res.message ?? 'حُفظت التعديلات.');
    } catch (error) {
      if (!isAuthLoss(error)) showFormErrors(profile, error, 'تعذّر حفظ التعديلات.');
      else toLogin();
    } finally {
      busy(button, false);
    }
  });

  // حسابٌ أُنشئ عند الكفالة بلا كلمة مرور يعرفها صاحبه: الرابط إلى بريده
  $('[data-send-reset]')?.addEventListener('click', async (e) => {
    const button = e.currentTarget;
    button.disabled = true;
    try {
      const res = await forgotPassword(profile?.dataset.email ?? '');
      toast(res.message ?? 'أرسلنا الرابط إلى بريدك.');
    } catch (error) {
      failed(error, 'تعذّر إرسال الرابط.');
    } finally {
      button.disabled = false;
    }
  });

  const password = $('[data-portal-password]');
  password?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const { current_password: current, password: next, password_confirmation: again } = password.elements;
    clearFieldErrors(password);
    if (next.value !== again.value) {
      showFieldError(again, 'كلمتا المرور غير متطابقتين.');
      again.focus();
      return;
    }

    const button = password.querySelector('[type="submit"]');
    busy(button, true);
    try {
      const res = await updatePassword({
        current_password: current.value,
        password: next.value,
        password_confirmation: again.value,
      });
      password.reset();
      toast(res.message ?? 'تم تغيير كلمة المرور. خرجت أجهزتك الأخرى من حسابك.');
    } catch (error) {
      if (!isAuthLoss(error)) showFormErrors(password, error, 'تعذّر تغيير كلمة المرور.');
      else toLogin();
    } finally {
      busy(button, false);
    }
  });
}

/* ------------------------------------------------------------ المدفوعات */

const PAY_CHIP = { succeeded: 'pt-chip--ok', failed: 'pt-chip--wait', pending: 'pt-chip--wait', refunded: '' };
const PAY_ICON = { succeeded: ['', 'fa-check'], failed: ['--warn', 'fa-xmark'], pending: ['--wait', 'fa-clock'], refunded: ['--wait', 'fa-rotate-left'] };
let paymentsPage = 0;

function initPayments() {
  $('[data-payments-more]')?.addEventListener('click', () => loadPayments(paymentsPage + 1));
  loadPayments(1);
}

/** صورة الطفل في سطر الدفعة: من كفالته في البوابة إن كانت قائمة. */
const orphanOf = (p) => sponsorships.find((s) => s.id === p.sponsorship?.id)?.orphan ?? p.sponsorship?.orphan;

/** آخر ثلاث دفعات في الرئيسية: أكانت قد وصلت؟ */
function renderRecent(items) {
  const host = $('[data-portal-recent]');
  if (!host) return;
  host.innerHTML = items.length
    ? items.slice(0, 3).map((p) => {
      const [tone, icon] = PAY_ICON[p.status] ?? PAY_ICON.pending;
      return `
<li>
  <span class="pt-recent__icon pt-recent__icon${tone}" aria-hidden="true"><i class="fa-solid ${icon}"></i></span>
  <span class="pt-recent__text"><b>${escapeHtml(p.sponsorship?.orphan?.name ?? 'كفالة')}</b><span>${fmtShort(p.paid_at ?? p.created_at)} · ${escapeHtml(p.status_label ?? '')}</span></span>
  <span class="num">${formatMoney(p.amount, p.currency)}</span>
</li>`;
    }).join('')
    : '<li class="pt-muted">لا مدفوعات بعد.</li>';
}

/** صفحة بعد صفحة: كان السجل يقف عند آخر عشر دفعات بلا طريق لما قبلها. */
async function loadPayments(page) {
  const body = $('[data-portal-payments]');
  const more = $('[data-payments-more]');
  if (!body) return;
  if (more) more.disabled = true;

  try {
    const { data, meta } = await portalPayments(`?per_page=10&page=${page}`);
    paymentsPage = meta?.current_page ?? page;

    setText('[data-stat="payments-total"]', formatMoney(meta?.total_given ?? 0));
    if (page === 1) renderRecent(data);

    if (page === 1 && !data.length) {
      body.innerHTML = '<tr><td colspan="6" class="pt-table__empty">لا مدفوعات بعد.</td></tr>';
    } else {
      const rows = data.map((p) => `
<tr>
  <td data-label="التاريخ">${fmtDate(p.paid_at ?? p.created_at)}</td>
  <td data-label="الطفل"><span class="pt-table__kid">${kidImage(orphanOf(p), 30)}${escapeHtml(p.sponsorship?.orphan?.name ?? 'غير معروف')}</span></td>
  <td data-label="المبلغ" class="num">${formatMoney(p.amount, p.currency)}</td>
  <td data-label="الوسيلة">${escapeHtml(p.card ?? p.method_label ?? '')}</td>
  <td data-label="الحالة">
    <span class="pt-chip ${PAY_CHIP[p.status] ?? ''}">${escapeHtml(p.status_label ?? '')}</span>
    ${p.status === 'failed' && p.failure_reason ? `<span class="pt-why">${escapeHtml(p.failure_reason)}</span>` : ''}
  </td>
  <td data-label="الإيصال">${p.receipt_number ? `<span class="num pt-receipt">${escapeHtml(p.receipt_number)}</span>` : '<span class="pt-receipt">لا يوجد</span>'}</td>
</tr>`).join('');
      if (page === 1) body.innerHTML = rows;
      else body.insertAdjacentHTML('beforeend', rows);
    }

    if (more) more.hidden = !(meta && meta.current_page < meta.last_page);
    const foot = $('[data-payments-foot]');
    if (foot) foot.hidden = page === 1 && !data.length;
    setText('[data-payments-shown]', meta?.total
      ? `عرض ${formatNumber(body.querySelectorAll('tr').length)} من ${formatNumber(meta.total)}`
      : '');
  } catch (error) {
    if (isAuthLoss(error)) { toLogin(); return; }
    if (page === 1) {
      body.innerHTML = '<tr><td colspan="6" class="pt-table__empty">تعذّر تحميل سجل المدفوعات.</td></tr>';
      renderRecent([]);
    } else toast(error?.message ?? 'تعذّر تحميل المزيد.', 'error');
  } finally {
    if (more) more.disabled = false;
  }
}

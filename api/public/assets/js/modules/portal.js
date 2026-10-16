/**
 * بوابة الكافل: ما يبني `dashboard.html`.
 *
 * كل رقم وصفّ هنا من `/portal/*`، فيصدق مع كل كافل لا مع كافل وهمي.
 * والصفحة تُتيح فعلاً ما يعد به الموقع: قراءة التقرير، ومراسلة الطفل عبر
 * المشرف، والإيقاف المؤقت أو الإنهاء بلا اتصال ولا إحراج، وتعديل الحساب.
 */

import { $, $$, escapeHtml, formatMoney, formatNumber } from './utils.js';
import {
  me, logout, isSponsor, isAuthLoss, portalOverview, portalPayments, markReportSeen, markSignedIn, clearSignedIn,
  portalMessages, sendPortalMessage, cancelSponsorship, setSponsorshipStatus, updateProfile, updatePassword, forgotPassword,
} from './auth.js';
import { fetchCheckoutConfig } from './api.js';
import { toast, openModal, closeModal } from './ui.js';

const dateFmt = new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'long', year: 'numeric' });
const shortFmt = new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'long' });
const fmtDate = (v) => (v ? dateFmt.format(new Date(v)) : 'غير محدد');
const fmtShort = (v) => (v ? shortFmt.format(new Date(v)) : 'غير محدد');

/** العمر بصيغة العدد العربية: سنة، سنتان، ٣ إلى ١٠ سنوات، ثم ١١ سنة. */
function ageLabel(age) {
  if (!age) return '';
  if (age === 1) return 'سنة واحدة';
  if (age === 2) return 'سنتان';
  return `${age} ${age <= 10 ? 'سنوات' : 'سنة'}`;
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

/** يملأ عنصراً بالنص، ويتجاهله بهدوء إن لم يكن في الصفحة. */
function setText(selector, value) {
  const el = $(selector);
  if (el) el.textContent = value;
}

/** انتهت الجلسة أثناء التصفح: إلى الدخول ثم العودة إلى هنا. */
function toLogin() {
  clearSignedIn();
  window.location.replace(`login.html?redirect=${encodeURIComponent('dashboard.html')}`);
}

/** خطأ طلبٍ في اللوحة: الجلسة المنتهية تُعيد للدخول، وغيرها يُقال. */
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
<div class="portal-empty" role="alert">
  <p>${escapeHtml(text)}</p>
  <button class="btn btn--outline btn--sm" type="button" data-portal-retry>أعد المحاولة</button>
</div>`;

let sponsorships = [];

export async function initPortal() {
  const root = $('[data-portal]');
  if (!root) return;

  /**
   * الحارس.
   *
   * إخفاء اللوحة عن غير الداخل ليس أمناً، فالخادم يرفض `/portal/*` بلا جلسة
   * على أي حال. هذا لتفادي عرض صفحة تنتهي بخطأ.
   */
  let user;
  try {
    user = await me({ strict: true });
  } catch {
    // الخادم متعثّر لا الجلسة: لا نُخرج الكافل، نقول ما حدث
    setText('[data-portal-subtitle]', 'تعذّر الوصول إلى الخادم الآن.');
    const list = $('[data-portal-sponsorships]');
    if (list) list.innerHTML = errorBlock('لم نستطع تحميل لوحتك. تحقّق من اتصالك ثم أعد المحاولة.');
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
    window.location.replace('login.html');
    return;
  }

  markSignedIn();
  setText('[data-portal-name]', user.name);
  setText('[data-portal-greeting]', `أهلاً ${user.name.split(' ')[0]}`);
  setText('[data-portal-avatar]', user.name.charAt(0));
  fillProfile(user);

  $('[data-portal-logout]')?.addEventListener('click', async (e) => {
    e.preventDefault();
    await logout().catch(() => {});
    clearSignedIn();
    window.location.href = 'index.html';
  });

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-portal-retry]')) loadOverview();
  });

  initTabsSpy();
  initMessages();
  initStatusActions();
  initCancel();
  initBank();
  initAccount();
  initPayments();

  await loadOverview();
  root.removeAttribute('data-portal-loading');
}

async function loadOverview() {
  try {
    const { data } = await portalOverview();
    renderStats(data);
    sponsorships = data.sponsorships ?? [];
    renderSponsorships(sponsorships);
    renderPast(data.past_sponsorships ?? []);
    renderReports(data.latest_reports ?? []);
  } catch (error) {
    if (isAuthLoss(error)) {
      toLogin();
      return;
    }

    // لا أرقام وهمية ولا «لا كفالات» كاذبة: القسم يقول إنه لم يُحمَّل
    $$('[data-stat]').forEach((el) => { if (el.tagName === 'DD') el.textContent = '·'; });
    setText('[data-portal-subtitle]', 'تعذّر تحميل لوحتك الآن.');
    const list = $('[data-portal-sponsorships]');
    if (list) list.innerHTML = errorBlock(error?.message ?? 'تعذّر تحميل كفالاتك.');
    const reports = $('[data-portal-reports]');
    if (reports) reports.innerHTML = '<li class="muted">لم تُحمَّل التقارير.</li>';
  }
}

/* ------------------------------------------------------------ التبويب النشط */

/** التبويب النشط يتبع موضع القراءة، لا النقر وحده. */
function initTabsSpy() {
  const tabs = $$('.portal-tab');
  const sections = tabs.map((t) => document.getElementById(t.hash.slice(1))).filter(Boolean);
  if (!('IntersectionObserver' in window) || !sections.length) return;

  const setActive = (id) => tabs.forEach((t) => {
    if (t.hash === `#${id}`) t.setAttribute('aria-current', 'true');
    else t.removeAttribute('aria-current');
  });

  const observer = new IntersectionObserver((entries) => {
    const visible = entries.filter((e) => e.isIntersecting)
      .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
    if (visible) setActive(visible.target.id);
  }, { rootMargin: '-30% 0px -60% 0px' });

  sections.forEach((s) => observer.observe(s));
}

/* ------------------------------------------------------------------ المؤشرات */

function renderStats(data) {
  const s = data.stats ?? {};

  setText('[data-stat="orphans"]', formatNumber(s.orphans_sponsored ?? 0));
  setText('[data-stat="given"]', formatMoney(s.total_given ?? 0));
  setText('[data-stat="commitment"]', formatMoney(s.monthly_commitment ?? 0));

  const next = data.next_payment;
  setText('[data-stat="next"]', next ? fmtShort(next.due_at) : 'لا أقساط');
  setText('[data-stat="next-hint"]', next
    ? `القسط القادم، ${formatMoney(next.amount, next.currency)}${next.interval_months > 1 ? ` ${CYCLE[next.interval_months] ?? ''}` : ''}`
    : 'القسط القادم');

  const since = data.sponsor?.since;
  setText('[data-portal-since]', since ? `كافل منذ ${new Date(since).getFullYear()}` : '');

  // نقطة «جديد» على تبويب التقارير: رقم حقيقي لا ثابت
  const badge = $('[data-portal-reports-badge]');
  if (badge) badge.hidden = !(s.new_reports > 0);

  // الأهم أولاً: حجزٌ ينتظر تحويلك يسقط إن لم يصل
  const status = s.pending_count > 0
    ? (s.pending_count === 1
      ? 'لديك كفالة محجوزة بانتظار تحويلك. تفاصيل التحويل في «كفالاتي».'
      : `لديك ${formatNumber(s.pending_count)} كفالات محجوزة بانتظار تحويلك.`)
    : s.new_reports > 0
      ? (s.new_reports === 1
        ? 'لديك تقرير جديد لم تقرأه بعد.'
        : `لديك ${formatNumber(s.new_reports)} من التقارير الجديدة لم تقرأها بعد.`)
      : next
        ? `كل التقارير مقروءة. قسطك القادم ${fmtShort(next.due_at)}.`
        : 'كل التقارير مقروءة.';
  setText('[data-portal-subtitle]', status);
}

/* ----------------------------------------------------------------- الكفالات */

/** شارة الحالة وما يعنيه كل منها للكافل. */
function statusLine(s) {
  if (s.status === 'pending') {
    return `<p class="pkid__state pkid__state--wait">
      <i class="fa-regular fa-clock" aria-hidden="true"></i>
      <span><b>بانتظار تحويلك.</b> نحجز الطفل لك حتى ${fmtShort(s.hold_expires_at)}، وتبدأ الكفالة حين يصل التحويل.</span>
    </p>`;
  }
  if (s.status === 'paused') {
    return `<p class="pkid__state pkid__state--paused">
      <i class="fa-solid fa-pause" aria-hidden="true"></i>
      <span><b>موقوفة مؤقتاً${s.paused_at ? ` منذ ${fmtShort(s.paused_at)}` : ''}.</b> لا خصم الآن، والطفل محجوز لك.</span>
    </p>`;
  }
  return '';
}

function renderSponsorships(items) {
  const list = $('[data-portal-sponsorships]');
  if (!list) return;

  if (!items.length) {
    list.innerHTML = `
<div class="portal-empty">
  <p>لا كفالات قائمة الآن.</p>
  <a class="btn btn--cta" href="orphans.html">اختر طفلاً تقف معه</a>
</div>`;
    return;
  }

  list.innerHTML = items.map((s) => {
    const orphan = s.orphan ?? {};
    const name = escapeHtml(orphan.name ?? 'غير معروف');
    const funded = Number(orphan.funded_percent ?? 0);
    const interval = Number(s.interval_months ?? 1);
    const media = orphan.photo_url
      ? `<img src="${escapeHtml(orphan.photo_url)}" alt="" loading="lazy" width="320" height="240">`
      : `<span class="pkid__empty" aria-hidden="true">${escapeHtml((orphan.name ?? '؟').charAt(0))}</span>`;

    const pending = s.status === 'pending';
    const paused = s.status === 'paused';

    // ما يظهر من الإجراءات يتبع الحالة: الحجز يُستكمل أو يُلغى، والموقوفة تُستأنف
    const actions = pending
      ? `<button class="btn btn--primary btn--sm" type="button" data-bank-open="${s.id}">
          <i class="fa-solid fa-building-columns" aria-hidden="true"></i> بيانات التحويل
        </button>
        <a class="link-arrow" href="orphan.html?id=${encodeURIComponent(orphan.reference_code ?? '')}">ملف ${name}</a>
        <button class="pkid__stop" type="button" data-cancel-open="${s.id}">ألغِ الحجز</button>`
      : `<button class="btn btn--outline btn--sm" type="button" data-message-open="${s.id}">
          <i class="fa-regular fa-envelope" aria-hidden="true"></i> أرسل رسالة
        </button>
        ${paused ? `<button class="btn btn--primary btn--sm" type="button" data-resume="${s.id}">
          <i class="fa-solid fa-play" aria-hidden="true"></i> استأنف الكفالة
        </button>` : ''}
        <a class="link-arrow" href="orphan.html?id=${encodeURIComponent(orphan.reference_code ?? '')}">ملف ${name}</a>
        <button class="pkid__stop" type="button" data-cancel-open="${s.id}">${paused ? 'إنهاء الكفالة' : 'إيقاف أو إنهاء'}</button>`;

    // الصنف sponsor-row يبقى: يعتمد عليه اختبار e2e للوحة
    return `
<article class="pkid sponsor-row" data-sponsorship="${s.id}" data-status="${escapeHtml(s.status)}">
  <div class="pkid__media">${media}</div>

  <div class="pkid__body">
    <div class="pkid__head">
      <h3>${name}<span>${orphan.age ? `، ${ageLabel(orphan.age)}` : ''}</span></h3>
      <p>${escapeHtml(s.program?.name ?? '')}${s.is_zakat ? ' <span class="pkid__tag">من الزكاة</span>' : ''}</p>
    </div>

    ${statusLine(s)}

    <dl class="pkid__facts">
      <div><dt>${interval > 1 ? `${CYCLE[interval]} (${formatMoney(s.monthly_amount, s.currency)} شهرياً)` : 'شهرياً'}</dt>
        <dd class="num">${formatMoney(interval > 1 ? s.cycle_amount : s.monthly_amount, s.currency)}</dd></div>
      <div><dt>${pending ? 'أول دفعة' : 'القسط القادم'}</dt><dd>${pending ? 'عند وصول التحويل' : paused ? 'بعد الاستئناف' : fmtShort(s.next_due_at)}</dd></div>
      <div><dt>ما قدّمته له</dt><dd class="num">${formatMoney(s.total_paid ?? 0, s.currency)}</dd></div>
    </dl>

    ${pending ? '' : `<div class="pkid__fund">
      <span class="kid__track" style="--v:${funded}%"><span></span></span>
      <span>كفالته مغطّاة بنسبة <b class="num">${funded}٪</b></span>
    </div>`}

    <div class="pkid__actions">${actions}</div>
  </div>
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
<li class="ppast">
  <strong>${escapeHtml(s.orphan?.name ?? 'غير معروف')}</strong>
  <span>${escapeHtml(s.status_label ?? '')}${s.ended_at ? ` في ${fmtDate(s.ended_at)}` : ''}</span>
  <span>قدّمت له <b class="num">${formatMoney(s.total_paid ?? 0, s.currency)}</b></span>
</li>`).join('');
}

/* ----------------------------------------------------------------- التقارير */

function renderReports(items) {
  const list = $('[data-portal-reports]');
  if (!list) return;

  if (!items.length) {
    list.innerHTML = '<li class="portal-empty"><p>لا تقارير منشورة بعد. يصلك أول تقرير بعد ثلاثة أشهر من بدء الكفالة.</p></li>';
    return;
  }

  list.innerHTML = items.map((r) => `
<li class="preport${r.is_new ? ' is-new' : ''}" data-report="${r.id}">
  <span class="preport__dot" aria-hidden="true"></span>
  <div class="preport__text">
    <strong>تقرير ${escapeHtml(r.orphan?.name ?? '')}</strong>
    <span>${escapeHtml(r.period_label)}${r.is_new ? ' · <b>جديد</b>' : ''}</span>
  </div>
  <button class="btn btn--ghost btn--sm" type="button" data-report-open="${r.id}">
    اقرأ التقرير <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
  </button>
</li>`).join('');

  /**
   * فتح التقرير يسجّل القراءة على الخادم، فتُطفأ علامة «جديد» عند الجميع،
   * لا في هذا المتصفح وحده.
   */
  $$('[data-report-open]', list).forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.dataset.reportOpen;
      button.disabled = true;

      try {
        const { data } = await markReportSeen(id);
        openReportModal(data);
        const row = $(`[data-report="${id}"]`);
        row?.classList.remove('is-new');
        row?.querySelector('.preport__text b')?.remove();
        if (!$('.preport.is-new')) $('[data-portal-reports-badge]')?.setAttribute('hidden', '');
      } catch (error) {
        failed(error, 'تعذّر فتح التقرير.');
      } finally {
        button.disabled = false;
      }
    });
  });
}

/** التقرير بشكل «نموذج التقرير» في الرئيسية: ملخص، ملاحظات، وكيف صُرفت الكفالة. */
function openReportModal(report) {
  const host = $('[data-report-content]');
  if (!host) return;

  const notes = [
    ['الدراسة', report.academic_note],
    ['الصحة', report.health_note],
    ['الحالة النفسية', report.psychosocial_note],
  ].filter(([, v]) => v);

  const breakdown = Object.entries(report.spending_breakdown ?? {})
    .filter(([key, v]) => SPENDING[key] && Number(v) > 0);

  const photo = report.media?.find((m) => m.type === 'image') ?? null;

  host.innerHTML = `
<header class="preport-modal__head">
  <p class="meta-label">${escapeHtml(report.period_label ?? '')}</p>
  <h2 id="reportModalTitle">${escapeHtml(report.title)}</h2>
  <p>${escapeHtml(report.orphan?.name ?? '')} · زيارة ميدانية في ${fmtDate(report.visit_date)}${report.supervisor ? ` مع ${escapeHtml(report.supervisor)}` : ''}</p>
</header>

${report.summary ? `<p class="preport-modal__summary">${escapeHtml(report.summary)}</p>` : ''}

${photo ? `<figure class="preport-modal__photo"><img src="${escapeHtml(photo.url)}" alt="${escapeHtml(photo.caption ?? '')}" loading="lazy"></figure>` : ''}

${notes.length ? `<dl class="preport-modal__notes">
  ${notes.map(([label, value]) => `<div><dt>${label}</dt><dd>${escapeHtml(value)}</dd></div>`).join('')}
</dl>` : ''}

${breakdown.length ? `<div class="preport-modal__spend">
  <h3>كيف صُرفت الكفالة</h3>
  <div class="report-split" role="img" aria-label="${breakdown.map(([k, v]) => `${SPENDING[k].label} ${v}٪`).join('، ')}">
    ${breakdown.map(([k, v]) => `<span style="--w:${Number(v)}%; --c:${SPENDING[k].color}"></span>`).join('')}
  </div>
  <ul class="report-legend" role="list">
    ${breakdown.map(([k, v]) => `<li><i style="--c:${SPENDING[k].color}"></i>${SPENDING[k].label} <b class="num">${Number(v)}٪</b></li>`).join('')}
  </ul>
</div>` : ''}

<p class="preport-modal__private">
  <i class="fa-solid fa-lock" aria-hidden="true"></i>
  هذا التقرير خاص بك ككافل. نرجو ألا تنشر صور الطفل أو تفاصيله خارج المنصة.
</p>`;

  openModal('reportModal');
}

/* ------------------------------------------------------------------ الرسائل */

let messagesFor = null;

/** «وافق المشرف» لا تعني «وصلت»: التسليم خطوة لاحقة يسجّلها المشرف. */
const MESSAGE_STATUS = {
  pending_review: 'بانتظار مراجعة المشرف',
  approved: 'وافق عليها المشرف، وتصل الطفل في الزيارة القادمة',
  delivered: 'وصلت للطفل',
  rejected: 'لم تُسلَّم',
};

function initMessages() {
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-message-open]');
    if (!trigger) return;
    openMessages(Number(trigger.dataset.messageOpen));
  });

  const form = $('[data-messages-form]');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const field = form.elements.body;
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
  openModal('messagesModal');
  await renderMessages(id);
}

async function renderMessages(id) {
  const list = $('[data-messages-list]');
  if (!list) return;
  list.innerHTML = '<li class="muted">جارٍ التحميل…</li>';

  try {
    const { data } = await portalMessages(id);
    // ردٌّ متأخر لنافذة طفلٍ آخر لا يُكتب فوق رسائل الطفل المفتوح الآن
    if (messagesFor !== id) return;
    const items = [...(data ?? [])].reverse();

    list.innerHTML = items.length
      ? items.map((m) => `
<li class="pmessage pmessage--${m.sender_type === 'sponsor' ? 'me' : 'them'}">
  <p>${escapeHtml(m.body ?? '')}</p>
  <span>${fmtDate(m.created_at)}${m.sender_type === 'sponsor' ? ` · ${MESSAGE_STATUS[m.status] ?? escapeHtml(m.status_label ?? '')}` : ''}</span>
  ${m.status === 'rejected' && m.rejection_reason ? `<span class="pmessage__why">${escapeHtml(m.rejection_reason)}</span>` : ''}
</li>`).join('')
      : '<li class="portal-empty"><p>لا رسائل بعد. كلمة قصيرة منك تعني له الكثير.</p></li>';

    list.scrollTop = list.scrollHeight;
  } catch (error) {
    if (messagesFor !== id) return;
    if (isAuthLoss(error)) { toLogin(); return; }
    list.innerHTML = `<li class="muted">${escapeHtml(error.message ?? 'تعذّر تحميل الرسائل.')}</li>`;
  }
}

/* ------------------------------------------------- الإيقاف المؤقت والاستئناف */

/**
 * بعد إعادة رسم القائمة: الزر الذي ضُغط (أو فُتحت منه النافذة) لم يعد موجوداً،
 * فكان التركيز يسقط إلى أول الصفحة. يعود إلى بطاقة الطفل نفسها، أو إلى
 * عنوان القائمة إن اختفت البطاقة (كفالة أُنهيت).
 */
function refocusSponsorship(id) {
  const target = $(`[data-sponsorship="${id}"] h3`)
    ?? $('[data-portal-sponsorships]')?.closest('section')?.querySelector('h2');
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
    refocusSponsorship(id);
    return true;
  } catch (error) {
    failed(error, 'تعذّر تغيير حالة الكفالة.');
    // 409: الحالة تغيّرت من مكان آخر، فاللوحة تعرض الحالة الحقيقية
    if (error?.status === 409) await loadOverview();
    return false;
  } finally {
    if (button?.isConnected) busy(button, false);
  }
}

function initStatusActions() {
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-resume]');
    if (trigger) changeStatus(Number(trigger.dataset.resume), 'active', trigger);
  });
}

/* ------------------------------------------------- الإنهاء (والإيقاف بدلاً منه) */

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
      closeModal($('#cancelModal'));
      refocusSponsorship(id);
    }
  });

  const form = $('[data-cancel-form]');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = form.querySelector('[type="submit"]');
    busy(button, true, 'جارٍ التنفيذ…');

    try {
      const id = cancelFor;
      const res = await cancelSponsorship(id, form.elements.reason.value.trim());
      closeModal($('#cancelModal'));
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

/* ------------------------------------------------------------ بيانات التحويل */

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
}

/* -------------------------------------------------------------------- الحساب */

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
      const name = res.data?.name ?? payload.name;
      setText('[data-portal-name]', name);
      setText('[data-portal-greeting]', `أهلاً ${name.split(' ')[0]}`);
      setText('[data-portal-avatar]', name.charAt(0));
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

/* ---------------------------------------------------------------- المدفوعات */

const PAY_CLASS = { succeeded: 'ok', failed: 'warn', pending: 'wait', refunded: 'muted' };
let paymentsPage = 0;

function initPayments() {
  $('[data-payments-more]')?.addEventListener('click', () => loadPayments(paymentsPage + 1));
  loadPayments(1);
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

    if (page === 1 && !data.length) {
      body.innerHTML = '<tr><td colspan="6" class="muted">لا مدفوعات بعد.</td></tr>';
    } else {
      const rows = data.map((p) => `
<tr>
  <td>${fmtDate(p.paid_at ?? p.created_at)}</td>
  <td>${escapeHtml(p.sponsorship?.orphan?.name ?? 'غير معروف')}</td>
  <td class="num">${formatMoney(p.amount, p.currency)}</td>
  <td>${escapeHtml(p.card ?? p.method_label ?? '')}</td>
  <td>
    <span class="pstatus pstatus--${PAY_CLASS[p.status] ?? 'muted'}">${escapeHtml(p.status_label ?? '')}</span>
    ${p.status === 'failed' && p.failure_reason ? `<span class="pstatus__why">${escapeHtml(p.failure_reason)}</span>` : ''}
  </td>
  <td>${p.receipt_number ? `<span class="num muted">${escapeHtml(p.receipt_number)}</span>` : '<span class="muted">لا يوجد</span>'}</td>
</tr>`).join('');
      if (page === 1) body.innerHTML = rows;
      else body.insertAdjacentHTML('beforeend', rows);
    }

    if (more) more.hidden = !(meta && meta.current_page < meta.last_page);
  } catch (error) {
    if (isAuthLoss(error)) { toLogin(); return; }
    if (page === 1) body.innerHTML = '<tr><td colspan="6" class="muted">تعذّر تحميل سجل المدفوعات.</td></tr>';
    else toast(error?.message ?? 'تعذّر تحميل المزيد.', 'error');
  } finally {
    if (more) more.disabled = false;
  }
}

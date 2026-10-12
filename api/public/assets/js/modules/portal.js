/**
 * بوابة الكافل: ما يبني `dashboard.html`.
 *
 * كل رقم وصفّ هنا من `/portal/*`، فيصدق مع كل كافل لا مع كافل وهمي.
 * والصفحة تُتيح فعلاً ما يعد به الموقع: قراءة التقرير، ومراسلة الطفل عبر
 * المشرف، وإيقاف الكفالة بلا اتصال ولا إحراج، وتعديل الحساب.
 */

import { $, $$, escapeHtml, formatMoney, formatNumber } from './utils.js';
import {
  me, logout, isSponsor, portalOverview, portalPayments, markReportSeen, markSignedIn, clearSignedIn,
  portalMessages, sendPortalMessage, cancelSponsorship, updateProfile, updatePassword,
} from './auth.js';
import { toast, openModal, closeModal } from './ui.js';

const dateFmt = new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'long', year: 'numeric' });
const shortFmt = new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'long' });
const fmtDate = (v) => (v ? dateFmt.format(new Date(v)) : 'غير محدد');
const fmtShort = (v) => (v ? shortFmt.format(new Date(v)) : 'غير محدد');
const ageLabel = (age) => (age ? `${age} ${age >= 3 && age <= 10 ? 'سنوات' : 'سنة'}` : '');

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
  const user = await me();

  if (!user) {
    clearSignedIn();
    const target = encodeURIComponent('dashboard.html');
    window.location.replace(`login.html?redirect=${target}`);
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

  initTabsSpy();
  initMessages();
  initCancel();
  initAccount();

  await loadOverview();
  loadPayments();
  root.removeAttribute('data-portal-loading');
}

async function loadOverview() {
  try {
    const { data } = await portalOverview();
    renderStats(data);
    sponsorships = data.sponsorships ?? [];
    renderSponsorships(sponsorships);
    renderReports(data.latest_reports ?? []);
  } catch (error) {
    toast(error.message ?? 'تعذّر تحميل لوحتك.', 'error');
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
  setText('[data-stat="next-hint"]', next ? `القسط القادم، ${formatMoney(next.amount)}` : 'القسط القادم');

  const since = data.sponsor?.since;
  setText('[data-portal-since]', since ? `كافل منذ ${new Date(since).getFullYear()}` : '');

  // نقطة «جديد» على تبويب التقارير: رقم حقيقي لا ثابت
  const badge = $('[data-portal-reports-badge]');
  if (badge) badge.hidden = !(s.new_reports > 0);

  const status = s.new_reports > 0
    ? (s.new_reports === 1
      ? 'لديك تقرير جديد لم تقرأه بعد.'
      : `لديك ${formatNumber(s.new_reports)} من التقارير الجديدة لم تقرأها بعد.`)
    : next
      ? `كل التقارير مقروءة. قسطك القادم ${fmtShort(next.due_at)}.`
      : 'كل التقارير مقروءة.';
  setText('[data-portal-subtitle]', status);
}

/* ----------------------------------------------------------------- الكفالات */

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
    const media = orphan.photo_url
      ? `<img src="${escapeHtml(orphan.photo_url)}" alt="" loading="lazy" width="320" height="240">`
      : `<span class="pkid__empty" aria-hidden="true">${escapeHtml((orphan.name ?? '؟').charAt(0))}</span>`;

    // الصنف sponsor-row يبقى: يعتمد عليه اختبار e2e للوحة
    return `
<article class="pkid sponsor-row" data-sponsorship="${s.id}">
  <div class="pkid__media">${media}</div>

  <div class="pkid__body">
    <div class="pkid__head">
      <h3>${name}<span>${orphan.age ? `، ${ageLabel(orphan.age)}` : ''}</span></h3>
      <p>${escapeHtml(s.program?.name ?? '')}${s.is_zakat ? ' <span class="pkid__tag">من الزكاة</span>' : ''}</p>
    </div>

    <dl class="pkid__facts">
      <div><dt>شهرياً</dt><dd class="num">${formatMoney(s.monthly_amount)}</dd></div>
      <div><dt>القسط القادم</dt><dd>${fmtShort(s.next_due_at)}</dd></div>
      <div><dt>ما قدّمته له</dt><dd class="num">${formatMoney(s.total_paid ?? 0)}</dd></div>
    </dl>

    <div class="pkid__fund">
      <span class="kid__track" style="--v:${funded}%"><span></span></span>
      <span>كفالته مغطّاة بنسبة <b class="num">${funded}٪</b></span>
    </div>

    <div class="pkid__actions">
      <button class="btn btn--outline btn--sm" type="button" data-message-open="${s.id}">
        <i class="fa-regular fa-envelope" aria-hidden="true"></i> أرسل رسالة
      </button>
      <a class="link-arrow" href="orphan.html?id=${encodeURIComponent(orphan.reference_code ?? '')}">ملف ${name}</a>
      <button class="pkid__stop" type="button" data-cancel-open="${s.id}">إيقاف الكفالة</button>
    </div>
  </div>
</article>`;
  }).join('');
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
        toast(error.message ?? 'تعذّر فتح التقرير.', 'error');
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

const MESSAGE_STATUS = {
  pending_review: 'بانتظار مراجعة المشرف',
  approved: 'وصلت للطفل',
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

    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    try {
      const res = await sendPortalMessage(messagesFor, body);
      field.value = '';
      toast(res.message ?? 'وصلتنا رسالتك.');
      await renderMessages(messagesFor);
    } catch (error) {
      toast(error.message ?? 'تعذّر إرسال الرسالة.', 'error');
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
    list.innerHTML = `<li class="muted">${escapeHtml(error.message ?? 'تعذّر تحميل الرسائل.')}</li>`;
  }
}

/* ------------------------------------------------------------- إيقاف الكفالة */

let cancelFor = null;

function initCancel() {
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-cancel-open]');
    if (!trigger) return;
    cancelFor = Number(trigger.dataset.cancelOpen);
    const s = sponsorships.find((x) => x.id === cancelFor);
    setText('[data-cancel-name]', s?.orphan?.name ?? '');
    openModal('cancelModal');
  });

  const form = $('[data-cancel-form]');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;

    try {
      const res = await cancelSponsorship(cancelFor, form.elements.reason.value.trim());
      closeModal($('#cancelModal'));
      form.reset();
      toast(res.message ?? 'أُوقفت الكفالة.');
      await loadOverview();
    } catch (error) {
      toast(error.message ?? 'تعذّر إيقاف الكفالة.', 'error');
    } finally {
      button.disabled = false;
    }
  });
}

/* -------------------------------------------------------------------- الحساب */

function fillProfile(user) {
  const form = $('[data-portal-profile]');
  if (!form) return;
  form.elements.name.value = user.name ?? '';
  form.elements.email.value = user.email ?? '';
  form.elements.phone.value = user.phone ?? '';
}

/** يعرض أول خطأ تحقّق من الخادم، أو رسالته العامة. */
const firstError = (error, fallback) =>
  Object.values(error?.errors ?? {})[0]?.[0] ?? error?.message ?? fallback;

function initAccount() {
  const profile = $('[data-portal-profile]');
  profile?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const button = profile.querySelector('[type="submit"]');
    button.disabled = true;

    try {
      const res = await updateProfile({
        name: profile.elements.name.value.trim(),
        email: profile.elements.email.value.trim(),
        phone: profile.elements.phone.value.trim() || null,
      });
      const name = res.data?.name ?? profile.elements.name.value.trim();
      setText('[data-portal-name]', name);
      setText('[data-portal-greeting]', `أهلاً ${name.split(' ')[0]}`);
      setText('[data-portal-avatar]', name.charAt(0));
      toast(res.message ?? 'حُفظت التعديلات.');
    } catch (error) {
      toast(firstError(error, 'تعذّر حفظ التعديلات.'), 'error');
    } finally {
      button.disabled = false;
    }
  });

  const password = $('[data-portal-password]');
  password?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const { current_password: current, password: next, password_confirmation: again } = password.elements;
    if (next.value !== again.value) {
      toast('كلمتا المرور غير متطابقتين.', 'error');
      again.focus();
      return;
    }

    const button = password.querySelector('[type="submit"]');
    button.disabled = true;
    try {
      const res = await updatePassword({
        current_password: current.value,
        password: next.value,
        password_confirmation: again.value,
      });
      password.reset();
      toast(res.message ?? 'تم تغيير كلمة المرور.');
    } catch (error) {
      toast(firstError(error, 'تعذّر تغيير كلمة المرور.'), 'error');
    } finally {
      button.disabled = false;
    }
  });
}

/* ---------------------------------------------------------------- المدفوعات */

async function loadPayments() {
  const body = $('[data-portal-payments]');
  if (!body) return;

  try {
    const { data, meta } = await portalPayments('?per_page=10');

    setText('[data-stat="payments-total"]', formatMoney(meta?.total_given ?? 0));

    if (!data.length) {
      body.innerHTML = '<tr><td colspan="6" class="muted">لا مدفوعات بعد.</td></tr>';
      return;
    }

    body.innerHTML = data.map((p) => {
      const ok = p.status === 'succeeded';
      return `
<tr>
  <td>${fmtDate(p.paid_at ?? p.created_at)}</td>
  <td>${escapeHtml(p.sponsorship?.orphan?.name ?? 'غير معروف')}</td>
  <td class="num">${formatMoney(p.amount)}</td>
  <td>${escapeHtml(p.card ?? p.method_label ?? '')}</td>
  <td><span class="pstatus pstatus--${ok ? 'ok' : 'warn'}">${escapeHtml(p.status_label ?? '')}</span></td>
  <td>${p.receipt_number ? `<span class="num muted">${escapeHtml(p.receipt_number)}</span>` : '<span class="muted">لا يوجد</span>'}</td>
</tr>`;
    }).join('');
  } catch {
    body.innerHTML = '<tr><td colspan="6" class="muted">تعذّر تحميل سجل المدفوعات.</td></tr>';
  }
}

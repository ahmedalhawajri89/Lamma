/**
 * بوابة الكافل — ما يبني `dashboard.html`.
 *
 * كانت كل أرقام تلك الصفحة وصفوفها نصاً مكتوباً في HTML: «أيتام تكفلهم ٢»،
 * و«إجمالي ما قدّمته ‎$3240»، و«القسط القادم ٢٥ أغسطس»، وأربعة صفوف مدفوعات.
 * صارت كلها من `/portal/overview` و`/portal/payments`، فتصدق مع كل كافل
 * لا مع كافل واحد وهمي.
 */

import { $, $$, escapeHtml, formatMoney, formatNumber } from './utils.js';
import { me, logout, portalOverview, portalPayments, markReportSeen } from './auth.js';
import { toast } from './ui.js';

const dateFmt = new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'long', year: 'numeric' });
const fmtDate = (v) => (v ? dateFmt.format(new Date(v)) : '—');

/** يملأ عنصراً بالنص، ويتجاهله بهدوء إن لم يكن في الصفحة. */
function setText(selector, value) {
  const el = $(selector);
  if (el) el.textContent = value;
}

export async function initPortal() {
  const root = $('[data-portal]');
  if (!root) return;

  /**
   * الحارس.
   *
   * إخفاء اللوحة عن غير الداخل ليس أمناً — الخادم يرفض `/portal/*` بلا جلسة
   * على أي حال. هذا لتفادي عرض صفحة تنتهي بخطأ.
   */
  const user = await me();

  if (!user) {
    const target = encodeURIComponent('dashboard.html');
    window.location.replace(`login.html?redirect=${target}`);
    return;
  }

  setText('[data-portal-name]', user.name);
  setText('[data-portal-greeting]', `أهلاً ${user.name.split(' ')[0]}`);
  setText('[data-portal-avatar]', user.name.charAt(0));

  $('[data-portal-logout]')?.addEventListener('click', async (e) => {
    e.preventDefault();
    await logout().catch(() => {});
    window.location.href = 'index.html';
  });

  try {
    const { data } = await portalOverview();
    renderStats(data);
    renderSponsorships(data.sponsorships ?? []);
    renderReports(data.latest_reports ?? []);
  } catch (error) {
    toast(error.message ?? 'تعذّر تحميل لوحتك.', 'error');
  }

  loadPayments();
  root.removeAttribute('data-portal-loading');
}

/* ------------------------------------------------------------------ المؤشرات */

function renderStats(data) {
  const s = data.stats ?? {};

  setText('[data-stat="orphans"]', formatNumber(s.orphans_sponsored ?? 0));
  setText('[data-stat="given"]', formatMoney(s.total_given ?? 0));
  setText('[data-stat="reports"]', formatNumber(s.new_reports ?? 0));
  setText('[data-stat="commitment"]', formatMoney(s.monthly_commitment ?? 0));

  const next = data.next_payment;
  setText('[data-stat="next"]', next ? fmtDate(next.due_at) : 'لا أقساط قادمة');
  setText(
    '[data-stat="next-hint"]',
    next ? `${formatMoney(next.amount)} — تلقائي، لا إجراء مطلوب` : '',
  );

  // شارة «جديد» في التنقل الجانبي — رقم حقيقي لا ثابت
  const badge = $('[data-portal-reports-badge]');
  if (badge) {
    badge.textContent = formatNumber(s.new_reports ?? 0);
    badge.hidden = !(s.new_reports > 0);
  }

  const greeting = $('[data-portal-subtitle]');
  if (greeting) {
    greeting.textContent = s.new_reports > 0
      ? `لديك ${formatNumber(s.new_reports)} من التقارير لم تطّلع عليها بعد.`
      : 'لا جديد منذ آخر زيارة — كل التقارير مقروءة.';
  }
}

/* ----------------------------------------------------------------- الكفالات */

function renderSponsorships(items) {
  const list = $('[data-portal-sponsorships]');
  if (!list) return;

  if (!items.length) {
    list.innerHTML = `
<div class="secure-note">
  <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
  <span>لا كفالات بعد. <a class="link-arrow" href="orphans.html">اختر حالة تقف معها</a></span>
</div>`;
    return;
  }

  list.innerHTML = items.map((s) => {
    const orphan = s.orphan ?? {};
    const photo = orphan.photo_url
      ? `<img src="${escapeHtml(orphan.photo_url)}" alt="" width="48" height="48">`
      : `<span class="avatar avatar--1" aria-hidden="true">${escapeHtml((orphan.name ?? '؟').charAt(0))}</span>`;

    return `
<article class="sponsor-row">
  <div class="sponsor-row__who">
    ${photo}
    <div>
      <strong>${escapeHtml(orphan.name ?? '—')}</strong>
      <span class="badge badge--brand">${escapeHtml(s.program?.name ?? '')}</span>
    </div>
  </div>
  <div class="sponsor-row__cell">
    <span>القسط القادم</span>
    <strong>${fmtDate(s.next_due_at)}</strong>
  </div>
  <div class="sponsor-row__cell">
    <span>المبلغ الشهري</span>
    <strong class="num">${formatMoney(s.monthly_amount)}</strong>
  </div>
  <div class="sponsor-row__actions">
    <a class="btn btn--ghost btn--sm" href="orphan.html?id=${encodeURIComponent(orphan.reference_code ?? '')}">الملف</a>
  </div>
</article>`;
  }).join('');
}

/* ----------------------------------------------------------------- التقارير */

function renderReports(items) {
  const list = $('[data-portal-reports]');
  if (!list) return;

  if (!items.length) {
    list.innerHTML = '<p class="muted">لا تقارير منشورة بعد. يصلك أول تقرير بعد ثلاثة أشهر من بدء الكفالة.</p>';
    return;
  }

  list.innerHTML = items.map((r) => `
<article class="report-row" data-report="${r.id}">
  <div>
    <strong>${escapeHtml(r.title)}</strong>
    ${r.is_new ? '<span class="badge badge--urgent">جديد</span>' : ''}
    <span class="muted">${escapeHtml(r.period_label)} · ${escapeHtml(r.orphan?.name ?? '')}</span>
  </div>
  <button class="btn btn--outline btn--sm" type="button" data-report-open="${r.id}">اقرأ التقرير</button>
</article>`).join('');

  /**
   * فتح التقرير يسجّل القراءة على الخادم، فتُطفأ شارة «جديد» عند الجميع
   * — لا في هذا المتصفح وحده.
   */
  $$('[data-report-open]', list).forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.dataset.reportOpen;
      button.disabled = true;

      try {
        const { data } = await markReportSeen(id);
        openReportModal(data);
        $(`[data-report="${id}"] .badge--urgent`)?.remove();
      } catch (error) {
        toast(error.message ?? 'تعذّر فتح التقرير.', 'error');
      } finally {
        button.disabled = false;
      }
    });
  });
}

function openReportModal(report) {
  const modal = $('#reportModal');
  if (!modal) return;

  setText('#reportModalTitle', report.title);
  setText('[data-report-meta]', `${report.period_label} · زيارة بتاريخ ${fmtDate(report.visit_date)}`);

  const body = $('[data-report-body]', modal);
  if (body) {
    const notes = [
      ['الدراسة', report.academic_note],
      ['الصحة', report.health_note],
      ['الحالة النفسية', report.psychosocial_note],
    ].filter(([, v]) => v);

    body.innerHTML = notes
      .map(([label, value]) => `<li><strong>${label}</strong> — ${escapeHtml(value)}</li>`)
      .join('');
  }

  const photo = $('[data-report-photo]', modal);
  if (photo) {
    const url = report.orphan?.photo_url ?? report.media?.[0]?.url;
    photo.hidden = !url;
    if (url) photo.querySelector('img').src = url;
  }

  modal.classList.add('is-open');
  document.body.classList.add('has-modal');
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

    body.innerHTML = data.map((p) => `
<tr>
  <td>${fmtDate(p.paid_at ?? p.created_at)}</td>
  <td>${escapeHtml(p.sponsorship?.orphan?.name ?? '—')}</td>
  <td class="num">${formatMoney(p.amount)}</td>
  <td>${escapeHtml(p.card ?? p.method_label)}</td>
  <td><span class="badge ${p.status === 'succeeded' ? 'badge--growth' : 'badge--urgent'}">${escapeHtml(p.status_label)}</span></td>
  <td>${p.receipt_number ? `<span class="num muted">${escapeHtml(p.receipt_number)}</span>` : '—'}</td>
</tr>`).join('');
  } catch {
    body.innerHTML = '<tr><td colspan="6" class="muted">تعذّر تحميل سجل المدفوعات.</td></tr>';
  }
}

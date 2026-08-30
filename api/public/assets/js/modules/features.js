/**
 * المميزات التفاعلية الخاصة بالمشروع:
 * حاسبة الأثر، وفلترة الأيتام، وصفحة تفاصيل اليتيم.
 */

import { $, $$, formatNumber, escapeHtml, debounce, arabicDigits, readingTime } from './utils.js';
import { ORPHANS, IMPACT_TIERS, PROGRAMS, getProgram } from './data.js';
import { orphanCard, postCard } from './render.js';
import { fetchPost, fetchPostsPage } from './api.js';
import { initReveal, initProgressBars } from './motion.js';

/* =========================================================================
   1. حاسبة الأثر
   يسحب المستخدم المبلغ فيرى فوراً ما الذي يوفّره — يحوّل الرقم المجرد
   إلى أثر ملموس، وهو ما يصنع القرار.
   ========================================================================= */
export function initImpactCalculator() {
  const range = $('#impactRange');
  const output = $('#impactAmount');
  const list = $('#impactList');
  if (!range || !output || !list) return;

  const render = () => {
    const amount = Number(range.value);

    output.textContent = formatNumber(amount);

    // تلوين الجزء المملوء من الشريط (لمتصفحات WebKit)
    const percent = ((amount - range.min) / (range.max - range.min)) * 100;
    range.style.setProperty('--fill', `${percent}%`);

    const unlocked = IMPACT_TIERS.filter((tier) => amount >= tier.min);

    list.innerHTML = unlocked.map((tier) => `
      <li>
        <span class="impact-list__icon"><i class="fa-solid ${escapeHtml(tier.icon)}" aria-hidden="true"></i></span>
        <span class="impact-list__text">
          <strong>${escapeHtml(tier.title)}</strong>
          ${escapeHtml(tier.text)}
        </span>
      </li>`).join('');

    // نبضة قصيرة على آخر عنصر أُضيف لتوضيح ما تغيّر
    list.lastElementChild?.classList.add('is-updated');

    // البرنامج الأقرب للمبلغ المختار
    const suggestion = $('#impactSuggestion');
    if (suggestion) {
      const match = [...PROGRAMS].reverse().find((p) => amount >= p.price);
      suggestion.textContent = match
        ? `هذا المبلغ يغطي «${match.name}» بالكامل.`
        : 'كل مبلغ يصنع فرقاً، مهما بدا صغيراً.';
    }
  };

  range.addEventListener('input', render);
  render();
}

/* =========================================================================
   2. فلترة الأيتام
   ========================================================================= */
export function initOrphanFilters() {
  const grid = $('#orphansGrid');
  if (!grid) return;

  const search = $('#orphanSearch');
  const programSelect = $('#filterProgram');
  const countrySelect = $('#filterCountry');
  const sortSelect = $('#filterSort');
  const countEl = $('#orphanCount');
  const resetBtn = $('#filterReset');

  /* --- تعبئة قوائم الفلترة من البيانات --- */
  if (programSelect) {
    programSelect.insertAdjacentHTML('beforeend', PROGRAMS
      .map((p) => `<option value="${escapeHtml(p.id)}">${escapeHtml(p.name)}</option>`)
      .join(''));
  }

  if (countrySelect) {
    const countries = [...new Set(ORPHANS.map((o) => o.country))].sort();
    countrySelect.insertAdjacentHTML('beforeend', countries
      .map((c) => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`)
      .join(''));
  }

  /* --- قراءة الفلتر الأولي من الرابط (قادم من صفحة البرامج) --- */
  const params = new URLSearchParams(window.location.search);
  const initialProgram = params.get('program');
  if (initialProgram && programSelect && getProgram(initialProgram)) {
    programSelect.value = initialProgram;
  }

  const SORTERS = {
    urgent: (a, b) => Number(b.urgent) - Number(a.urgent) || a.funded - b.funded,
    'funded-asc': (a, b) => a.funded - b.funded,
    'price-asc': (a, b) => a.monthly - b.monthly,
    'price-desc': (a, b) => b.monthly - a.monthly,
    'age-asc': (a, b) => a.age - b.age,
  };

  const apply = () => {
    const query = (search?.value || '').trim().toLowerCase();
    const program = programSelect?.value || '';
    const country = countrySelect?.value || '';
    const sort = sortSelect?.value || 'urgent';

    const results = ORPHANS
      .filter((o) => {
        if (program && o.program !== program) return false;
        if (country && o.country !== country) return false;
        if (!query) return true;
        return `${o.name} ${o.country} ${o.city} ${o.dream}`.toLowerCase().includes(query);
      })
      .sort(SORTERS[sort] || SORTERS.urgent);

    if (countEl) countEl.textContent = formatNumber(results.length);

    grid.innerHTML = results.length
      ? results.map((o, i) => orphanCard(o, { delay: i * 60 })).join('')
      : `<div class="empty-state full-bleed">
           <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
           <h3>لا توجد نتائج مطابقة</h3>
           <p>جرّب توسيع البحث أو إزالة بعض الفلاتر.</p>
         </div>`;

    // البطاقات جديدة في الصفحة، فنعيد تشغيل حركاتها
    initReveal();
    initProgressBars();
  };

  search?.addEventListener('input', debounce(apply, 220));
  [programSelect, countrySelect, sortSelect].forEach((el) =>
    el?.addEventListener('change', apply));

  resetBtn?.addEventListener('click', () => {
    if (search) search.value = '';
    if (programSelect) programSelect.value = '';
    if (countrySelect) countrySelect.value = '';
    if (sortSelect) sortSelect.value = 'urgent';
    apply();
  });

  apply();
}

/* =========================================================================
   3. صفحة تفاصيل اليتيم
   تقرأ المعرّف من الرابط وتملأ الصفحة، وتعود لأول حالة إن كان الرابط خاطئاً.
   ========================================================================= */
export function initOrphanDetail() {
  const root = $('#orphanDetail');
  if (!root) return;

  const id = new URLSearchParams(window.location.search).get('id');
  const orphan = ORPHANS.find((o) => o.id === id) || ORPHANS[0];
  const program = getProgram(orphan.program);

  /** يملأ كل عنصر يحمل data-field بالقيمة المناسبة — نصاً لا HTML. */
  const fields = {
    name: orphan.name,
    id: orphan.id,
    age: `${orphan.age} سنوات`,
    gender: orphan.gender,
    location: `${orphan.country} — ${orphan.city}`,
    grade: orphan.grade,
    health: orphan.health,
    dream: orphan.dream,
    story: orphan.story,
    program: program.name,
    price: `$${orphan.monthly}`,
    funded: `${orphan.funded}٪`,
  };

  Object.entries(fields).forEach(([key, value]) => {
    $$(`[data-field="${key}"]`).forEach((el) => { el.textContent = value; });
  });

  const photo = $('[data-field="photo"]');
  if (photo) {
    photo.src = orphan.photo;
    photo.alt = `صورة ${orphan.name}`;
  }

  const progress = $('#orphanProgress');
  if (progress) progress.dataset.value = orphan.funded;

  document.title = `${orphan.name} — كفالة`;

  $$('[data-sponsor-link]').forEach((link) => {
    link.href = `checkout.html?id=${encodeURIComponent(orphan.id)}`;
  });

  const urgentSlot = $('#orphanUrgent');
  if (urgentSlot && !orphan.urgent) urgentSlot.remove();

  /* --- حالات مقترحة: نفس البرنامج، باستثناء الحالي --- */
  const similar = ORPHANS
    .filter((o) => o.id !== orphan.id && o.program === orphan.program)
    .concat(ORPHANS.filter((o) => o.id !== orphan.id && o.program !== orphan.program))
    .slice(0, 6);

  const similarHost = $('#similarOrphans');
  if (similarHost) {
    similarHost.innerHTML = similar
      .map((o) => `<div class="slider__slide">${orphanCard(o)}</div>`)
      .join('');
  }
}

/* =========================================================================
   4. ملخص الدفع — يقرأ اليتيم المختار من الرابط
   ========================================================================= */
export function initCheckoutSummary() {
  const summary = $('#checkoutSummary');
  if (!summary) return;

  const id = new URLSearchParams(window.location.search).get('id');
  const orphan = ORPHANS.find((o) => o.id === id) || ORPHANS[0];
  const program = getProgram(orphan.program);

  $$('[data-checkout="name"]').forEach((el) => { el.textContent = orphan.name; });
  $$('[data-checkout="program"]').forEach((el) => { el.textContent = program.name; });
  $$('[data-checkout="price"]').forEach((el) => { el.textContent = `$${orphan.monthly}`; });
  $$('[data-checkout="id"]').forEach((el) => { el.textContent = orphan.id; });

  const photo = $('[data-checkout="photo"]');
  if (photo) {
    photo.src = orphan.photo;
    photo.alt = `صورة ${orphan.name}`;
  }

  const form = $('#checkoutForm');
  if (form) form.dataset.redirect = `thank-you.html?id=${encodeURIComponent(orphan.id)}`;
}

/* =========================================================================
   5. صفحة الشكر
   ========================================================================= */
export function initThankYou() {
  const card = $('#thanksCard');
  if (!card) return;

  const id = new URLSearchParams(window.location.search).get('id');
  const orphan = ORPHANS.find((o) => o.id === id) || ORPHANS[0];
  const program = getProgram(orphan.program);

  $$('[data-thanks="name"]').forEach((el) => { el.textContent = orphan.name; });
  $$('[data-thanks="program"]').forEach((el) => { el.textContent = program.name; });
  $$('[data-thanks="price"]').forEach((el) => { el.textContent = `$${orphan.monthly}`; });
  $$('[data-thanks="ref"]').forEach((el) => { el.textContent = orphan.id; });

  const photo = $('[data-thanks="photo"]');
  if (photo) {
    photo.src = orphan.photo;
    photo.alt = `صورة ${orphan.name}`;
  }

  launchConfetti();
}

/** قصاصات احتفال خفيفة — تُزال من DOM بعد انتهائها. */
function launchConfetti() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const host = $('.thanks');
  if (!host) return;

  const colors = ['#c2410c', '#f0910c', '#4d7c0f', '#0f766e', '#fcca4d'];

  for (let i = 0; i < 40; i += 1) {
    const bit = document.createElement('span');
    bit.className = 'confetti';
    bit.style.insetInlineStart = `${Math.random() * 100}%`;
    bit.style.background = colors[i % colors.length];
    bit.style.animationDuration = `${2.5 + Math.random() * 2}s`;
    bit.style.animationDelay = `${Math.random() * 1.2}s`;
    bit.addEventListener('animationend', () => bit.remove(), { once: true });
    host.appendChild(bit);
  }
}


/* =========================================================================
   6. صفحة المقال
   ========================================================================= */

/**
 * يعرض مقالاً واحداً حسب `?slug=` في العنوان.
 *
 * المدونة كانت تعرض البطاقات بروابط `href="#"` — تُقرأ عناوينها ولا يُقرأ
 * شيء منها. وهذه الصفحة تكمل الدائرة.
 *
 * والنصّ يأتي من نقطة المقال الواحد لا من القائمة: `PostResource` لا يُرجع
 * `body` في القوائم عمداً، فلا نُحمّل نصوص اثني عشر مقالاً لعرض بطاقاتها.
 */
export async function initPostDetail() {
  const host = $('#postDetail');
  if (!host) return;

  const slug = new URLSearchParams(window.location.search).get('slug');

  if (!slug) {
    window.location.replace('blog.html');
    return;
  }

  const post = await fetchPost(slug);

  if (!post) {
    host.innerHTML = `
<section class="section section--tight">
  <div class="container container--narrow" style="text-align:center">
    <h1>لم نجد هذا المقال</h1>
    <p class="muted" style="margin-block:var(--space-4)">
      ربما حُذف أو تغيّر رابطه.
    </p>
    <a class="btn btn--primary" href="blog.html">العودة إلى المدونة</a>
  </div>
</section>`;
    host.removeAttribute('aria-busy');
    return;
  }

  document.title = `${post.title} | كفالة`;

  const cover = post.image
    ? `<div class="frame" style="aspect-ratio:16/9;margin-block-end:var(--space-8)">
         <img class="frame__img" src="${escapeHtml(post.image)}" alt="" width="1200" height="675">
       </div>`
    : '';

  // النصّ يصل فقرات مفصولة بأسطر فارغة؛ نحوّلها إلى <p> بعد التهريب
  const body = String(post.body ?? post.excerpt ?? '')
    .split(/\n{2,}/)
    .map((para) => para.trim())
    .filter(Boolean)
    .map((para) => `<p>${escapeHtml(para)}</p>`)
    .join('');

  host.innerHTML = `
<section class="page-hero">
  <div class="glow" aria-hidden="true"></div>
  <div class="container">
    <div class="page-hero__inner">
      <nav aria-label="مسار التنقل">
        <ol class="breadcrumb">
          <li><a href="index.html">الرئيسية</a></li>
          <li><a href="blog.html">المدونة</a></li>
          <li><span aria-current="page">${escapeHtml(post.category ?? 'مقال')}</span></li>
        </ol>
      </nav>
      <span class="eyebrow">${escapeHtml(post.category ?? 'مقال')}</span>
      <h1 class="page-hero__title">${escapeHtml(post.title)}</h1>
      <p class="page-hero__lede">${escapeHtml(post.excerpt ?? '')}</p>

      <div class="cluster" style="gap:var(--space-5);margin-block-start:var(--space-5)">
        <time class="muted" datetime="${escapeHtml(post.date ?? '')}">
          <i class="fa-regular fa-calendar" aria-hidden="true"></i> ${escapeHtml(arabicDigits(post.dateLabel ?? ''))}
        </time>
        <span class="muted">
          <i class="fa-regular fa-clock" aria-hidden="true"></i> ${readingTime(post.readMin)} قراءة
        </span>
      </div>
    </div>
  </div>
</section>

<section class="section section--tight">
  <div class="container container--narrow">
    ${cover}
    <div class="prose">${body}</div>

    <div class="cluster" style="margin-block-start:var(--space-10)">
      <a class="btn btn--outline" href="blog.html">
        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> كل المقالات
      </a>
      <a class="btn btn--primary" href="orphans.html">اختر طفلاً تكفله</a>
    </div>
  </div>
</section>`;

  host.removeAttribute('aria-busy');
  initReveal();
}

/* =========================================================================
   7. «تحميل مقالات أقدم»
   ========================================================================= */

/**
 * يجلب الصفحة التالية ويُلحقها بالشبكة.
 *
 * كان الزرّ موجوداً بلا معالج إطلاقاً: يضغطه الزائر فلا يحدث شيء. وهذا أسوأ
 * من غيابه — الزرّ الميت يوحي بأن الموقع معطّل.
 *
 * ويختفي حين لا يبقى شيء، فلا يعِد بما لا يملك.
 */
export function initPostsPager() {
  const button = $('#loadMorePosts');
  const grid = $('#postsGrid');
  if (!button || !grid) return;

  let page = 1;
  let lastPage = 1;
  const original = button.innerHTML;

  // البذرة قد تكون كل ما لدينا (وضع بلا خادم) — عندها لا صفحات أخرى
  fetchPostsPage(1).then((res) => {
    lastPage = res?.meta?.last_page ?? 1;
    button.hidden = lastPage <= 1;
  });

  button.addEventListener('click', async () => {
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> جارٍ التحميل…';

    const res = await fetchPostsPage(page + 1);

    button.disabled = false;
    button.innerHTML = original;

    if (!res || !res.posts.length) {
      button.hidden = true;
      return;
    }

    page += 1;
    lastPage = res.meta.last_page ?? page;

    grid.insertAdjacentHTML(
      'beforeend',
      res.posts.map((post, i) => postCard(post, { delay: i * 60 })).join(''),
    );

    initReveal();
    button.hidden = page >= lastPage;
  });
}


/* =========================================================================
   8. صفحة تعيين كلمة المرور
   ========================================================================= */

/**
 * يملأ الرمز والبريد من رابط البريد الإلكتروني.
 *
 * النموذج يبدأ `hidden` ولا يظهر إلا بوجود الرمز: عرض حقول كلمة مرور
 * لطلبٍ يُرفض حتماً يجعل المستخدم يجرّب ثلاث مرات قبل أن يفهم أن العطب
 * في الرابط لا في كلمته.
 */
export function initResetPassword() {
  const form = $('#resetPasswordForm');
  const invalid = $('#resetInvalid');
  if (!form || !invalid) return;

  const params = new URLSearchParams(window.location.search);
  const token = params.get('token');
  const email = params.get('email');

  if (!token || !email) {
    invalid.hidden = false;
    return;
  }

  $('#resetToken').value = token;
  $('#resetEmailHidden').value = email;

  const label = $('[data-reset-email]');
  if (label) label.textContent = email;

  form.hidden = false;
  $('#newPassword')?.focus();
}

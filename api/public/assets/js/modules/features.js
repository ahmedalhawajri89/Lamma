/**
 * المميزات التفاعلية الخاصة بالمشروع:
 * حاسبة الأثر، وفلترة الأيتام، وصفحة تفاصيل اليتيم.
 */

import { $, $$, formatNumber, escapeHtml, arabicDigits, readingTime } from './utils.js';
import { ORPHANS, IMPACT_TIERS, PROGRAMS, getProgram } from './data.js';
import { orphanCard } from './render.js';
import { fetchPost } from './api.js';
import { initReveal } from './motion.js';

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
   2. فلترة الأيتام: انتقلت إلى catalog.js (فلترة وتصفيح على الخادم)
   ========================================================================= */

/* =========================================================================
   3. صفحة تفاصيل اليتيم
   تقرأ المعرّف من الرابط وتملأ الصفحة.

   كانت تبحث في الأطفال المحمَّلين مع الصفحة (أول مئة)، وإن لم تجد عرضت
   **أول طفل** بصمت: رابطُ «سارة» يفتح ملف «كرم» ويُكفل كرم. الآن يُطلب من
   الخادم برمزه قبل البناء إن لم يكن محمَّلاً، ورابطٌ لا طفل له يقول ذلك.
   ========================================================================= */
export function initOrphanDetail() {
  const root = $('#orphanDetail');
  if (!root) return;

  // إن لم يكن محمَّلاً طلبه hydrate() برمزه قبل البناء (data.js)
  const id = new URLSearchParams(window.location.search).get('id');
  // بلا رمز في الرابط: أول طفل (صفحة تجريبية تُفتح مباشرة)؛ ورمزٌ لا طفل له: «لم نجد»
  const orphan = id ? ORPHANS.find((o) => o.id === id) : ORPHANS[0];
  if (!orphan) {
    root.innerHTML = `<div class="container section"><div class="empty-state">
      <i class="fa-solid fa-child" aria-hidden="true"></i>
      <h1>لم نجد هذا الطفل</h1>
      <p>ربما كُفل كاملاً أو تغيّر رابطه. الأطفال الذين ينتظرون كافلاً في صفحتهم.</p>
      <a class="btn btn--primary" href="orphans.html">كل الأطفال</a></div></div>`;
    document.title = 'لم نجد هذا الطفل | كفالة';
    return;
  }
  const program = getProgram(orphan.program) ?? { name: '' };

  /** يملأ كل عنصر يحمل data-field بالقيمة المناسبة — نصاً لا HTML. */
  const age = Number(orphan.age);
  const her = orphan.gender === 'أنثى';
  const remaining = Math.max(0, Math.round((orphan.monthly * (100 - orphan.funded)) / 100));

  const fields = {
    name: orphan.name,
    id: orphan.id,
    age: `${age} ${age >= 3 && age <= 10 ? 'سنوات' : 'سنة'}`,
    gender: orphan.gender,
    location: `${orphan.city}، ${orphan.country}`,
    grade: orphan.grade,
    health: orphan.health,
    dream: orphan.dream,
    story: orphan.story,
    program: program.name,
    price: `$${orphan.monthly}`,
    funded: `${orphan.funded}٪`,
    remaining: `$${remaining}`,
    'dream-label': her ? 'حلمها' : 'حلمه',
  };

  Object.entries(fields).forEach(([key, value]) => {
    $$(`[data-field="${key}"]`).forEach((el) => { el.textContent = value; });
  });

  const photo = $('[data-field="photo"]');
  if (photo) {
    photo.src = orphan.photo;
    // الصور توضيحية بلا وجوه، فلا نصفها بأنها صورة الطفل نفسه
    photo.alt = '';
  }

  const fund = $('#orphanFund');
  if (fund) fund.style.setProperty('--v', `${Number(orphan.funded)}%`);

  document.title = `${orphan.name} | كفالة`;

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

/** قصاصات احتفال خفيفة — تُزال من DOM بعد انتهائها. */
export function launchConfetti() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const host = $('.thanks');
  if (!host) return;

  // ألوان المخططات نفسها — تتبع الهوية والوضع الليلي بلا قائمة مكرّرة هنا
  const colors = [1, 2, 3, 4, 5].map((n) => `var(--chart-${n})`);

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

  /*
    النصّ المنسّق (HTML) يصل من المحرّر وقد نظّفه الخادم عند الحفظ
    (App\Services\RichText: الوسوم المسموحة وحدها، بلا سكربت ولا سمات أحداث)،
    فيُعرض كما هو. والنصّ القديم العادي يبقى بفقراته المهرَّبة.
  */
  const raw = String(post.body ?? '');
  const isHtml = /^\s*<(p|h2|h3|ul|ol|blockquote|hr)\b/i.test(raw);

  // النصّ العادي يصل فقرات مفصولة بأسطر فارغة؛ نحوّلها إلى <p> بعد التهريب
  const body = isHtml ? raw : String(post.body ?? post.excerpt ?? '')
    /*
      الفاصل يحتمل CRLF: مربّع النصّ في المتصفح يُطبّع أسطره إلى \r\n،
      فيصير فاصل الفقرات \r\n\r\n — ولا يطابقه /\n{2,}/ لأن السطرين فيه
      غير متجاورين. فتنهار مقالة كاملة إلى فقرة واحدة طويلة بلا سبب ظاهر.
    */
    .split(/(?:\r?\n[ \t]*){2,}/)
    .map((para) => para.trim())
    .filter(Boolean)
    .map((para) => `<p>${escapeHtml(para)}</p>`)
    .join('');

  host.innerHTML = `
<section class="page-hero">
  <div class="container">
    <div class="page-hero__inner">
      <nav aria-label="مسار التنقل">
        <ol class="breadcrumb">
          <li><a href="index.html">الرئيسية</a></li>
          <li><a href="blog.html">المدونة</a></li>
          <li><span aria-current="page">${escapeHtml(post.category ?? 'مقال')}</span></li>
        </ol>
      </nav>
      <p class="meta-label">${escapeHtml(post.category ?? 'مقال')}</p>
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
      <a class="btn btn--cta" href="orphans.html">اكفل يتيماً</a>
    </div>
  </div>
</section>`;

  host.removeAttribute('aria-busy');
  initReveal();
}

/* =========================================================================
   7. صفحات المدونة: انتقلت إلى blog.js (مجلة بتصنيفات وبحث وصفحات)
   ========================================================================= */

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

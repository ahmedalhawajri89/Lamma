/**
 * أدوات مشتركة صغيرة.
 * قاعدة أمنية: أي نص من مصدر خارجي أو من المستخدم يُحقن عبر textContent فقط،
 * ولا يُستخدم innerHTML إلا مع قوالب ثابتة نكتبها نحن.
 */

/** اختيار عنصر واحد. */
export const $ = (selector, scope = document) => scope.querySelector(selector);

/** اختيار عدة عناصر كمصفوفة حقيقية. */
export const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

/** هل يفضّل المستخدم تقليل الحركة؟ */
export const prefersReducedMotion = () =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** تحديد قيمة ضمن مجال. */
export const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

/**
 * تنسيق رقم بالأرقام العربية الشرقية المستخدمة في الواجهة.
 * نستخدم en-US لأن أرقام اللاتينية أوضح في السياق المالي العربي الحديث.
 */
export const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value);

/**
 * المال.
 *
 * السنتات تظهر حين تكون فقط: «$65» لسعر برنامج مستدير، و«$522.20» لمجموع
 * مدفوعات. إظهارها دائماً يضيف ضجيجاً على الأسعار، وإخفاؤها دائماً يجعل
 * مجموعاً حقيقياً يبدو مقرَّباً — و«$522.2» أسوأ الاثنين لأنه ليس مبلغاً.
 */
export const formatMoney = (value, currency = 'USD') => {
  const amount = Number(value) || 0;
  const hasCents = Math.round(amount * 100) % 100 !== 0;
  const digits = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: hasCents ? 2 : 0,
    maximumFractionDigits: 2,
  }).format(amount);

  // كفالةٌ بعملة أخرى لا تُكتب بعلامة الدولار
  return !currency || currency === 'USD' ? `$${digits}` : `${digits} ${currency}`;
};

/** تأخير تنفيذ دالة حتى يهدأ الاستدعاء. */
export function debounce(fn, wait = 200) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
}

/** تنفيذ مرة واحدة لكل إطار رسم — للأحداث كثيفة التكرار مثل scroll. */
export function rafThrottle(fn) {
  let ticking = false;
  return (...args) => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      fn(...args);
      ticking = false;
    });
  };
}

/**
 * تهريب أي نص قبل وضعه داخل قالب HTML.
 * خط الدفاع الأخير ضد XSS إذا جاءت البيانات لاحقاً من واجهة برمجية.
 * والقيمة الغائبة (null أو undefined) نصٌّ فارغ، لا كلمة «null» على الصفحة.
 */
export const escapeHtml = (value) =>
  String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[char]);

/** اسم ملف الصفحة الحالية، مع اعتبار الجذر صفحةً رئيسية. */
export function currentPage() {
  const file = window.location.pathname.split('/').pop();
  return !file || file === '' ? 'index.html' : file;
}

/**
 * مراقب ظهور موحّد — بدل إنشاء IntersectionObserver لكل مكون.
 * @param {Element[]} elements
 * @param {(el: Element) => void} onEnter
 */
export function observeOnce(elements, onEnter, options = {}) {
  if (!elements.length) return;

  if (!('IntersectionObserver' in window)) {
    elements.forEach(onEnter);
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      onEnter(entry.target);
      observer.unobserve(entry.target);
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px', ...options });

  elements.forEach((el) => observer.observe(el));
}

/**
 * أرقام هندية للنثر العربي.
 *
 * الموقع يستعمل اللاتينية في الجداول والمبالغ (`.num`) لأنها تُحاذى رأسياً
 * وتُنسخ صحيحة. أما داخل جملة عربية فالهندية أنسب — و`2026` وسط نصٍّ هندي
 * يبدو خطأً مطبعياً.
 */
export const arabicDigits = (n) => String(n).replace(/\d/g, (d) => '٠١٢٣٤٥٦٧٨٩'[d]);

/**
 * «دقيقتان» لا «2 دقائق».
 *
 * العربية تُعدّ بأربع صيغ لا بواحدة، والقالب `${n} دقائق` يُخرج «١ دقائق»
 * و«٢ دقائق» — وكلاهما خطأ ظاهر لأي قارئ عربي.
 */
export function readingTime(minutes) {
  const n = Number(minutes) || 0;
  if (n === 1) return 'دقيقة واحدة';
  if (n === 2) return 'دقيقتان';
  if (n <= 10) return `${arabicDigits(n)} دقائق`;
  return `${arabicDigits(n)} دقيقة`;
}

/**
 * عدٌّ صاعد مرة واحدة، أقل من ثانية، وبلا حركة لمن طلب تقليلها.
 * `format` يكتب القيمة (الافتراضي عددٌ بفواصل، ويُمرَّر مبلغٌ بعملته).
 */
export function countUp(el, target, format = formatNumber) {
  if (prefersReducedMotion() || !target) { el.textContent = format(target); return; }
  const start = performance.now();
  const dur = 900;
  const tick = (now) => {
    const t = Math.min(1, (now - start) / dur);
    el.textContent = format(Math.round(target * (1 - (1 - t) ** 3)));
    if (t < 1) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}

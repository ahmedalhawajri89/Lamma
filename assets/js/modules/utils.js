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

/** تنسيق مبلغ بالدولار. */
export const formatMoney = (value) => `$${formatNumber(value)}`;

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
 */
export const escapeHtml = (value) =>
  String(value).replace(/[&<>"']/g, (char) => ({
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

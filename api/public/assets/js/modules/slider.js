/**
 * سلايدر مبني على scroll-snap الأصلي في المتصفح.
 *
 * لماذا لا مكتبة خارجية؟ لأن المتصفح يوفّر التمرير السلس والالتقاط
 * والوصول بالكيبورد مجاناً. نضيف فوقه: السحب بالفأرة، أزرار التنقل،
 * النقاط، وشريط التقدم — بأقل من ١٥٠ سطراً وبأداء أعلى من أي مكتبة.
 *
 * البنية المتوقعة:
 * <div class="slider" data-slider>
 *   <div class="slider__viewport" data-slider-viewport>
 *     <div class="slider__track">…شرائح…</div>
 *   </div>
 *   <button data-slider-prev>…</button>
 *   <button data-slider-next>…</button>
 *   <div data-slider-dots></div>
 * </div>
 */

import { $, $$, rafThrottle, prefersReducedMotion } from './utils.js';

const DRAG_THRESHOLD = 6; // بكسل — تحت هذا الحد نعتبرها نقرة لا سحباً

function createSlider(root) {
  const viewport = $('[data-slider-viewport]', root);
  const track = $('.slider__track', viewport);
  if (!viewport || !track) return;

  const slides = [...track.children];
  if (!slides.length) return;

  const prevBtn = $('[data-slider-prev]', root);
  const nextBtn = $('[data-slider-next]', root);
  const dotsHost = $('[data-slider-dots]', root);
  const progressBar = $('[data-slider-progress]', root);

  /* --- حساب مقدار خطوة واحدة (عرض شريحة + الفجوة) --- */
  const stepSize = () => {
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    return slides[0].getBoundingClientRect().width + gap;
  };

  /**
   * في RTL تكون قيمة scrollLeft سالبة في المتصفحات الحديثة.
   * نحوّلها دائماً إلى مسافة موجبة من البداية المنطقية لتوحيد الحسابات.
   */
  const maxScroll = () => viewport.scrollWidth - viewport.clientWidth;
  const getPosition = () => Math.abs(viewport.scrollLeft);
  const isRtl = () => getComputedStyle(viewport).direction === 'rtl';

  const scrollBySteps = (steps) => {
    const delta = stepSize() * steps * (isRtl() ? -1 : 1);
    viewport.scrollBy({
      left: delta,
      behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
  };

  /* --- النقاط --- */
  let dots = [];
  if (dotsHost) {
    dotsHost.innerHTML = '';
    dots = slides.map((_, i) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'slider__dot';
      dot.setAttribute('aria-label', `الانتقال إلى الشريحة ${i + 1}`);
      dot.addEventListener('click', () => {
        viewport.scrollTo({
          left: stepSize() * i * (isRtl() ? -1 : 1),
          behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        });
      });
      dotsHost.appendChild(dot);
      return dot;
    });
  }

  /* --- تحديث الحالة (الأزرار، النقاط، شريط التقدم) --- */
  const sync = rafThrottle(() => {
    const position = getPosition();
    const max = maxScroll();
    const atStart = position <= 1;
    const atEnd = position >= max - 1;

    if (prevBtn) prevBtn.disabled = atStart;
    if (nextBtn) nextBtn.disabled = atEnd;

    const activeIndex = Math.round(position / stepSize());
    dots.forEach((dot, i) => {
      dot.setAttribute('aria-current', String(i === activeIndex));
    });

    if (progressBar) {
      const visibleRatio = viewport.clientWidth / viewport.scrollWidth;
      const scrolled = max > 0 ? position / max : 0;
      progressBar.style.inlineSize = `${Math.min(visibleRatio * 100, 100)}%`;
      progressBar.style.transform =
        `translateX(${(isRtl() ? -1 : 1) * scrolled * (100 / visibleRatio - 100)}%)`;
    }
  });

  viewport.addEventListener('scroll', sync, { passive: true });
  window.addEventListener('resize', sync);
  sync();

  prevBtn?.addEventListener('click', () => scrollBySteps(-1));
  nextBtn?.addEventListener('click', () => scrollBySteps(1));

  /* --- السحب بالمؤشر --- */
  let pointerId = null;
  let startX = 0;
  let startScroll = 0;
  let moved = false;

  viewport.addEventListener('pointerdown', (e) => {
    // نتجاهل الأزرار الوسطى/اليمنى واللمس (اللمس يعمل أصلاً بشكل أصلي)
    if (e.button !== 0 || e.pointerType === 'touch') return;
    pointerId = e.pointerId;
    startX = e.clientX;
    startScroll = viewport.scrollLeft;
    moved = false;
    viewport.setPointerCapture(pointerId);
  });

  viewport.addEventListener('pointermove', (e) => {
    if (pointerId !== e.pointerId) return;
    const distance = e.clientX - startX;

    if (!moved && Math.abs(distance) > DRAG_THRESHOLD) {
      moved = true;
      viewport.classList.add('is-dragging');
    }

    if (moved) viewport.scrollLeft = startScroll - distance;
  });

  const endDrag = (e) => {
    if (pointerId !== e.pointerId) return;
    viewport.releasePointerCapture?.(pointerId);
    pointerId = null;
    viewport.classList.remove('is-dragging');
  };

  viewport.addEventListener('pointerup', endDrag);
  viewport.addEventListener('pointercancel', endDrag);

  // منع فتح الرابط إذا كانت الحركة سحباً لا نقرة
  viewport.addEventListener('click', (e) => {
    if (moved) {
      e.preventDefault();
      e.stopPropagation();
      moved = false;
    }
  }, true);

  /* --- الكيبورد --- */
  viewport.setAttribute('tabindex', '0');
  viewport.setAttribute('role', 'region');
  viewport.setAttribute('aria-label', root.dataset.sliderLabel || 'عارض شرائح');

  viewport.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { e.preventDefault(); scrollBySteps(isRtl() ? -1 : 1); }
    if (e.key === 'ArrowLeft')  { e.preventDefault(); scrollBySteps(isRtl() ? 1 : -1); }
    // البداية صفرٌ في الاتجاهين، والنهاية سالبة في RTL (كما يعدّها المتصفح)
    const behavior = prefersReducedMotion() ? 'auto' : 'smooth';
    const end = viewport.scrollWidth - viewport.clientWidth;
    if (e.key === 'Home') { e.preventDefault(); viewport.scrollTo({ left: 0, behavior }); }
    if (e.key === 'End')  { e.preventDefault(); viewport.scrollTo({ left: isRtl() ? -end : end, behavior }); }
  });
}

export function initSliders() {
  $$('[data-slider]').forEach(createSlider);
}

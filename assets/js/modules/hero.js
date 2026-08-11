/**
 * سلايدر الهيرو السينمائي.
 *
 * نقاط الوصولية المقصودة:
 *   • زر إيقاف/تشغيل ظاهر — معيار WCAG 2.2.2 يوجب أن يكون كل محتوى
 *     متحرّك تلقائياً قابلاً للإيقاف.
 *   • التشغيل التلقائي يتوقف عند تمرير الفأرة أو دخول التركيز أو خروج
 *     التبويب من الشاشة، فلا تنتقل الشريحة والمستخدم يقرأها.
 *   • منطقة حيّة تُعلن رقم الشريحة للقارئ الصوتي.
 *   • لا تشغيل تلقائي إطلاقاً مع prefers-reduced-motion.
 */

import { $, $$, prefersReducedMotion } from './utils.js';

const DURATION = 7000; // مدة عرض الشريحة الواحدة

export function initHeroSlider() {
  const hero = $('#heroSlider');
  if (!hero) return;

  const slides = $$('.hero__slide', hero);
  const thumbs = $$('.hero__thumb', hero);
  const titleHost = $('#heroContent', hero);
  const counter = $('#heroCount', hero);
  const live = $('#heroLive', hero);
  const playBtn = $('#heroPlay', hero);
  const prevBtn = $('#heroPrev', hero);
  const nextBtn = $('#heroNext', hero);

  if (slides.length < 2) {
    hero.classList.add('is-ready');
    return;
  }

  /* نصوص الشرائح مخزّنة في data-* على كل شريحة، فيبقى المحتوى في HTML */
  const content = slides.map((slide) => ({
    eyebrow: slide.dataset.eyebrow || '',
    title: slide.dataset.title || '',
    accent: slide.dataset.accent || '',
    lede: slide.dataset.lede || '',
  }));

  const reduced = prefersReducedMotion();
  let index = 0;
  let timer = null;
  let playing = false;

  const pad = (n) => String(n).padStart(2, '0');

  /** يكتب نصوص الشريحة ويعيد تشغيل حركة الدخول. */
  function paint(i) {
    const c = content[i];

    // نبني العناصر يدوياً ونضع النص بـ textContent — لا حقن HTML
    titleHost.replaceChildren();

    if (c.eyebrow) {
      const eyebrow = document.createElement('span');
      eyebrow.className = 'hero__eyebrow';
      const dot = document.createElement('span');
      dot.className = 'pulse-dot';
      eyebrow.append(dot, document.createTextNode(` ${c.eyebrow}`));
      titleHost.append(eyebrow);
    }

    const h1 = document.createElement('h1');
    h1.className = 'hero__title';
    h1.append(document.createTextNode(c.title + ' '));
    if (c.accent) {
      const em = document.createElement('em');
      em.textContent = c.accent;
      h1.append(em);
    }
    titleHost.append(h1);

    const lede = document.createElement('p');
    lede.className = 'hero__lede';
    lede.textContent = c.lede;
    titleHost.append(lede);

    titleHost.append(actions());

    // إعادة تشغيل حركة الدخول: نزيل الصنف ونجبر إعادة الحساب ثم نعيده
    hero.classList.remove('is-ready');
    void hero.offsetWidth;
    hero.classList.add('is-ready');
  }

  /** أزرار الدعوة — ثابتة عبر الشرائح. */
  function actions() {
    const wrap = document.createElement('div');
    wrap.className = 'hero__actions';

    const primary = document.createElement('a');
    primary.className = 'btn btn--primary btn--lg';
    primary.href = 'orphans.html';
    primary.append(document.createTextNode('اختر طفلاً تكفله '));
    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-arrow-left btn__icon btn__icon--arrow';
    icon.setAttribute('aria-hidden', 'true');
    primary.append(icon);

    const secondary = document.createElement('a');
    secondary.className = 'btn btn--glass btn--lg';
    secondary.href = '#how';
    const play = document.createElement('i');
    play.className = 'fa-solid fa-circle-play';
    play.setAttribute('aria-hidden', 'true');
    secondary.append(play, document.createTextNode(' كيف تعمل الكفالة؟'));

    wrap.append(primary, secondary);
    return wrap;
  }

  /** الانتقال إلى شريحة بعينها. */
  function go(next) {
    index = (next + slides.length) % slides.length;

    slides.forEach((s, i) => s.classList.toggle('is-active', i === index));
    thumbs.forEach((t, i) => t.setAttribute('aria-current', String(i === index)));

    if (counter) {
      counter.replaceChildren(
        document.createTextNode(pad(index + 1)),
        Object.assign(document.createElement('small'), { textContent: ` / ${pad(slides.length)}` }),
      );
    }

    if (live) live.textContent = `الشريحة ${index + 1} من ${slides.length}: ${content[index].title}`;

    paint(index);
    if (playing) restartProgress();
  }

  /** إعادة تشغيل شريط التقدّم من الصفر. */
  function restartProgress() {
    hero.classList.remove('is-playing');
    void hero.offsetWidth;
    hero.classList.add('is-playing');
  }

  function play() {
    if (reduced || playing) return;
    playing = true;
    hero.style.setProperty('--hero-duration', `${DURATION}ms`);
    restartProgress();
    timer = setInterval(() => go(index + 1), DURATION);
    setPlayIcon(true);
  }

  function pause() {
    if (!playing) return;
    playing = false;
    clearInterval(timer);
    hero.classList.remove('is-playing');
    setPlayIcon(false);
  }

  function setPlayIcon(isPlaying) {
    if (!playBtn) return;
    playBtn.setAttribute('aria-label', isPlaying ? 'إيقاف العرض التلقائي' : 'تشغيل العرض التلقائي');
    playBtn.replaceChildren(
      Object.assign(document.createElement('i'), {
        className: `fa-solid ${isPlaying ? 'fa-pause' : 'fa-play'}`,
      }),
    );
  }

  /** نقلة يدوية: تُعيد ضبط المؤقّت حتى لا تقفز الشريحة بعدها مباشرة. */
  function step(delta) {
    const wasPlaying = playing;
    pause();
    go(index + delta);
    if (wasPlaying) play();
  }

  prevBtn?.addEventListener('click', () => step(-1));
  nextBtn?.addEventListener('click', () => step(1));

  playBtn?.addEventListener('click', () => (playing ? pause() : play()));

  thumbs.forEach((thumb, i) => {
    thumb.addEventListener('click', () => {
      const wasPlaying = playing;
      pause();
      go(i);
      if (wasPlaying) play();
    });
  });

  // الإيقاف أثناء القراءة أو التفاعل
  hero.addEventListener('pointerenter', pause);
  hero.addEventListener('pointerleave', () => { if (!reduced) play(); });
  hero.addEventListener('focusin', pause);

  // لا فائدة من تشغيل المؤقّت والتبويب مخفي
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) pause();
    else if (!reduced) play();
  });

  // الأسهم للتنقل حين يكون التركيز داخل الهيرو
  hero.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft')  { e.preventDefault(); step(1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); step(-1); }
  });

  go(0);
  if (reduced) {
    setPlayIcon(false);
    playBtn?.setAttribute('hidden', '');
  } else {
    play();
  }
}

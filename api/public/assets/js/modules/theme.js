/**
 * زر الوضع الليلي.
 *
 * ثلاث حالات لا اثنتان: «تلقائي» (لا شيء محفوظ، نتبع الجهاز) ثم اختيار
 * صريح فاتح أو داكن. الزر يقلب بين الفاتح والداكن فقط، لكن من لم يضغطه قط
 * يبقى على التلقائي — فيتبدّل الموقع مع جهازه مساءً بلا تدخّل.
 *
 * الحالة الأولى يطبّقها theme-init.js قبل الرسم، وهذه الوحدة تتولى الزر.
 */

import { $, $$ } from './utils.js';
import { t } from './i18n.js';

const KEY = 'kafala-theme';
const META_COLOR = { light: '#0f5c5c', dark: '#0d1617' };

const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

function stored() {
  try {
    const value = localStorage.getItem(KEY);
    return value === 'dark' || value === 'light' ? value : null;
  } catch {
    return null;
  }
}

function store(theme) {
  try {
    localStorage.setItem(KEY, theme);
  } catch {
    /* تخزين محجوب — يبقى الاختيار لهذه الجلسة فقط عبر data-theme */
  }
}

/** السمة الفعلية الآن: المحفوظة، وإلا تفضيل الجهاز. */
function effectiveTheme() {
  return document.documentElement.dataset.theme || (systemDark.matches ? 'dark' : 'light');
}

function syncUi(button) {
  const dark = effectiveTheme() === 'dark';

  // الاسم ثابت والحالة في aria-pressed: «تفعيل الوضع الفاتح، مضغوط» كانت تُقرأ متناقضة
  button.setAttribute('aria-pressed', String(dark));
  button.setAttribute('aria-label', t('الوضع الليلي'));
  button.title = dark ? t('الوضع الفاتح') : t('الوضع الليلي');

  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.content = META_COLOR[dark ? 'dark' : 'light'];

  // مكوّنات Bootstrap (سهم القائمة المنسدلة، وزر الإغلاق) تقرأ الوضع من
  // data-bs-theme لا من data-theme؛ ولا أثر له في الصفحات التي لم تنتقل إليه
  document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
}

/**
 * اختيارٌ صريح: يُطبَّق ويُحفظ، ويتحدّث كل زرٍّ يعرضه.
 *
 * ومع `origin` (الزر المضغوط) تنتشر السمة الجديدة دائرةً منه حتى تغطي
 * الشاشة (View Transition، والحركة في theme.css). بلا دعمٍ في المتصفح أو
 * لمن طلب تقليل الحركة تتبدّل فوراً كما كانت.
 */
export function setTheme(theme, origin = null) {
  const root = document.documentElement;
  const apply = () => {
    root.dataset.theme = theme;
    store(theme);
    syncAll();
  };

  const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!origin || still || typeof document.startViewTransition !== 'function' || effectiveTheme() === theme) {
    apply();
    return;
  }

  const box = origin.getBoundingClientRect();
  const x = box.left + box.width / 2;
  const y = box.top + box.height / 2;
  root.style.setProperty('--kf-vt-x', `${x}px`);
  root.style.setProperty('--kf-vt-y', `${y}px`);
  root.style.setProperty('--kf-vt-r', `${Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y))}px`);
  root.classList.add('kf-theme-vt');

  const vt = document.startViewTransition(apply);
  vt.finished.finally(() => root.classList.remove('kf-theme-vt'));
}

/** زر الترويسة (#themeToggle) وأزرار الاختيار في درج الجوال ([data-theme-set]). */
function syncAll() {
  const button = $('#themeToggle');
  if (button) syncUi(button);
  const dark = effectiveTheme() === 'dark';
  $$('[data-theme-set]').forEach((b) => b.setAttribute('aria-pressed', String((b.dataset.themeSet === 'dark') === dark)));
}

export function initThemeToggle() {
  // كل قالب يرسم زرّه (#themeToggle): الترويسة، وصفحات الحساب، والبوابة
  const button = $('#themeToggle');
  const choices = $$('[data-theme-set]');
  if (!button && !choices.length) return;

  syncAll();

  button?.addEventListener('click', () => setTheme(effectiveTheme() === 'dark' ? 'light' : 'dark', button));
  choices.forEach((b) => b.addEventListener('click', () => setTheme(b.dataset.themeSet, b)));

  // من لم يختر بعد يتبع جهازه حتى أثناء التصفح
  systemDark.addEventListener('change', () => {
    if (!stored()) syncAll();
  });
}

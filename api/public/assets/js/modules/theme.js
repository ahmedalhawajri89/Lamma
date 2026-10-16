/**
 * زر الوضع الليلي.
 *
 * ثلاث حالات لا اثنتان: «تلقائي» (لا شيء محفوظ، نتبع الجهاز) ثم اختيار
 * صريح فاتح أو داكن. الزر يقلب بين الفاتح والداكن فقط، لكن من لم يضغطه قط
 * يبقى على التلقائي — فيتبدّل الموقع مع جهازه مساءً بلا تدخّل.
 *
 * الحالة الأولى يطبّقها theme-init.js قبل الرسم، وهذه الوحدة تتولى الزر.
 */

import { $ } from './utils.js';

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
  button.setAttribute('aria-label', 'الوضع الليلي');
  button.title = dark ? 'الوضع الفاتح' : 'الوضع الليلي';

  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.content = META_COLOR[dark ? 'dark' : 'light'];

  // مكوّنات Bootstrap (سهم القائمة المنسدلة، وزر الإغلاق) تقرأ الوضع من
  // data-bs-theme لا من data-theme؛ ولا أثر له في الصفحات التي لم تنتقل إليه
  document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
}

/**
 * الصفحات التي لا هيدر لها: الزر يدخل خانة [data-theme-slot] إن وُجدت
 * (رأس البوابة)، وإلا يطفو في الزاوية (الدخول والشكر).
 */
function createButton() {
  const button = document.createElement('button');
  button.type = 'button';
  button.id = 'themeToggle';
  button.className = 'theme-toggle';
  button.innerHTML =
    '<i class="fa-solid fa-moon" aria-hidden="true"></i>' +
    '<i class="fa-solid fa-sun" aria-hidden="true"></i>';

  const slot = $('[data-theme-slot]');
  if (slot) {
    slot.prepend(button);
  } else {
    button.classList.add('theme-toggle--floating');
    document.body.append(button);
  }
  return button;
}

export function initThemeToggle() {
  const button = $('#themeToggle') || createButton();

  syncUi(button);

  button.addEventListener('click', () => {
    const next = effectiveTheme() === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    store(next);
    syncUi(button);
  });

  // من لم يختر بعد يتبع جهازه حتى أثناء التصفح
  systemDark.addEventListener('change', () => {
    if (!stored()) syncUi(button);
  });
}

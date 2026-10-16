/**
 * صفحة المعاينة (styleguide.html): زر الوضع الليلي نفسه، والكشف عند التمرير،
 * ومزامنة data-bs-theme مع سمة الموقع.
 *
 * Bootstrap يقرأ الوضع الليلي من data-bs-theme (سهم القائمة المنسدلة، وزر
 * الإغلاق)، والموقع يحفظه في data-theme أو يتبع الجهاز. فنعكس الفعلي منهما
 * على data-bs-theme كلما تغيّر أحدهما.
 */

import { initThemeToggle } from './modules/theme.js';
import { initReveal } from './modules/motion.js';

const root = document.documentElement;
const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

function syncBootstrapTheme() {
  const dark = (root.dataset.theme || (systemDark.matches ? 'dark' : 'light')) === 'dark';
  root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
}

syncBootstrapTheme();
systemDark.addEventListener('change', syncBootstrapTheme);
new MutationObserver(syncBootstrapTheme).observe(root, { attributes: true, attributeFilter: ['data-theme'] });

initThemeToggle();
initReveal();
root.dataset.ready = 'true';

/**
 * لوحة ألوان المخططات، بالهوية المعتمدة نفسها في الموقع (--chart-1..5).
 *
 * المجموعة نفسها متحقَّق منها في الموقع لعمى الألوان: أسوأ زوج ΔE 14.7.
 * والترتيب هنا يباعد بين الذهبيَّين فلا يتجاوران في مخطط واحد.
 *
 * الترتيب ثابت ولا يُدار: الشريحة السادسة لا تولّد لوناً جديداً بل تندمج
 * في «أخرى»، لأن اللون يتبع الكيان لا ترتيبه.
 */
export const CATEGORICAL = ['#0f5c5c', '#e0a63b', '#c9694b', '#6366d9', '#edc56a']

/** لون واحد يتدرّج للمقادير، لا قوس قزح. */
export const SEQUENTIAL = ['#d3ebe8', '#a8d6d0', '#74bbb2', '#1e8a7a', '#0f5c5c']

/** ألوان الحالة محجوزة، لا تُستعمل كسلسلة رابعة. */
export const STATUS = {
  good: '#1f8048',
  warning: '#c48a25',
  serious: '#b5543a',
  critical: '#be123c',
}

export const INK = {
  primary: '#1f2a2e',
  secondary: '#3a474b',
  muted: '#5f6b6f',
  grid: '#e4e0d6',
  surface: '#ffffff',
}

export const color = (i) => CATEGORICAL[i % CATEGORICAL.length]

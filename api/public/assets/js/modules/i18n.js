/**
 * لغة الصفحة في JS.
 *
 * الخادم يكتب في <head> كتلة بيانات (#kf-i18n): اللغة واتجاهها، ونصوص JS
 * المترجمة. كتلة JSON لا سكربت، فلا تخالف CSP. والمصدر هو النص العربي نفسه:
 * t('اكفل يتيماً') يعطيه كما هو في العربية، وترجمته من lang/en/js.php في
 * الإنجليزية. ما لم يُترجم بعد يبقى عربياً بدل أن يختفي.
 */

const DATA = (() => {
  try {
    return JSON.parse(document.getElementById('kf-i18n')?.textContent || '{}');
  } catch {
    return {};
  }
})();

export const locale = DATA.locale || 'ar';
export const defaultLocale = DATA.default || 'ar';
export const dir = DATA.dir || 'rtl';
/** لغة Intl: العربية بأرقام لاتينية كما يكتبها الموقع */
export const intl = DATA.intl || 'ar-u-nu-latn';
export const isDefault = locale === defaultLocale;

const STRINGS = DATA.strings || {};

/** نصٌّ بلغة الصفحة، مع {متغيّرات}. */
export function t(text, vars) {
  const out = STRINGS[text] ?? text;
  return vars ? out.replace(/\{(\w+)\}/g, (m, k) => (k in vars ? vars[k] : m)) : out;
}

/**
 * صيغة العدد بقواعد كل لغة: العربية ست صيغ (صفر، واحد، اثنان، قليل، كثير،
 * غيرها) والإنجليزية اثنتان. والصيغ تُكتب في موضعها للّغتين:
 *   plural(n, { ar: { one: 'طفل', two: 'طفلان', few: '{n} أطفال', other: '{n} طفلاً' },
 *               en: { one: 'one child', other: '{n} children' } })
 */
const rules = new Intl.PluralRules(locale);
export function plural(n, forms) {
  const set = forms[locale] ?? forms[defaultLocale] ?? {};
  const text = set[rules.select(n)] ?? set.other ?? '';
  return text.replace(/\{n\}/g, String(n));
}

/** رابطٌ داخلي بلغة الصفحة: url('/orphans') يعطي «/en/orphans» في الإنجليزية. */
export function url(path) {
  const clean = `/${String(path).replace(/^\/+/, '')}`;
  if (isDefault) return clean;
  return clean === '/' ? `/${locale}` : `/${locale}${clean}`;
}

/**
 * أدوات العرض.
 *
 * الأرقام لاتينية عمداً حتى داخل النص العربي: الأرقام الهندية في جدول مالي
 * تُبطئ القراءة ولا تُحاذى رأسياً، ولا تُنسخ إلى Excel كأرقام.
 */
/** ما يُعرض مكان قيمة غائبة: كلمة هادئة لا شرطة. */
export const EMPTY = 'غير محدد'

const nf = new Intl.NumberFormat('en-US')
const cf = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
// ‎-u-nu-latn: الشهر بالعربية والأرقام لاتينية، كبقية أرقام اللوحة
const df = new Intl.DateTimeFormat('ar-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
const dtf = new Intl.DateTimeFormat('ar-u-nu-latn', {
  day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
})

export const num = (v) => (v === null || v === undefined ? EMPTY : nf.format(v))
export const money = (v, c = '$') => (v === null || v === undefined ? EMPTY : `${c}${cf.format(v)}`)

export const date = (v) => (v ? df.format(new Date(v)) : EMPTY)
export const dateTime = (v) => (v ? dtf.format(new Date(v)) : EMPTY)

/** العمر بصيغته العربية الصحيحة: سنة واحدة، سنتان، 3 سنوات، 11 سنة. */
export function years(n) {
  if (n === null || n === undefined) return EMPTY
  if (n === 1) return 'سنة واحدة'
  if (n === 2) return 'سنتان'
  if (n >= 3 && n <= 10) return `${n} سنوات`
  return `${n} سنة`
}

/** «قبل 3 أيام» أوضح من تاريخ مطلق في سجل نشاط. */
export function relative(value) {
  if (!value) return EMPTY

  const diff = (new Date(value) - Date.now()) / 1000
  const rtf = new Intl.RelativeTimeFormat('ar-u-nu-latn', { numeric: 'auto' })
  const units = [
    ['year', 31536000], ['month', 2592000], ['day', 86400],
    ['hour', 3600], ['minute', 60],
  ]

  for (const [unit, seconds] of units) {
    if (Math.abs(diff) >= seconds) {
      return rtf.format(Math.round(diff / seconds), unit)
    }
  }

  return 'الآن'
}

export function percent(v) {
  return v === null || v === undefined ? EMPTY : `${Math.round(v)}%`
}

/** لون شريط التمويل: أحمر تحت الربع، عنبر تحت الكامل، زيتوني عند الاكتمال. */
export function fundingTone(p) {
  if (p >= 100) return 'olive'
  if (p >= 50) return 'amber'
  if (p >= 25) return 'clay'
  return 'rose'
}

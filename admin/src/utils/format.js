/**
 * أدوات العرض.
 *
 * الأرقام لاتينية عمداً حتى داخل النص العربي: الأرقام الهندية في جدول مالي
 * تُبطئ القراءة ولا تُحاذى رأسياً، ولا تُنسخ إلى Excel كأرقام.
 */
const nf = new Intl.NumberFormat('en-US')
const cf = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const df = new Intl.DateTimeFormat('ar', { day: 'numeric', month: 'long', year: 'numeric' })
const dtf = new Intl.DateTimeFormat('ar', {
  day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
})

export const num = (v) => (v === null || v === undefined ? '—' : nf.format(v))
export const money = (v, c = '$') => (v === null || v === undefined ? '—' : `${c}${cf.format(v)}`)

export const date = (v) => (v ? df.format(new Date(v)) : '—')
export const dateTime = (v) => (v ? dtf.format(new Date(v)) : '—')

/** «قبل ٣ أيام» — أوضح من تاريخ مطلق في سجل نشاط. */
export function relative(value) {
  if (!value) return '—'

  const diff = (new Date(value) - Date.now()) / 1000
  const rtf = new Intl.RelativeTimeFormat('ar', { numeric: 'auto' })
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
  return v === null || v === undefined ? '—' : `${Math.round(v)}%`
}

/** لون شريط التمويل: أحمر تحت الربع، عنبر تحت الكامل، زيتوني عند الاكتمال. */
export function fundingTone(p) {
  if (p >= 100) return 'olive'
  if (p >= 50) return 'amber'
  if (p >= 25) return 'clay'
  return 'rose'
}

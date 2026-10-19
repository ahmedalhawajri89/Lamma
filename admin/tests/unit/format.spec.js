import { describe, it, expect } from 'vitest'
import { num, money, percent, fundingTone, relative, date, EMPTY } from '@/utils/format'

describe('تنسيق الأرقام', () => {
  it('يعرض شرطة بدل صفر زائف عند غياب القيمة', () => {
    // الفرق مهم: «—» تعني «لا بيانات»، و«$0.00» تعني «صفر دولار».
    // خلطهما في تقرير مالي يجعل حقلاً ناقصاً يبدو رصيداً صفرياً.
    expect(num(null)).toBe(EMPTY)
    expect(money(null)).toBe(EMPTY)
    expect(money(0)).toBe('$0.00')
    expect(num(0)).toBe('0')
  })

  it('يبقي الأرقام لاتينية داخل النص العربي', () => {
    expect(num(3280)).toBe('3,280')
    expect(money(65)).toBe('$65.00')
    expect(money(1234.5)).toBe('$1,234.50')
  })

  it('يقرّب النسب ولا يكسرها', () => {
    expect(percent(62.4)).toBe('62%')
    expect(percent(100)).toBe('100%')
    expect(percent(null)).toBe(EMPTY)
  })
})

describe('لون شريط التمويل', () => {
  it('يتدرّج من الأحمر إلى الزيتوني مع اكتمال التمويل', () => {
    expect(fundingTone(0)).toBe('rose')
    expect(fundingTone(24)).toBe('rose')
    expect(fundingTone(25)).toBe('clay')
    expect(fundingTone(49)).toBe('clay')
    expect(fundingTone(50)).toBe('amber')
    expect(fundingTone(99)).toBe('amber')
    expect(fundingTone(100)).toBe('olive')
  })
})

describe('التواريخ', () => {
  it('يتحمّل القيم الفارغة بلا استثناء', () => {
    expect(date(null)).toBe(EMPTY)
    expect(relative(null)).toBe(EMPTY)
  })

  it('يصف الماضي بالصيغة الاصطلاحية العربية', () => {
    // numeric:'auto' يعطي «أمس» و«أول أمس» بدل «قبل يوم» و«قبل يومين» —
    // وهو ما يكتبه عربيٌّ فعلاً، ولذلك اخترناه في سجل النشاط.
    const ago = (days) => new Date(Date.now() - days * 86400 * 1000).toISOString()

    expect(relative(ago(1))).toBe('أمس')
    expect(relative(ago(5))).toContain('أيام')
    expect(relative(ago(90))).toContain('أشهر')
  })
})

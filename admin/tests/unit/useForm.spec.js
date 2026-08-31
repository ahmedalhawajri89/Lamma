import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useForm } from '@/composables/useForm'
import { useUiStore } from '@/stores/ui'

/**
 * `useForm` أساس كل نموذج في اللوحة.
 *
 * فعيبٌ فيه لا يظهر في شاشة واحدة بل في كل شاشة، وبعد أن تُبنى فوقه
 * عشرة نماذج يصير إصلاحه عشرة إصلاحات.
 */
describe('useForm', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('يعرض أخطاء الخادم مربوطةً بحقولها', async () => {
    const submitter = vi.fn().mockRejectedValue({
      fieldErrors: { first_name: ['الاسم الأول مطلوب.'] },
      userMessage: 'تعذّر قبول البيانات المُرسلة.',
    })

    const form = useForm({ first_name: '' }, submitter)

    expect(await form.submit()).toBeNull()
    expect(form.errors.value.first_name[0]).toBe('الاسم الأول مطلوب.')
  })

  /**
   * إشعارٌ فوق أخطاء حقول ظاهرة ضجيج: المستخدم يقرأ الرسالة مرتين ولا
   * تدلّه الثانية على شيء. أما ما لا حقل له فبلا الإشعار يبقى الزرّ صامتاً.
   */
  it('يُشعر فقط حين لا حقل يحمل الخطأ', async () => {
    const ui = useUiStore()

    const withField = useForm({ a: '' }, vi.fn().mockRejectedValue({
      fieldErrors: { a: ['خطأ'] }, userMessage: 'رسالة عامة',
    }))
    await withField.submit()
    expect(ui.toasts).toHaveLength(0)

    const withoutField = useForm({ a: '' }, vi.fn().mockRejectedValue({
      fieldErrors: {}, userMessage: 'لا صلاحية.',
    }))
    await withoutField.submit()
    expect(ui.toasts.map((t) => t.message)).toEqual(['لا صلاحية.'])
  })

  it('يمسح خطأ الحقل عند تعديله', async () => {
    const form = useForm({ email: '' }, vi.fn().mockRejectedValue({
      fieldErrors: { email: ['صيغة البريد غير صحيحة.'] },
    }))

    await form.submit()
    expect(form.errors.value.email).toBeDefined()

    // خطأ يبقى معلّقاً بعد التصحيح يجعل المستخدم يظنّ أن تصحيحه لم يُقبل
    form.touch('email')
    expect(form.errors.value.email).toBeUndefined()
  })

  /**
   * نقرتان سريعتان على «حفظ» تُنشئان سجلّين — وهذا هنا يعني يتيماً
   * مكرّراً أو دفعةً مضاعفة.
   */
  it('يمنع الإرسال المزدوج', async () => {
    let resolve
    const submitter = vi.fn(() => new Promise((r) => { resolve = r }))
    const form = useForm({ a: '1' }, submitter)

    const first = form.submit()
    const second = form.submit()

    expect(await second).toBeNull()
    expect(submitter).toHaveBeenCalledTimes(1)

    resolve({ data: { data: { id: 7 } } })
    expect(await first).toEqual({ id: 7 })
  })

  it('يتتبّع التعديل ويعيد الحالة الأصلية', () => {
    const form = useForm({ name: 'أصل', tags: ['أ'] }, vi.fn())

    expect(form.dirty.value).toBe(false)

    form.values.name = 'معدَّل'
    form.values.tags.push('ب')
    form.touch('name')
    expect(form.dirty.value).toBe(true)

    form.reset()
    expect(form.values.name).toBe('أصل')

    // النسخ العميق: نسخة سطحية تجعل reset يعيد إشارةً إلى ما عُدِّل فعلاً
    expect(form.values.tags).toEqual(['أ'])
    expect(form.dirty.value).toBe(false)
  })

  it('يُنظّف الحالة بعد نجاح الإرسال', async () => {
    const ui = useUiStore()
    const form = useForm({ a: '' }, vi.fn().mockResolvedValue({
      data: { message: 'أُنشئ الملف.', data: { id: 3 } },
    }))

    form.values.a = 'x'
    form.touch('a')

    expect(await form.submit()).toEqual({ id: 3 })
    expect(form.dirty.value).toBe(false)
    expect(ui.toasts[0].message).toBe('أُنشئ الملف.')
  })
})

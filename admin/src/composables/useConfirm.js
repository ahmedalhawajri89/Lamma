import { reactive } from 'vue'

/**
 * نافذة تأكيد واحدة للّوحة كلها، بدل `confirm()` و`prompt()` الأصليتين.
 *
 * تلك نوافذ المتصفح بلغة نظامه واتجاهه، بلا تنسيق ولا حقل سبب يُرى كاملاً،
 * وتوقف الصفحة كلها. هذه تُفتح بالطريقة نفسها تقريباً:
 *
 *   const r = await confirmDialog({ title, message, reason: 'required', danger: true })
 *   if (!r) return            // أُلغيت
 *   r.reason                  // السبب المكتوب إن طُلب
 *
 * الحالة مشتركة، ويعرضها `ConfirmDialog.vue` المركَّب مرة واحدة في `App.vue`.
 */
export const confirmState = reactive({
  open: false,
  title: '',
  message: '',
  /** null بلا حقل، 'optional' أو 'required' بحقل سبب */
  reason: null,
  reasonLabel: 'السبب',
  /** حدود السبب كما في الخادم: كان يُقبل هنا ثم يردّه الخادم بعد أن تُغلق النافذة ويضيع ما كُتب */
  minLength: 5,
  maxLength: 255,
  confirmLabel: 'تأكيد',
  danger: false,
  resolve: null,
})

export function confirmDialog(options = {}) {
  // نافذة مفتوحة تُغلق كأنها أُلغيت قبل فتح غيرها
  confirmState.resolve?.(null)

  return new Promise((resolve) => {
    Object.assign(confirmState, {
      title: '',
      message: '',
      reason: null,
      reasonLabel: 'السبب',
      minLength: 5,
      maxLength: 255,
      confirmLabel: 'تأكيد',
      danger: false,
      ...options,
      open: true,
      resolve,
    })
  })
}

/** تُستدعى من النافذة نفسها. */
export function settleConfirm(result) {
  const resolve = confirmState.resolve
  confirmState.open = false
  confirmState.resolve = null
  resolve?.(result)
}

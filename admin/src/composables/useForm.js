import { computed, reactive, ref } from 'vue'
import { useUiStore } from '@/stores/ui'

/**
 * حالة نموذج واحد: القيم، وأخطاء الحقول، والإرسال.
 *
 * أربعة أشياء تتكرر في كل نموذج، فتعيش هنا مرة واحدة:
 *
 *  1. **أخطاء الحقول من الخادم.** لارافل يردّ `422` بخريطة
 *     `{ field: [messages] }`، والمكان الصحيح لعرضها تحت الحقل نفسه لا في
 *     إشعار عائم يختفي قبل أن يجد المستخدم الحقل المقصود.
 *
 *  2. **مسح خطأ الحقل عند تعديله.** خطأ يبقى معلّقاً بعد تصحيح الحقل يجعل
 *     المستخدم يظنّ أن تصحيحه لم يُقبل.
 *
 *  3. **منع الإرسال المزدوج.** نقرتان سريعتان على «حفظ» تُنشئان سجلّين —
 *     وهذا في هذا المشروع يعني يتيماً مكرّراً أو دفعةً مضاعفة.
 *
 *  4. **تتبّع التعديل (`dirty`).** يسمح للنافذة أن تسأل قبل الإغلاق بدل أن
 *     تبتلع نصف ساعة من الإدخال بنقرة على الخلفية.
 *
 * ولا يعرف هذا الملف شيئاً عن الحقول: يستقبل قيماً ابتدائية ودالة إرسال،
 * فيصلح لنموذج اليتيم كما يصلح لنموذج الأسرة.
 */
export function useForm(initial, submitter, options = {}) {
  const ui = useUiStore()

  const values = reactive(structuredClone(initial))
  const errors = ref({})
  const submitting = ref(false)
  const dirty = ref(false)

  /**
   * `structuredClone` لا للنسخ السطحي: القيم الابتدائية قد تحوي كائنات
   * متداخلة، ونسخة سطحية تجعل `reset()` يعيد إشارةً إلى ما عُدِّل فعلاً.
   */
  const snapshot = structuredClone(initial)

  const hasErrors = computed(() => Object.keys(errors.value).length > 0)

  /** يُستدعى من الحقل عند كل تعديل. */
  function touch(field) {
    dirty.value = true
    if (errors.value[field]) {
      const next = { ...errors.value }
      delete next[field]
      errors.value = next
    }
  }

  function setValues(next) {
    Object.assign(values, structuredClone(next))
    Object.assign(snapshot, structuredClone(next))
    errors.value = {}
    dirty.value = false
  }

  function reset() {
    Object.keys(values).forEach((k) => delete values[k])
    Object.assign(values, structuredClone(snapshot))
    errors.value = {}
    dirty.value = false
  }

  /**
   * يُرجع السجلّ عند النجاح، و`null` عند الفشل.
   *
   * `null` لا استثناء: النداء يقع في معالج نقرة، والاستثناء غير الملتقَط هناك
   * يصل إلى الكونسول وحده — فيبدو الزرّ كأنه لا يعمل.
   */
  async function submit() {
    if (submitting.value) return null

    submitting.value = true
    errors.value = {}

    try {
      const { data } = await submitter(values)

      dirty.value = false
      if (options.successMessage !== false) {
        ui.success(data?.message ?? options.successMessage ?? 'حُفظ.')
      }

      return data?.data ?? data ?? true
    } catch (e) {
      errors.value = e.fieldErrors ?? {}

      /**
       * الإشعار للأخطاء التي لا حقل لها فقط.
       *
       * إشعارٌ فوق أخطاء حقول ظاهرة ضجيج: المستخدم يرى الرسالة مرتين ولا
       * تدلّه الثانية على شيء. أما `403` أو انقطاع الشبكة فلا حقل لهما،
       * وبلا الإشعار يبقى الزرّ صامتاً.
       */
      if (!hasErrors.value) {
        ui.error(e.userMessage ?? 'تعذّر الحفظ.')
      }

      return null
    } finally {
      submitting.value = false
    }
  }

  /**
   * الحقول التي تغيّرت عن القيم المحمَّلة وحدها.
   *
   * لنماذج التعديل التي لا يجوز أن ترسل ما لم يلمسه المستخدم: كانت الكفالة
   * ترسل حالتها مع كل حفظ فتتفعّل بلا دفعة، والإعدادات ترسل كل مفاتيحها
   * فتمسحها إن فشل تحميلها.
   */
  function changed() {
    return Object.fromEntries(
      Object.entries(values).filter(([k, v]) => JSON.stringify(v) !== JSON.stringify(snapshot[k])),
    )
  }

  return { values, errors, submitting, dirty, hasErrors, touch, setValues, reset, submit, changed }
}

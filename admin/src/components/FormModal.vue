<script setup>
/**
 * نافذة نموذج.
 *
 * ليست `<div>` بخلفية معتمة: النافذة التي لا تحبس التركيز تترك قارئ الشاشة
 * ولوحة المفاتيح يتجوّلان في الصفحة خلفها، فيملأ المستخدم حقولاً لا يراها.
 *
 * أربعة التزامات يفي بها هذا المكوّن:
 *  1. **حبس التركيز** داخلها، مع دورانه عند طرفيها.
 *  2. **إعادة التركيز** إلى العنصر الذي فتحها عند الإغلاق — وإلا عاد إلى
 *     أعلى المستند فضاع مكان المستخدم في جدول طويل.
 *  3. **قفل تمرير الصفحة** خلفها.
 *  4. **تأكيد الإغلاق إن كان النموذج معدَّلاً** — نقرة على الخلفية لا تبتلع
 *     نصف ساعة من الإدخال.
 */
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import BaseIcon from './BaseIcon.vue'
import { confirmDialog, confirmState } from '@/composables/useConfirm'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  dirty: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  wide: { type: Boolean, default: false },

  /** نافذة التأكيد نفسها (ConfirmDialog): تملك Escape وTab حين تُفتح فوق غيرها */
  isConfirm: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'submit'])

const panel = ref(null)

// معرّف ثابت للعنوان: كان يُبنى من العنوان نفسه، والعناوين فيها مسافات،
// و`aria-labelledby` قائمة معرّفات مفصولة بالمسافات، فلا تُسمّى النافذة
const titleId = useId()
let opener = null

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

function focusables() {
  return Array.from(panel.value?.querySelectorAll(FOCUSABLE) ?? []).filter((el) => el.offsetParent !== null)
}

function onKeydown(event) {
  // نافذة تأكيد مفتوحة فوق هذه تملك لوحة المفاتيح وحدها
  if (confirmState.open && !props.isConfirm) return

  if (event.key === 'Escape') {
    event.preventDefault()
    attemptClose()
    return
  }

  if (event.key !== 'Tab') return

  const items = focusables()
  if (!items.length) return

  const first = items[0]
  const last = items[items.length - 1]
  const active = document.activeElement

  // الدوران عند الطرفين — بلا هذا يقفز التركيز خلف النافذة عند آخر عنصر
  if (event.shiftKey && (active === first || !panel.value.contains(active))) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

/**
 * التأكيد بنافذة اللوحة لا بـ`window.confirm`: نافذة المتصفح بلغة نظامه
 * واتجاهه، وتوقف الصفحة كلها، وكانت الوحيدة الباقية بعد استبدال البقية.
 */
async function attemptClose() {
  if (props.busy) return

  if (props.dirty) {
    const ok = await confirmDialog({
      title: 'تعديلات غير محفوظة',
      message: 'ما كتبته في هذه النافذة لم يُحفظ بعد، ويضيع إن أغلقتها.',
      confirmLabel: 'أغلق دون حفظ',
      danger: true,
    })
    if (!ok) return
  }

  emit('close')
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      opener = document.activeElement
      document.body.style.overflow = 'hidden'
      document.addEventListener('keydown', onKeydown)

      await nextTick()

      // أول حقل لا زرّ الإغلاق: المستخدم فتح النافذة ليكتب
      const target = panel.value?.querySelector('[data-autofocus]') ?? focusables()[0]
      target?.focus()
    } else {
      document.body.style.overflow = ''
      document.removeEventListener('keydown', onKeydown)
      opener?.focus?.()
      opener = null
    }
  },
)

onBeforeUnmount(() => {
  document.body.style.overflow = ''
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-8"
      >
        <div class="fixed inset-0 bg-sand-900/40 backdrop-blur-[2px]" @click="attemptClose" />

        <div
          ref="panel"
          class="card relative w-full my-auto"
          :class="wide ? 'max-w-3xl' : 'max-w-xl'"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
        >
          <header class="flex items-start gap-3 p-5 border-b border-sand-100">
            <div class="flex-1 min-w-0">
              <h2 :id="titleId" class="font-semibold">{{ title }}</h2>
              <p v-if="subtitle" class="text-sm text-sand-500 mt-0.5">{{ subtitle }}</p>
            </div>

            <button
              class="btn btn-ghost -m-1.5 p-1.5" type="button"
              :disabled="busy" aria-label="إغلاق" @click="attemptClose"
            >
              <BaseIcon name="close" :size="18" />
            </button>
          </header>

          <!-- `novalidate`: التحقق عندنا بالعربية، وفقاعات المتصفح بلغة نظامه -->
          <form novalidate @submit.prevent="emit('submit')">
            <div class="p-5 space-y-4">
              <slot />
            </div>

            <footer class="flex items-center justify-end gap-2 p-5 pt-0">
              <!-- `close` يمرّ بفحص التعديلات غير المحفوظة؛ زرّ إلغاء يُطلق `close` مباشرة يتجاوزه -->
              <slot name="actions" :close="attemptClose">
                <button class="btn btn-outline" type="button" :disabled="busy" @click="attemptClose">
                  إلغاء
                </button>
                <button class="btn btn-primary" type="submit" :disabled="busy">
                  <BaseIcon v-if="busy" name="loader" :size="15" class="animate-spin" />
                  {{ busy ? 'جارٍ الحفظ…' : 'حفظ' }}
                </button>
              </slot>
            </footer>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active { transition: opacity 0.15s ease; }
.modal-enter-from,
.modal-leave-to { opacity: 0; }

/* الحركة تُلغى لمن طلب تقليلها — لا تُبطّأ */
@media (prefers-reduced-motion: reduce) {
  .modal-enter-active,
  .modal-leave-active { transition: none; }
}
</style>

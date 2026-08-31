<script setup>
/**
 * حقل واحد: تسمية + مدخل + خطأ.
 *
 * غرضه أن الربط بين الثلاثة يقع مرة واحدة لا في كل نموذج. الحقل الذي تُكتب
 * تسميته `<span>` بلا `for`، أو خطؤه في `<p>` بلا `aria-describedby`، يبدو
 * صحيحاً على الشاشة ولا يصل قارئ الشاشة منه شيء.
 *
 * والخطأ يُعلن بـ`role="alert"`: يظهر بعد الإرسال، ومن لا يرى الشاشة يحتاج
 * أن يُقال له إن شيئاً ظهر.
 */
import { computed, useId } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  modelValue: { type: [String, Number, Boolean, null], default: '' },

  /** رسائل الخادم لهذا الحقل — مصفوفة كما تردّها لارافل. */
  error: { type: [Array, String], default: null },

  type: { type: String, default: 'text' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },

  /** خيارات `select` — `[{ value, label }]` أو نصوص. */
  options: { type: Array, default: null },

  rows: { type: Number, default: 4 },
  placeholder: { type: String, default: '' },
  min: { type: [String, Number], default: null },
  max: { type: [String, Number], default: null },
  step: { type: [String, Number], default: null },
  autofocus: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const id = useId()
const errorId = computed(() => `${id}-error`)
const hintId = computed(() => `${id}-hint`)

const message = computed(() => (Array.isArray(props.error) ? props.error[0] : props.error) || '')

const describedBy = computed(() => {
  const ids = []
  if (props.hint) ids.push(hintId.value)
  if (message.value) ids.push(errorId.value)
  return ids.join(' ') || undefined
})

const control = computed(() => {
  if (props.options) return 'select'
  if (props.type === 'textarea') return 'textarea'
  return 'input'
})

const normalized = computed(() =>
  (props.options ?? []).map((o) => (typeof o === 'object' ? o : { value: o, label: o })),
)

function onInput(event) {
  const el = event.target

  if (props.type === 'checkbox') {
    emit('update:modelValue', el.checked)
    return
  }

  /**
   * الرقم يبقى رقماً.
   *
   * `<input type="number">` يُرجع نصاً دائماً، وإرساله نصاً يجعل `min:5` في
   * لارافل يقارن نصوصاً — فيمرّ `"9"` ويسقط `"10"`. والحقل الفارغ `null` لا
   * `0`: الصفر قيمة يقصدها المستخدم، والفراغ غيابها.
   */
  if (props.type === 'number') {
    emit('update:modelValue', el.value === '' ? null : Number(el.value))
    return
  }

  emit('update:modelValue', el.value)
}
</script>

<template>
  <div>
    <!-- خانة الاختيار: التسمية إلى جانبها لا فوقها -->
    <label v-if="type === 'checkbox'" class="flex items-center gap-2 cursor-pointer">
      <input
        :id="id" type="checkbox" :checked="Boolean(modelValue)" :disabled="disabled"
        class="w-4 h-4 accent-clay-600 cursor-pointer"
        :aria-describedby="describedBy" @change="onInput"
      >
      <span class="text-sm">{{ label }}</span>
    </label>

    <template v-else>
      <label class="block text-sm font-medium mb-1.5" :for="id">
        {{ label }}
        <span v-if="required" class="text-clay-600" aria-hidden="true">*</span>
      </label>

      <select
        v-if="control === 'select'" :id="id" class="input"
        :value="modelValue" :disabled="disabled" :required="required"
        :aria-invalid="message ? 'true' : undefined" :aria-describedby="describedBy"
        :data-autofocus="autofocus || undefined" @change="onInput"
      >
        <option value="">{{ placeholder || '—' }}</option>
        <option v-for="o in normalized" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>

      <textarea
        v-else-if="control === 'textarea'" :id="id" class="input" :rows="rows"
        :value="modelValue" :disabled="disabled" :required="required" :placeholder="placeholder"
        :aria-invalid="message ? 'true' : undefined" :aria-describedby="describedBy"
        :data-autofocus="autofocus || undefined" @input="onInput"
      />

      <input
        v-else :id="id" class="input" :type="type"
        :value="modelValue" :disabled="disabled" :required="required" :placeholder="placeholder"
        :min="min" :max="max" :step="step"
        :aria-invalid="message ? 'true' : undefined" :aria-describedby="describedBy"
        :data-autofocus="autofocus || undefined" @input="onInput"
      >
    </template>

    <p v-if="hint && !message" :id="hintId" class="text-xs text-sand-500 mt-1">{{ hint }}</p>
    <p v-if="message" :id="errorId" class="text-xs text-clay-700 mt-1" role="alert">{{ message }}</p>
  </div>
</template>

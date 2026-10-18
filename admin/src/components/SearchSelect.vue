<script setup>
/**
 * اختيار بالبحث: يكتب المستخدم فتأتي النتائج من الخادم.
 *
 * بديل القوائم المنسدلة التي كانت تُملأ بأول 200 سجل: الأسرة في نموذج
 * اليتيم، والطفل في الزيارة والصرف والتقرير، والكفالة في الدفعة اليدوية.
 * ما بعد المئتين كان يختفي بصمت، والأقدم (الأحوج إلى زيارة أو دفعة) أولها.
 *
 * نمط combobox كما في WAI-ARIA: الأسهم تتنقل، Enter يختار، Escape يغلق،
 * والقائمة تُعلن لقارئ الشاشة.
 */
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue'
import BaseIcon from './BaseIcon.vue'

const props = defineProps({
  label: { type: String, required: true },
  modelValue: { type: [String, Number, null], default: '' },

  /** `(q) => Promise<[{ value, label, hint? }]>` */
  search: { type: Function, required: true },

  /** تسمية القيمة الحالية حين يُفتح النموذج للتعديل */
  initialLabel: { type: String, default: '' },

  error: { type: [Array, String], default: null },
  hint: { type: String, default: '' },
  placeholder: { type: String, default: 'اكتب للبحث…' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  autofocus: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'picked'])

const id = useId()
const listId = `${id}-list`
const message = computed(() => (Array.isArray(props.error) ? props.error[0] : props.error) || '')

const text = ref(props.initialLabel)
const options = ref([])
const open = ref(false)
const active = ref(-1)
const loading = ref(false)
let timer = null
let sequence = 0

watch(() => props.initialLabel, (v) => { text.value = v ?? '' })

// تفريغ القيمة من الخارج (نموذج جديد) يفرّغ النص
watch(() => props.modelValue, (v) => {
  if (v === '' || v === null || v === undefined) text.value = props.initialLabel && v ? props.initialLabel : ''
})

async function run(q) {
  const mine = ++sequence
  loading.value = true
  try {
    const result = await props.search(q)
    if (mine !== sequence) return
    options.value = result ?? []
    active.value = options.value.length ? 0 : -1
  } catch {
    if (mine === sequence) options.value = []
  } finally {
    if (mine === sequence) loading.value = false
  }
}

function onInput(event) {
  text.value = event.target.value
  open.value = true

  // ما كُتب لم يعد القيمة المختارة: لا يُرسَل اختيارٌ قديم بنصّ جديد
  if (props.modelValue !== '' && props.modelValue !== null) emit('update:modelValue', '')

  clearTimeout(timer)
  timer = setTimeout(() => run(text.value.trim()), 250)
}

const hasValue = () => props.modelValue !== '' && props.modelValue !== null && props.modelValue !== undefined

function onFocus() {
  if (props.disabled) return
  // حقلٌ مختارةٌ قيمته (طفلٌ مختار سلفاً) لا تُفتح قائمته بتركيز النموذج حين
  // يُفتح: كان يبحث باسمه الكامل «دعاء · KFL-2012» فيعرض «لا نتائج» فوق النموذج
  if (hasValue()) return
  open.value = true
  if (!options.value.length) run(text.value.trim())
}

/** النقر على حقلٍ مختارٍ لتغييره: القائمة كاملة، لا بحثٌ بالاسم المختار */
function onClick() {
  if (props.disabled || open.value || !hasValue()) return
  open.value = true
  run('')
}

function pick(option) {
  text.value = option.label
  open.value = false
  emit('update:modelValue', option.value)
  emit('picked', option)
}

function onKeydown(event) {
  if (!open.value && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
    if (hasValue()) { open.value = true; run('') } else onFocus()
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    active.value = Math.min(options.value.length - 1, active.value + 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    active.value = Math.max(0, active.value - 1)
  } else if (event.key === 'Enter' && open.value && options.value[active.value]) {
    event.preventDefault()
    pick(options.value[active.value])
  } else if (event.key === 'Escape' && open.value) {
    // يغلق القائمة وحدها، لا النافذة التي تحويها
    event.stopPropagation()
    open.value = false
  }
}

function onBlur() {
  // مهلة قصيرة كي يصل النقر على الخيار قبل الإغلاق
  setTimeout(() => {
    open.value = false
    // نصٌّ بلا اختيار يُعاد إلى آخر اختيار، فلا يبقى في الحقل ما لن يُرسَل
    if ((props.modelValue === '' || props.modelValue === null) && !options.value.some((o) => o.label === text.value)) {
      text.value = ''
    }
  }, 150)
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="relative">
    <label class="block text-sm font-medium mb-1.5" :for="id">
      {{ label }}
      <span v-if="required" class="text-clay-600" aria-hidden="true">*</span>
    </label>

    <div class="relative">
      <input
        :id="id" class="input pe-9" type="text" autocomplete="off"
        role="combobox" :aria-expanded="open" :aria-controls="listId" aria-autocomplete="list"
        :aria-activedescendant="open && active >= 0 ? `${listId}-${active}` : undefined"
        :aria-invalid="message ? 'true' : undefined"
        :aria-describedby="message ? `${id}-error` : hint ? `${id}-hint` : undefined"
        :value="text" :placeholder="placeholder" :disabled="disabled" :required="required"
        :data-autofocus="autofocus || undefined"
        @input="onInput" @focus="onFocus" @click="onClick" @keydown="onKeydown" @blur="onBlur"
      >
      <span class="absolute inset-y-0 end-3 grid place-items-center text-sand-400 pointer-events-none">
        <BaseIcon :name="loading ? 'loader' : 'search'" :size="15" :class="{ 'animate-spin': loading }" />
      </span>
    </div>

    <ul
      v-show="open && !disabled" :id="listId" role="listbox"
      class="absolute z-20 inset-x-0 mt-1 max-h-64 overflow-y-auto rounded-xl border border-sand-200 bg-sand-0 shadow-lg py-1"
    >
      <li
        v-for="(o, i) in options" :id="`${listId}-${i}`" :key="o.value"
        role="option" :aria-selected="i === active"
        class="px-3 py-2 cursor-pointer text-sm"
        :class="i === active ? 'bg-clay-50 text-clay-800' : 'text-sand-800 hover:bg-sand-50'"
        @mousedown.prevent="pick(o)" @mouseenter="active = i"
      >
        <span class="block">{{ o.label }}</span>
        <span v-if="o.hint" class="block text-xs text-sand-500">{{ o.hint }}</span>
      </li>
      <li v-if="!loading && !options.length" class="px-3 py-2 text-sm text-sand-500" role="presentation">
        {{ text ? 'لا نتائج مطابقة.' : 'اكتب للبحث.' }}
      </li>
    </ul>

    <p v-if="hint && !message" :id="`${id}-hint`" class="text-xs text-sand-500 mt-1">{{ hint }}</p>
    <p v-if="message" :id="`${id}-error`" class="text-xs text-rose-700 mt-1" role="alert">{{ message }}</p>
  </div>
</template>

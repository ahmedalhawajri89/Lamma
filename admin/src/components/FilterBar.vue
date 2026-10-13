<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import BaseIcon from './BaseIcon.vue'

defineProps({
  searchPlaceholder: { type: String, default: 'ابحث…' },
  hasSearch: { type: Boolean, default: true },

  /**
   * هل في الفلاتر ما يُمسح؟ زرّ «مسح الفلاتر» يظهر حينها وحده: زرٌّ دائم
   * الظهور لا يفعل شيئاً يعلّم المستخدم تجاهله. الافتراضي يُبقي السلوك القديم.
   */
  dirty: { type: Boolean, default: true },
})

const search = defineModel('search', { type: String, default: '' })
defineEmits(['reset'])

/**
 * «/» يركّز البحث من أي مكان في الشاشة، كما في GitHub وGmail؛ وEscape
 * داخل الحقل يمسحه. لا يعمل والمستخدم يكتب في حقل آخر.
 */
const input = ref(null)
function onKey(e) {
  if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return
  const t = e.target
  if (t instanceof HTMLElement && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName))) return
  if (document.querySelector('[role="dialog"][aria-modal="true"]')) return
  e.preventDefault()
  input.value?.focus()
}
onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="flex items-center gap-2 flex-wrap mb-4">
    <label v-if="hasSearch" class="relative basis-full sm:basis-auto sm:flex-1 sm:min-w-52 sm:max-w-80">
      <span class="sr-only">{{ searchPlaceholder }}</span>
      <BaseIcon
        name="search" :size="16"
        class="absolute top-1/2 -translate-y-1/2 text-sand-400 pointer-events-none"
        style="inset-inline-start: 0.75rem"
      />
      <input
        ref="input" v-model="search" type="search" class="input ps-9 pe-9" :placeholder="searchPlaceholder"
        @keydown.esc="search = ''"
      />
      <kbd
        v-if="!search"
        class="hidden md:grid absolute top-1/2 -translate-y-1/2 place-items-center size-5 rounded border border-sand-200 text-[0.7rem] text-sand-400 pointer-events-none"
        style="inset-inline-end: 0.6rem" aria-hidden="true"
      >/</kbd>
    </label>

    <slot />

    <button v-if="dirty" class="btn btn-ghost text-xs" type="button" @click="$emit('reset')">
      <BaseIcon name="close" :size="14" />
      مسح الفلاتر
    </button>
  </div>
</template>

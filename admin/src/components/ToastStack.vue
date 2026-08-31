<script setup>
import { useUiStore } from '@/stores/ui'
import BaseIcon from './BaseIcon.vue'

const ui = useUiStore()
</script>

<template>
  <!--
    `aria-live`: قارئ الشاشة يعلن التنبيه بلا نقل التركيز إليه.

    و`inset-inline-end` لا `start`: الشريط الجانبي على بداية السطر، وفي أسفله
    اسم المستخدم وزرّ الخروج — فتنبيهٌ هناك يغطّي زرّ الخروج نفسه، ومن أراد
    الخروج بعد عملية ناجحة وجد تأكيدها واقفاً في طريقه.

    والموضع بالنمط لا بصنف Tailwind: `inset-inline-start-4` ليس صنفاً موجوداً،
    فكان يُكتب ولا يفعل شيئاً.
  -->
  <div
    class="fixed bottom-4 z-50 flex flex-col gap-2 max-w-sm"
    style="inset-inline-end: 1rem"
    role="status" aria-live="polite"
  >
    <TransitionGroup name="toast">
      <div
        v-for="t in ui.toasts" :key="t.id"
        class="card px-4 py-3 flex items-start gap-3 shadow-lg"
        :class="t.type === 'error' ? 'border-rose-600/30' : 'border-olive-200'"
      >
        <BaseIcon
          :name="t.type === 'error' ? 'alert' : 'check'" :size="18"
          class="shrink-0 mt-0.5"
          :class="t.type === 'error' ? 'text-rose-700' : 'text-olive-600'"
        />
        <p class="text-sm text-sand-800 flex-1">{{ t.message }}</p>
        <button
          class="text-sand-400 hover:text-sand-700" type="button"
          aria-label="إغلاق" @click="ui.dismiss(t.id)"
        >
          <BaseIcon name="close" :size="15" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(8px); }

@media (prefers-reduced-motion: reduce) {
  .toast-enter-active, .toast-leave-active { transition: none; }
}
</style>

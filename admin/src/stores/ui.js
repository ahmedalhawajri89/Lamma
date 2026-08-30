import { defineStore } from 'pinia'
import { ref } from 'vue'

/** التنبيهات وحالة الشريط الجانبي. */
export const useUiStore = defineStore('ui', () => {
  const toasts = ref([])
  const sidebarOpen = ref(false)
  let seq = 0

  function toast(message, type = 'success', ms = 4000) {
    const id = ++seq
    toasts.value.push({ id, message, type })
    setTimeout(() => dismiss(id), ms)
  }

  function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  const success = (m) => toast(m, 'success')
  const error = (m) => toast(m, 'error', 6000)

  return { toasts, sidebarOpen, toast, dismiss, success, error }
})

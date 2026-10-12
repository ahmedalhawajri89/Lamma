import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

/** التنبيهات وحالة الشريط الجانبي. */
export const useUiStore = defineStore('ui', () => {
  const toasts = ref([])
  const sidebarOpen = ref(false)

  /**
   * الشريط مطويّاً إلى عمود أيقونات (على الشاشات الكبيرة وحدها).
   * يُحفظ في المتصفح فيبقى كما تركه الموظف في زيارته التالية.
   */
  const KEY = 'kafala-admin-sidebar-collapsed'
  const sidebarCollapsed = ref(readCollapsed())

  function readCollapsed() {
    try { return localStorage.getItem(KEY) === '1' } catch { return false }
  }

  watch(sidebarCollapsed, (v) => {
    try { localStorage.setItem(KEY, v ? '1' : '0') } catch { /* تخزين محجوب: يبقى للجلسة وحدها */ }
  })

  const toggleSidebar = () => { sidebarCollapsed.value = !sidebarCollapsed.value }
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

  return { toasts, sidebarOpen, sidebarCollapsed, toggleSidebar, toast, dismiss, success, error }
})

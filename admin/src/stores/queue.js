import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { admin } from '@/api/endpoints'

/**
 * «بانتظارك»: مهام الموظف الحالي، للشريط الجانبي وللنظرة العامة.
 *
 * الشريط يطلبها عند كل تنقّل، لكن بحدّ أدنى دقيقة بين طلبين: الأرقام
 * لا تتغيّر كل ثانية، وطلب مع كل نقرة هدر. والنظرة العامة تستلمها ضمن
 * استجابتها فتحدّث المخزن مباشرة بلا طلب إضافي.
 */
const MIN_INTERVAL = 60_000

export const useQueueStore = defineStore('queue', () => {
  const tasks = ref([])
  let fetchedAt = 0
  let pending = null

  function set(list) {
    tasks.value = list ?? []
    fetchedAt = Date.now()
  }

  async function refresh(force = false) {
    if (!force && Date.now() - fetchedAt < MIN_INTERVAL) return
    if (pending) return pending

    pending = admin.queue()
      .then(({ data }) => set(data.data))
      .catch(() => {})            // الشريط يعمل بلا أرقام إن فشل الطلب
      .finally(() => { pending = null })

    return pending
  }

  /** مجموع ما ينتظر في كل شاشة، لأرقام الشريط الجانبي. */
  const countsByPath = computed(() => {
    const out = {}
    for (const t of tasks.value) {
      const path = t.to?.path
      if (path) out[path] = (out[path] ?? 0) + t.count
    }
    return out
  })

  function clear() {
    tasks.value = []
    fetchedAt = 0
  }

  return { tasks, countsByPath, refresh, set, clear }
})

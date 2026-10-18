import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { admin } from '@/api/endpoints'
import { useUiStore } from '@/stores/ui'

/**
 * «بانتظارك»: مهام الموظف الحالي، للشريط الجانبي وللنظرة العامة.
 *
 * تبقى حيّة دون تحديث الصفحة، من ثلاثة مصادر:
 *  1. **جلب دوري** كل 30 ثانية ما دام التبويب ظاهراً. التبويب المخفي لا
 *     يطلب شيئاً، وعند العودة إليه يُجلب فوراً.
 *  2. **بعد كل إجراء** في أي شاشة: `client.js` يُطلق `kafala:mutated` بعد
 *     كل طلب مُغيِّر ناجح، فيتحدّث الرقم بعد الاعتماد أو التوثيق مباشرة.
 *  3. **التنقّل** بين الشاشات، بحدّ أدنى دقيقة بين طلبين.
 *
 * وحين يزيد عدد مهمة عمّا كان، يظهر تنبيه واحد بما وصل. لا يظهر في أول
 * تحميل ولا عند النقصان: النقصان عملٌ أنجزه الموظف بنفسه.
 *
 * ولا WebSockets: الخادم لا يبثّ، وطلب صغير كل نصف دقيقة كافٍ لطوابير
 * مراجعة بشرية لا تتغيّر كل ثانية.
 */
const MIN_INTERVAL = 60_000
const POLL_EVERY = 30_000
const MUTATION_DEBOUNCE = 500

export const useQueueStore = defineStore('queue', () => {
  const tasks = ref([])
  /** جُلبت المهام مرة على الأقل: قبلها لا يُقارَن بشيء (NewArrivalsBar) */
  const loaded = ref(false)
  /** يزيد بعد كل جلبٍ تلا إجراءً من الموظف نفسه: ما زاد حينها ليس «وصل» */
  const ownChange = ref(0)
  let fetchedAt = 0
  let pending = null
  let known = null            // آخر أعداد معروفة لكل مهمة، للمقارنة
  let timer = null
  let debounce = null
  let running = false

  function announce(list, quiet) {
    const next = Object.fromEntries(list.map((t) => [t.key, t.count]))

    // بعد إجراء الموظف نفسه لا تنبيه: مسوّدته الجديدة ليست «وصلت»
    if (known && !quiet) {
      // ما وصل إلى الشاشة المفتوحة يقوله شريطها («وصل جديد · اعرض»)، لا تنبيهٌ ثانٍ
      const here = window.location.pathname.replace(/^\/admin(?=\/)/, '')
      const grew = list.filter((t) => t.count > (known[t.key] ?? 0) && t.to?.path !== here)
      if (grew.length === 1) {
        const t = grew[0]
        useUiStore().toast(`وصل جديد: ${t.title} (${t.count - (known[t.key] ?? 0)})`, 'info', 5000)
      } else if (grew.length > 1) {
        useUiStore().toast(`وصلت مهام جديدة في ${grew.length} طوابير. راجع «بانتظارك».`, 'info', 5000)
      }
    }

    known = next
  }

  function set(list, quiet = false) {
    tasks.value = list ?? []
    loaded.value = true
    if (quiet) ownChange.value++
    fetchedAt = Date.now()
    announce(tasks.value, quiet)
  }

  async function refresh(force = false, quiet = false) {
    if (!force && Date.now() - fetchedAt < MIN_INTERVAL) return
    if (pending) return pending

    pending = admin.queue()
      .then(({ data }) => set(data.data, quiet))
      .catch(() => {})            // الشريط يعمل بلا أرقام إن فشل الطلب
      .finally(() => { pending = null })

    return pending
  }

  const visible = () => document.visibilityState === 'visible'

  function onVisible() {
    if (visible()) refresh(true)
  }

  function onMutated() {
    clearTimeout(debounce)
    debounce = setTimeout(() => refresh(true, true), MUTATION_DEBOUNCE)
  }

  /** يبدأ التحديث الحيّ. تُستدعى مرة عند دخول الموظف، والتكرار آمن. */
  function start() {
    if (running) return
    running = true

    timer = setInterval(() => { if (visible()) refresh(true) }, POLL_EVERY)
    document.addEventListener('visibilitychange', onVisible)
    window.addEventListener('focus', onVisible)
    window.addEventListener('kafala:mutated', onMutated)
  }

  function stop() {
    running = false
    clearInterval(timer)
    clearTimeout(debounce)
    document.removeEventListener('visibilitychange', onVisible)
    window.removeEventListener('focus', onVisible)
    window.removeEventListener('kafala:mutated', onMutated)
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
    loaded.value = false
    fetchedAt = 0
    known = null
  }

  return { tasks, loaded, ownChange, countsByPath, refresh, set, clear, start, stop }
})

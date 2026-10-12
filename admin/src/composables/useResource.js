import { ref, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/**
 * جلب قائمة مُصفَّحة مع فلاتر.
 *
 * ثلاثة أشياء تتكرر في كل شاشة قائمة، فتعيش هنا مرة واحدة:
 *
 *  1. **تأخير البحث** — كل ضغطة مفتاح طلبٌ للخادم بلا هذا التأخير.
 *  2. **الرجوع للصفحة الأولى عند تغيّر الفلتر** — بلا ذلك يفلتر المستخدم
 *     وهو على الصفحة السابعة فيرى «لا نتائج» بينما النتائج على الأولى.
 *  3. **تجاهل الاستجابات المتأخرة** — طلبان متتاليان قد يعودان بترتيب معكوس،
 *     فتظهر نتائج الفلتر القديم فوق الجديد. العدّاد يمنع ذلك.
 *  4. **الفلاتر في الرابط** — تُقرأ منه عند الفتح وتُكتب إليه عند التغيير،
 *     فيفتح زر «بانتظارك» الشاشة مفلترة مباشرة، ويبقى الفلتر بعد التحديث.
 *     لا يُقرأ إلا مفتاح معرّف في `initialFilters`، بنوعه نفسه.
 */

/** يحوّل قيمة نصية من الرابط إلى نوع القيمة الافتراضية للفلتر. */
function fromQuery(raw, fallback) {
  const value = Array.isArray(raw) ? raw[0] : raw
  if (typeof fallback === 'boolean') return value === '1' || value === 'true'
  if (typeof fallback === 'number') return Number(value) || fallback
  return value ?? ''
}

/** الفلاتر التي تخالف قيمها الافتراضية وحدها تظهر في الرابط. */
function toQuery(filters, defaults) {
  return Object.fromEntries(
    Object.entries(filters)
      .filter(([k, v]) => v !== '' && v !== null && v !== undefined && v !== (defaults[k] ?? (k === 'page' ? 1 : '')))
      .map(([k, v]) => [k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v)]),
  )
}

export function useResource(fetcher, initialFilters = {}) {
  const items = ref([])
  const meta = ref({ total: 0, current_page: 1, last_page: 1 })
  const loading = ref(false)
  const error = ref(null)
  const route = useRoute()
  const router = useRouter()
  const defaults = { page: 1, ...initialFilters }

  // الشاشة تملك رابطها وحده: عند الانتقال منها لا نمسّ رابط الصفحة التالية
  const ownPath = route?.path
  const onOwnPage = () => route && route.path === ownPath

  const readQuery = () => Object.fromEntries(
    Object.keys(defaults).map((k) => [k, route?.query[k] !== undefined ? fromQuery(route.query[k], defaults[k]) : defaults[k]]),
  )

  const filters = reactive(readQuery())

  let sequence = 0
  let timer = null

  async function load() {
    const mine = ++sequence
    loading.value = true
    error.value = null

    try {
      const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined && v !== false),
      )
      const { data } = await fetcher(params)

      if (mine !== sequence) return          // استجابة متأخرة لطلب قديم

      items.value = data.data ?? []
      meta.value = data.meta ?? meta.value
    } catch (e) {
      if (mine !== sequence) return
      error.value = e.userMessage ?? 'تعذّر جلب البيانات.'
      items.value = []
    } finally {
      if (mine === sequence) loading.value = false
    }
  }

  watch(
    () => ({ ...filters }),
    (next, prev) => {
      const pageOnly = prev && next.page !== prev.page &&
        Object.keys(next).every((k) => k === 'page' || next[k] === prev[k])

      if (!pageOnly && filters.page !== 1) {
        filters.page = 1                     // سيعيد هذا التغيير تشغيل المراقب
        return
      }

      clearTimeout(timer)
      timer = setTimeout(load, pageOnly ? 0 : 250)

      // الرابط يتبع الفلاتر (بلا إضافة خطوة في سجل المتصفح)
      if (router && onOwnPage()) {
        const query = toQuery(filters, defaults)
        if (JSON.stringify(query) !== JSON.stringify(toQuery(readQuery(), defaults))) {
          router.replace({ query })
        }
      }
    },
    { deep: true },
  )

  // رابط جديد للشاشة نفسها (زر من «بانتظارك» والشاشة مفتوحة): نطبّق فلاتره
  if (route) {
    watch(
      () => route.query,
      () => {
        if (!onOwnPage()) return
        const next = readQuery()
        if (JSON.stringify(toQuery(next, defaults)) !== JSON.stringify(toQuery(filters, defaults))) {
          Object.assign(filters, next)
        }
      },
    )
  }

  function reset() {
    Object.keys(filters).forEach((k) => {
      if (k !== 'page') filters[k] = initialFilters[k] ?? ''
    })
    filters.page = 1
  }

  load()

  return { items, meta, loading, error, filters, load, reset }
}

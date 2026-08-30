import { ref, reactive, watch } from 'vue'

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
 */
export function useResource(fetcher, initialFilters = {}) {
  const items = ref([])
  const meta = ref({ total: 0, current_page: 1, last_page: 1 })
  const loading = ref(false)
  const error = ref(null)
  const filters = reactive({ page: 1, ...initialFilters })

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
    },
    { deep: true },
  )

  function reset() {
    Object.keys(filters).forEach((k) => {
      if (k !== 'page') filters[k] = initialFilters[k] ?? ''
    })
    filters.page = 1
  }

  load()

  return { items, meta, loading, error, filters, load, reset }
}

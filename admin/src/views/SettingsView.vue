<script setup>
/**
 * الإعدادات: لوحة نظرة عامة لا نموذج.
 *
 * الصفحة تُقرأ: هوية المنصة كما يراها الزائر في الأعلى، ثم حالة كل سياسة
 * بأثرها الآن (كم رسالة تنتظر، الفعلي مقابل الهدف، كم تقريراً على الفريق)،
 * ثم من غيّر ماذا. والتعديل يُفتح في درجٍ جانبي لكل مجموعة، كباقي اللوحة،
 * بحقولها وسياقها وحفظها، فلا تكون الصفحة جدار حقول.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, RouterLink, useRoute, useRouter } from 'vue-router'
import { admin } from '@/api/endpoints'
import { confirmDialog, confirmState } from '@/composables/useConfirm'
import { useUiStore } from '@/stores/ui'
import { dateTime, num, relative } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import BaseIcon from '@/components/BaseIcon.vue'

/* global __SITE_URL__ */
const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const siteUrl = __SITE_URL__

/* ── المجموعات: كل واحدة تُعدَّل في درجها وتُحفظ وحدها ──────────────── */

const GROUPS = {
  identity: {
    title: 'هوية المنصة والتواصل',
    hint: 'ما يقرؤه الزائر في رأس الموقع وتذييله وصفحة التواصل.',
    // كل نصٍّ يقرؤه الزائر وتحته مقابله الإنجليزي (للموقع تحت /en)
    keys: ['site.name', 'site.name_en', 'site.tagline', 'site.tagline_en', 'site.start_year', 'site.email', 'site.phone', 'site.address', 'site.address_en'],
  },
  protection: {
    title: 'حماية الطفل',
    hint: 'ما يصل الطفل من الكفلاء، ومن يقرؤه قبله.',
    keys: ['policy.moderate_messages'],
  },
  finance: {
    title: 'المال والشفافية',
    hint: 'ما تُقاس عليه الأرقام المنشورة على صفحة الشفافية.',
    keys: ['finance.overhead_target_percent', 'finance.platform_fee_percent', 'finance.continuity_fund_percent'],
  },
  reports: {
    title: 'متابعة الأطفال',
    hint: 'إيقاع التقارير والزيارات لكل طفل في الميدان.',
    keys: ['policy.report_cadence_months'],
  },
}
const BOOLEAN = ['policy.moderate_messages']
const KEYS = Object.values(GROUPS).flatMap((g) => g.keys)

const FIELDS = {
  'site.name': { label: 'اسم المنصة', hint: 'في رأس الموقع وتذييله وعنوان كل صفحة.', required: true },
  'site.name_en': { label: 'اسم المنصة بالإنجليزية', hint: 'للموقع الإنجليزي. فارغاً يظهر الاسم العربي.', en: true },
  'site.tagline': { label: 'الوصف المختصر', hint: 'سطرٌ تحت الاسم في التذييل.' },
  'site.tagline_en': { label: 'الوصف المختصر بالإنجليزية', hint: 'فارغاً يظهر الوصف العربي في الموقع الإنجليزي.', en: true },
  // نصٌّ لا `number`: حقل الرقم يُنسَّق بلغة الصفحة فتظهر «٢٠١٢»
  'site.start_year': { label: 'سنة التأسيس', numeric: true, ltr: true, max: 4 },
  'site.email': { label: 'بريد التواصل', type: 'email', ltr: true },
  'site.phone': { label: 'الهاتف', type: 'tel', hint: 'بالصيغة الدولية.', ltr: true },
  'site.address': { label: 'العنوان', hint: 'مدينة وبلد يكفيان.' },
  'site.address_en': { label: 'العنوان بالإنجليزية', hint: 'مثل: London, United Kingdom', en: true },
}

const CADENCE = [
  { value: 1, label: 'كل شهر' },
  { value: 2, label: 'كل شهرين' },
  { value: 3, label: 'كل 3 أشهر' },
  { value: 6, label: 'كل 6 أشهر' },
  { value: 12, label: 'سنوياً' },
]

/* ── الحالة: المحفوظ والمسوّدة ───────────────────────────────────────── */

const loading = ref(true)
const failed = ref(false)
const saved = ref({})
const draft = reactive(Object.fromEntries(KEYS.map((k) => [k, ''])))
const meta = ref({})
const errors = ref({})
const saving = ref(false)

const normal = (k, val) => (BOOLEAN.includes(k) ? Boolean(val) : (val ?? ''))
const isDirty = (k) => JSON.stringify(draft[k]) !== JSON.stringify(normal(k, saved.value[k]))
const err = (k) => errors.value[k.replace('.', '__')]?.[0]
/** معرّف الحقل في الصفحة: النقطة في `site.email` تربك محدّدات CSS */
const fid = (k) => `f-${k.replace('.', '-')}`

function lastChanged(id) {
  const hits = GROUPS[id].keys.map((k) => meta.value.last_changed?.[k]).filter(Boolean)
  return hits.sort((a, b) => new Date(b.at) - new Date(a.at))[0] ?? null
}

async function load() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await admin.settings()
    saved.value = data.data ?? {}
    meta.value = data.meta ?? {}
    KEYS.forEach((k) => { draft[k] = normal(k, saved.value[k]) })
    loadHistory()
  } catch {
    // لا تعديل بلا القيم الحالية: حفظٌ فوق فراغ يمسح الإعدادات
    failed.value = true
  } finally {
    loading.value = false
  }
}
onMounted(load)

/* ── أسعار الصرف ── */
const rates = ref([])
const refreshingRates = ref(false)
async function loadRates() {
  try {
    rates.value = (await admin.fx.rates()).data.data
  } catch { rates.value = [] }
}
async function refreshRates() {
  refreshingRates.value = true
  try {
    const { data } = await admin.fx.refresh()
    ui.success(data.message)
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    refreshingRates.value = false
    loadRates()
  }
}
onMounted(loadRates)

/* ── ما يُعرض: من المحفوظ، والدرج من المسوّدة ───────────────────────── */

const view = (k) => saved.value[k]
const sinceOf = (y) => (Number(y) ? new Date().getFullYear() - Number(y) : null)

const actual = computed(() => meta.value.overhead_actual)
const hasActual = computed(() => actual.value !== null && actual.value !== undefined)
const overOf = (t) => hasActual.value && actual.value > Number(t || 0)
/** موضعٌ على مقياس 0 إلى 30٪، حدود الإعداد نفسه */
const pos = (p) => `${Math.min(100, Math.max(0, (Number(p) / 30) * 100))}%`
const cadenceLabel = (m) => CADENCE.find((c) => c.value === Number(m))?.label ?? `كل ${m} أشهر`
const perYearOf = (m) => (Number(m) ? Math.round(12 / Number(m)) : 0)

/* ── الدرج (?edit=) ──────────────────────────────────────────────────── */

const editing = computed(() => (GROUPS[route.query.edit] ? route.query.edit : null))
const group = computed(() => (editing.value ? GROUPS[editing.value] : null))
const groupDirty = computed(() => Boolean(group.value?.keys.some(isDirty)))

function open(id) { router.replace({ query: { ...route.query, edit: id } }) }
function dismiss() {
  const { edit: _, ...rest } = route.query
  router.replace({ query: rest })
}

async function close() {
  if (groupDirty.value && !saving.value) {
    const ok = await confirmDialog({
      title: 'تغييرات لم تُحفظ',
      message: 'في هذه المجموعة تغييرات لم تُحفظ، وتضيع إن أغلقت.',
      confirmLabel: 'أغلق دون حفظ',
      danger: true,
    })
    if (!ok) return
  }
  if (group.value) group.value.keys.forEach((k) => { draft[k] = normal(k, saved.value[k]) })
  errors.value = {}
  dismiss()
}

/** يُرسَل ما تغيّر في المجموعة المفتوحة وحدها */
async function save() {
  const id = editing.value
  const keys = GROUPS[id].keys.filter(isDirty)
  if (!keys.length || saving.value) return

  // إيقاف مراجعة الرسائل قرارُ حماية طفل، فيُؤكَّد بما يعنيه
  if (keys.includes('policy.moderate_messages') && draft['policy.moderate_messages'] === false) {
    const ok = await confirmDialog({
      title: 'إيقاف مراجعة رسائل الكفلاء',
      message: 'ستصل رسائل الكفلاء إلى الأطفال فوراً دون أن يقرأها أحد قبلهم. سياسة حماية الطفل المعلنة على الموقع تَعِد بالمراجعة.',
      confirmLabel: 'أوقف المراجعة',
      danger: true,
    })
    if (!ok) return
  }

  saving.value = true
  errors.value = {}
  try {
    const { data } = await admin.updateSettings(keys.map((name) => {
      const [g, ...rest] = name.split('.')
      return { group: g, key: rest.join('.'), value: BOOLEAN.includes(name) ? String(Boolean(draft[name])) : draft[name] }
    }))
    saved.value = data.data ?? saved.value
    keys.forEach((k) => { draft[k] = normal(k, saved.value[k]) })
    ui.success(`حُفظت ${GROUPS[id].title}.`)
    dismiss()
    // من غيّر ومتى، والسياق، والسجل: تُعاد بعد الحفظ
    try { meta.value = (await admin.settings()).data.meta ?? meta.value } catch { /* السياق ثانوي */ }
    loadHistory()
  } catch (e) {
    errors.value = e.fieldErrors ?? {}
    ui.error(Object.values(e.fieldErrors ?? {})[0]?.[0] ?? e.userMessage ?? 'تعذّر الحفظ.')
  } finally {
    saving.value = false
  }
}

const panel = ref(null)
watch(editing, async (id) => {
  if (!id) return
  await nextTick()
  panel.value?.querySelector('input, [role=switch], [role=radio]')?.focus()
})

/* ── سجل التغييرات ───────────────────────────────────────────────────── */

const history = ref(null)
const historyFailed = ref(false)
const historyAll = ref(false)
async function loadHistory() {
  historyFailed.value = false
  try {
    const { data } = await admin.activity({ event: 'settings_updated', per_page: 20 })
    history.value = data.data ?? []
  } catch {
    historyFailed.value = true
  }
}
const historyShown = computed(() => (historyAll.value ? history.value : history.value?.slice(0, 5)) ?? [])

/* ── ما لم يُحفظ، ولوحة المفاتيح ─────────────────────────────────────── */

onBeforeRouteLeave(async () => {
  if (!groupDirty.value || saving.value) return true
  return Boolean(await confirmDialog({
    title: 'تغييرات لم تُحفظ',
    message: 'في الإعدادات تغييرات لم تُحفظ بعد، وتضيع إن خرجت.',
    confirmLabel: 'اخرج دون حفظ',
    danger: true,
  }))
})
function beforeUnload(e) { if (groupDirty.value) { e.preventDefault(); e.returnValue = '' } }
function onKey(e) {
  if (!editing.value || confirmState.open) return
  if ((e.ctrlKey || e.metaKey) && e.code === 'KeyS') { e.preventDefault(); save() }
  else if (e.key === 'Escape' && !saving.value) { e.preventDefault(); close() }
}
onMounted(() => { window.addEventListener('beforeunload', beforeUnload); document.addEventListener('keydown', onKey) })
onBeforeUnmount(() => { window.removeEventListener('beforeunload', beforeUnload); document.removeEventListener('keydown', onKey) })
</script>

<template>
  <div>
    <PageHeader title="الإعدادات" subtitle="كيف تعمل المنصة الآن، وما يراه الزائر منها." />

    <div v-if="loading" class="space-y-4">
      <div class="h-48 bg-sand-100 rounded-2xl animate-pulse" />
      <div class="grid md:grid-cols-3 gap-4"><div v-for="i in 3" :key="i" class="h-44 bg-sand-100 rounded-2xl animate-pulse" /></div>
    </div>

    <div v-else-if="failed" class="card p-8 text-center" role="alert">
      <p class="font-medium text-sand-900">تعذّر تحميل الإعدادات</p>
      <p class="text-sm text-sand-500 mt-1">لا يُفتح التعديل قبل أن تُحمَّل القيم الحالية، كي لا يُحفظ فوقها فراغ.</p>
      <button class="btn btn-outline mt-4" type="button" @click="load">إعادة المحاولة</button>
    </div>

    <template v-else>
      <!-- ══ الهوية: كما يراها الزائر ══ -->
      <section class="rounded-2xl bg-[#0f5c5c] text-white p-6 lg:p-8" aria-labelledby="idName">
        <div class="flex flex-wrap items-start gap-5">
          <span class="size-16 shrink-0 rounded-2xl bg-[#E0A63B] text-[#0f5c5c] grid place-items-center text-3xl font-bold" aria-hidden="true">
            {{ (view('site.name') || 'ل').trim().charAt(0) }}
          </span>
          <div class="flex-1 min-w-[14rem]">
            <p class="text-xs text-white/60">هوية المنصة كما يراها الزائر</p>
            <h2 id="idName" class="text-2xl lg:text-3xl font-bold mt-1">{{ view('site.name') || 'بلا اسم' }}</h2>
            <p v-if="view('site.tagline')" class="text-white/80 mt-1">{{ view('site.tagline') }}</p>
            <p v-if="view('site.name_en') || view('site.tagline_en')" class="text-white/60 text-sm mt-2" dir="ltr" lang="en">
              <span class="font-semibold text-white/80">{{ view('site.name_en') }}</span>
              <template v-if="view('site.name_en') && view('site.tagline_en')"> · </template>{{ view('site.tagline_en') }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <a :href="siteUrl" target="_blank" rel="noopener" class="btn !bg-transparent !text-white border border-white/30 hover:!bg-white/10">
              <BaseIcon name="external" :size="15" /> افتح الموقع
            </a>
            <button type="button" class="btn !bg-white !text-[#0f5c5c] hover:!bg-white/90 font-semibold" @click="open('identity')">
              <BaseIcon name="edit" :size="15" /> تعديل الهوية
            </button>
          </div>
        </div>

        <ul class="flex flex-wrap gap-2 mt-6 text-sm">
          <li v-if="sinceOf(view('site.start_year'))" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5">
            <BaseIcon name="clock" :size="14" class="text-[#E0A63B]" />
            نعمل منذ <span class="num">{{ sinceOf(view('site.start_year')) }}</span> عاماً
            <span class="text-white/50 num">({{ view('site.start_year') }})</span>
          </li>
          <li v-if="view('site.email')" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5">
            <BaseIcon name="mail" :size="14" class="text-[#E0A63B]" /><span class="num" dir="ltr">{{ view('site.email') }}</span>
          </li>
          <li v-if="view('site.phone')" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5">
            <BaseIcon name="chat" :size="14" class="text-[#E0A63B]" /><span class="num" dir="ltr">{{ view('site.phone') }}</span>
          </li>
          <li v-if="view('site.address')" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5">
            <BaseIcon name="map" :size="14" class="text-[#E0A63B]" />{{ view('site.address') }}
          </li>
        </ul>
        <p v-if="lastChanged('identity')" class="text-xs text-white/50 mt-4">
          آخر تعديل: {{ lastChanged('identity').by }} <span :title="dateTime(lastChanged('identity').at)">{{ relative(lastChanged('identity').at) }}</span>
        </p>
      </section>

      <!-- ══ السياسات: حالة كل واحدة وأثرها الآن ══ -->
      <div class="grid md:grid-cols-3 gap-4 mt-4">
        <!-- حماية الطفل -->
        <article class="rounded-2xl bg-sand-0 border p-5 flex flex-col" :class="view('policy.moderate_messages') ? 'border-sand-200' : 'border-rose-300'" aria-labelledby="tProt">
          <header class="flex items-center gap-3">
            <span class="size-10 rounded-xl grid place-items-center" :class="view('policy.moderate_messages') ? 'bg-[#c9694b]/10 text-[#c9694b]' : 'bg-rose-100 text-rose-700'"><BaseIcon name="shield" :size="19" /></span>
            <h3 id="tProt" class="font-semibold flex-1">حماية الطفل</h3>
            <button type="button" class="btn btn-outline !py-1 !px-3 text-sm" @click="open('protection')"><BaseIcon name="edit" :size="13" /> تعديل</button>
          </header>
          <p class="mt-5 text-xs text-sand-500">مراجعة رسائل الكفلاء قبل وصولها</p>
          <p class="text-2xl font-bold mt-0.5" :class="view('policy.moderate_messages') ? 'text-sand-900' : 'text-rose-700'">
            {{ view('policy.moderate_messages') ? 'مفعّلة' : 'موقوفة' }}
          </p>
          <p class="text-sm mt-auto pt-4" :class="view('policy.moderate_messages') ? 'text-sand-600' : 'text-rose-700'">
            <RouterLink v-if="view('policy.moderate_messages')" to="/messages" class="hover:underline">
              <span class="num font-semibold text-sand-900">{{ num(meta.messages_pending ?? 0) }}</span> رسالة تنتظر المراجعة الآن
            </RouterLink>
            <template v-else>الرسائل تصل الأطفال دون أن يقرأها أحد.</template>
          </p>
        </article>

        <!-- نسبة التشغيل -->
        <article class="rounded-2xl bg-sand-0 border border-sand-200 p-5 flex flex-col" aria-labelledby="tFin">
          <header class="flex items-center gap-3">
            <span class="size-10 rounded-xl grid place-items-center bg-[#0f5c5c]/10 text-[#0f5c5c]"><BaseIcon name="wallet" :size="19" /></span>
            <h3 id="tFin" class="font-semibold flex-1">نسبة التشغيل</h3>
            <button type="button" class="btn btn-outline !py-1 !px-3 text-sm" @click="open('finance')"><BaseIcon name="edit" :size="13" /> تعديل</button>
          </header>
          <p class="mt-5 text-xs text-sand-500">الفعلي في <span class="num">{{ meta.overhead_year }}</span> من كل ما صُرف</p>
          <p class="text-2xl font-bold mt-0.5 num" :class="overOf(view('finance.overhead_target_percent')) ? 'text-rose-700' : 'text-sand-900'">
            <template v-if="hasActual">{{ actual }}%</template><template v-else>لا صرف بعد</template>
          </p>
          <div class="mt-auto pt-4">
            <div v-if="hasActual" class="relative h-1.5 rounded-full bg-sand-100" aria-hidden="true">
              <span class="absolute inset-y-0 start-0 rounded-full" :class="overOf(view('finance.overhead_target_percent')) ? 'bg-rose-400' : 'bg-[#1e8a7a]'" :style="{ width: pos(actual) }" />
              <span class="absolute -top-1 -bottom-1 w-0.5 bg-sand-900" :style="{ insetInlineStart: pos(view('finance.overhead_target_percent')) }" />
            </div>
            <p class="text-sm mt-2" :class="overOf(view('finance.overhead_target_percent')) ? 'text-rose-700' : 'text-sand-600'">
              الهدف <span class="num font-semibold">{{ view('finance.overhead_target_percent') }}%</span><template v-if="hasActual">،
                {{ overOf(view('finance.overhead_target_percent')) ? 'والفعلي فوقه.' : 'والفعلي ضمنه.' }}</template>
            </p>
          </div>
          <p class="text-sm text-sand-600 mt-3">
            التشغيل <span class="num font-semibold text-sand-900">{{ view('finance.platform_fee_percent') }}%</span> من كل دفعة،
            منها <span class="num font-semibold text-sand-900">{{ view('finance.continuity_fund_percent') }}%</span> لصندوق الاستمرارية.
          </p>
        </article>

        <!-- متابعة الأطفال -->
        <article class="rounded-2xl bg-sand-0 border border-sand-200 p-5 flex flex-col" aria-labelledby="tRep">
          <header class="flex items-center gap-3">
            <span class="size-10 rounded-xl grid place-items-center bg-[#E0A63B]/15 text-[#a8741a]"><BaseIcon name="file" :size="19" /></span>
            <h3 id="tRep" class="font-semibold flex-1">متابعة الأطفال</h3>
            <button type="button" class="btn btn-outline !py-1 !px-3 text-sm" @click="open('reports')"><BaseIcon name="edit" :size="13" /> تعديل</button>
          </header>
          <p class="mt-5 text-xs text-sand-500">تقرير عن كل طفل</p>
          <p class="text-2xl font-bold mt-0.5 text-sand-900">{{ cadenceLabel(view('policy.report_cadence_months')) }}</p>
          <p class="text-sm text-sand-600 mt-auto pt-4">
            <span class="num font-semibold text-sand-900">{{ perYearOf(view('policy.report_cadence_months')) }}</span> تقارير لكل طفل سنوياً<template v-if="meta.active_orphans">،
              <span class="num font-semibold text-sand-900">{{ num(perYearOf(view('policy.report_cadence_months')) * meta.active_orphans) }}</span> على الفريق</template>.
          </p>
        </article>
      </div>

      <!-- ══ أسعار الصرف ══ -->
      <section v-if="rates.length" class="rounded-2xl bg-sand-0 border border-sand-200 mt-4" aria-labelledby="fxTitle">
        <header class="flex flex-wrap items-center gap-3 px-5 py-4 border-b border-sand-100">
          <span class="size-10 rounded-xl grid place-items-center bg-[#0f5c5c]/10 text-[#0f5c5c]"><BaseIcon name="globe" :size="19" /></span>
          <div class="flex-1">
            <h3 id="fxTitle" class="font-semibold">أسعار الصرف</h3>
            <p class="text-xs text-sand-500">تُحدَّث كل يوم. بها يُحسب ما يعادل قسط الكافل بعملته، ويُقترح سعر الصرف المحلي.</p>
          </div>
          <button type="button" class="btn btn-outline !py-1 !px-3 text-sm" :disabled="refreshingRates" @click="refreshRates">
            <BaseIcon :name="refreshingRates ? 'loader' : 'undo'" :size="13" :class="{ 'animate-spin': refreshingRates }" /> حدّث الآن
          </button>
        </header>
        <ul class="grid sm:grid-cols-2 lg:grid-cols-3 gap-px bg-sand-100">
          <li v-for="r in rates.filter((x) => x.currency !== 'USD')" :key="r.currency" class="bg-sand-0 px-5 py-3 flex items-baseline gap-3">
            <span class="num font-semibold w-12" dir="ltr">{{ r.currency }}</span>
            <span class="flex-1 text-sm">
              <span class="num" dir="ltr">1 {{ r.currency }} = ${{ r.rate.toFixed(4) }}</span>
              <span v-if="r.for_sponsors" class="ms-1.5 text-[0.7rem] rounded-full bg-amber-50 text-amber-800 px-1.5 py-0.5">للكفلاء</span>
            </span>
            <span class="text-xs" :class="r.stale ? 'text-rose-700' : 'text-sand-500'">{{ r.fetched_at ? relative(r.fetched_at) : 'سعر ابتدائي' }}</span>
          </li>
        </ul>
      </section>

      <!-- ══ سجل التغييرات ══ -->
      <section class="rounded-2xl bg-sand-0 border border-sand-200 mt-4" aria-labelledby="hTitle">
        <header class="flex flex-wrap items-center gap-3 px-5 py-4 border-b border-sand-100">
          <span class="size-10 rounded-xl grid place-items-center bg-sand-100 text-sand-700"><BaseIcon name="history" :size="19" /></span>
          <div class="flex-1">
            <h3 id="hTitle" class="font-semibold">سجل التغييرات</h3>
            <p class="text-xs text-sand-500">كل تعديل على هذه الصفحة بقيمته قبل وبعد.</p>
          </div>
          <span class="text-xs text-sand-500">
            عملة الأساس <span class="num font-semibold text-sand-800" dir="ltr">{{ view('finance.base_currency') || 'USD' }}</span>، ثابتة في إعداد الخادم
          </span>
        </header>

        <div v-if="!history && !historyFailed" class="p-5 space-y-3"><div v-for="n in 3" :key="n" class="h-10 rounded bg-sand-100 animate-pulse" /></div>
        <div v-else-if="historyFailed" class="p-6 text-center">
          <p class="text-sm text-sand-500 mb-3">تعذّر تحميل السجل.</p>
          <button class="btn btn-outline" type="button" @click="loadHistory">إعادة المحاولة</button>
        </div>
        <ol v-else-if="history.length" class="divide-y divide-sand-100">
          <li v-for="h in historyShown" :key="h.id" class="px-5 py-3.5 flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <p class="text-sm w-44 shrink-0"><span class="font-medium">{{ h.actor }}</span>
              <span class="block text-xs text-sand-500" :title="dateTime(h.created_at)">{{ relative(h.created_at) }}</span></p>
            <ul class="flex-1 min-w-0 space-y-0.5 text-sm">
              <li v-for="c in h.changes" :key="c.field" class="flex flex-wrap items-baseline gap-x-2">
                <span class="text-sand-500">{{ c.label }}</span>
                <span class="text-sand-400 line-through">{{ c.before ?? 'فارغ' }}</span>
                <span class="text-sand-400">←</span>
                <span class="text-sand-900 font-medium">{{ c.after ?? 'فارغ' }}</span>
              </li>
            </ul>
          </li>
        </ol>
        <p v-else class="px-5 py-8 text-center text-sm text-sand-500">لم يُغيَّر شيء من هنا بعد.</p>
        <button
          v-if="history && history.length > 5" type="button" class="w-full py-3 text-sm text-clay-700 hover:bg-sand-25 border-t border-sand-100"
          @click="historyAll = !historyAll"
        >{{ historyAll ? 'أقل' : `عرض كل التغييرات (${history.length})` }}</button>
      </section>
    </template>

    <!-- ══ درج التعديل ══ -->
    <Teleport to="body">
      <div v-if="group" class="fixed inset-0 z-30 bg-sand-900/30" aria-hidden="true" @click="close" />
      <form
        v-if="group" ref="panel" novalidate role="dialog" aria-modal="true" aria-labelledby="edTitle"
        class="fixed inset-y-0 end-0 z-40 w-full sm:w-[32rem] bg-sand-0 border-s border-sand-200 flex flex-col"
        @submit.prevent="save"
      >
        <header class="flex items-start gap-3 p-5 border-b border-sand-200">
          <div class="flex-1">
            <h2 id="edTitle" class="text-lg font-semibold">{{ group.title }}</h2>
            <p class="text-sm text-sand-500 mt-0.5">{{ group.hint }}</p>
          </div>
          <button class="btn btn-ghost !p-2" type="button" aria-label="إغلاق" @click="close"><BaseIcon name="close" :size="18" /></button>
        </header>

        <div class="flex-1 overflow-y-auto p-5">
          <!-- الهوية والتواصل -->
          <div v-if="editing === 'identity'" class="space-y-5">
            <div v-for="k in group.keys" :key="k">
              <label :for="fid(k)" class="block text-sm font-medium text-sand-800 mb-1.5">
                {{ FIELDS[k].label }}<span v-if="FIELDS[k].required" class="text-rose-600"> *</span>
              </label>
              <input
                :id="fid(k)" v-model="draft[k]" :type="FIELDS[k].type ?? 'text'" class="input"
                :class="{ num: FIELDS[k].ltr }" :dir="FIELDS[k].ltr || FIELDS[k].en ? 'ltr' : undefined"
                :lang="FIELDS[k].en ? 'en' : undefined"
                :inputmode="FIELDS[k].numeric ? 'numeric' : undefined" :maxlength="FIELDS[k].max"
                :aria-invalid="Boolean(err(k))"
              />
              <p v-if="err(k)" class="text-xs text-rose-700 mt-1" role="alert">{{ err(k) }}</p>
              <p v-else-if="k === 'site.start_year' && sinceOf(draft[k])" class="text-xs text-sand-500 mt-1">على الموقع: «نعمل منذ <span class="num">{{ sinceOf(draft[k]) }}</span> عاماً».</p>
              <p v-else-if="FIELDS[k].hint" class="text-xs text-sand-500 mt-1">{{ FIELDS[k].hint }}</p>
            </div>
          </div>

          <!-- حماية الطفل -->
          <div v-else-if="editing === 'protection'">
            <div class="flex items-start gap-4 rounded-xl border p-4" :class="draft['policy.moderate_messages'] ? 'border-sand-200' : 'border-rose-300 bg-rose-50'">
              <div class="flex-1">
                <p id="modLabel" class="font-medium">مراجعة رسائل الكفلاء قبل وصولها للطفل</p>
                <p class="text-xs text-sand-600 mt-1 leading-relaxed">
                  كل رسالة يقرؤها أحد الفريق قبل أن تصل: لا رقم هاتف، ولا عنوان، ولا طلب لقاء. سياسة حماية الطفل المعلنة على الموقع تَعِد بهذا.
                </p>
              </div>
              <button
                type="button" role="switch" :aria-checked="Boolean(draft['policy.moderate_messages'])" aria-labelledby="modLabel"
                class="relative w-11 h-6 rounded-full shrink-0 transition-colors mt-0.5"
                :class="draft['policy.moderate_messages'] ? 'bg-clay-600' : 'bg-sand-300'"
                @click="draft['policy.moderate_messages'] = !draft['policy.moderate_messages']"
              >
                <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all" :class="draft['policy.moderate_messages'] ? 'start-[1.375rem]' : 'start-0.5'" />
              </button>
            </div>
            <p class="text-sm mt-4" :class="draft['policy.moderate_messages'] ? 'text-sand-600' : 'text-rose-700'">
              <template v-if="draft['policy.moderate_messages']"><span class="num font-semibold">{{ num(meta.messages_pending ?? 0) }}</span> رسالة تنتظر المراجعة الآن.</template>
              <template v-else>إن حُفظ هذا: تصل الرسائل الأطفال فور إرسالها دون أن يقرأها أحد.</template>
            </p>
          </div>

          <!-- المال والشفافية -->
          <div v-else-if="editing === 'finance'" class="space-y-6">
            <div>
              <label for="sOverhead" class="block text-sm font-medium text-sand-800 mb-1.5">نسبة التشغيل المستهدفة</label>
              <span class="relative block w-32" dir="ltr">
                <input
                  id="sOverhead" v-model.number="draft['finance.overhead_target_percent']" type="text" inputmode="numeric" maxlength="2"
                  class="input num !pr-8 text-left text-lg" :aria-invalid="Boolean(err('finance.overhead_target_percent'))"
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sand-500 pointer-events-none">%</span>
              </span>
              <p v-if="err('finance.overhead_target_percent')" class="text-xs text-rose-700 mt-1" role="alert">{{ err('finance.overhead_target_percent') }}</p>
              <p class="text-xs text-sand-500 mt-1.5">ما يُصرف على التشغيل من كل ما يُصرف. هدفٌ يُقاس عليه لا حدٌّ يفرضه النظام، بين 0 و30.</p>
            </div>
            <div v-if="hasActual" class="rounded-xl bg-sand-25 border border-sand-100 p-4">
              <div class="flex items-baseline justify-between text-sm">
                <span>الفعلي في <span class="num">{{ meta.overhead_year }}</span></span>
                <span class="num text-xl font-bold" :class="overOf(draft['finance.overhead_target_percent']) ? 'text-rose-700' : 'text-[#0f5c5c]'">{{ actual }}%</span>
              </div>
              <div class="relative h-2 rounded-full bg-sand-100 mt-3" aria-hidden="true">
                <span class="absolute inset-y-0 start-0 rounded-full" :class="overOf(draft['finance.overhead_target_percent']) ? 'bg-rose-400' : 'bg-[#1e8a7a]'" :style="{ width: pos(actual) }" />
                <span class="absolute -top-1.5 -bottom-1.5 w-0.5 bg-sand-900" :style="{ insetInlineStart: pos(draft['finance.overhead_target_percent']) }" />
              </div>
              <div class="flex justify-between text-[0.7rem] text-sand-400 mt-1.5 num"><span>0%</span><span>30%</span></div>
              <p class="text-xs mt-2" :class="overOf(draft['finance.overhead_target_percent']) ? 'text-rose-700' : 'text-sand-600'">
                {{ overOf(draft['finance.overhead_target_percent']) ? 'بهذا الهدف يصير الفعلي فوقه، ويظهر هكذا في تقارير الشفافية.' : 'الفعلي ضمن هذا الهدف.' }} الخطّ الداكن هو الهدف.
              </p>
            </div>
            <div>
              <label for="sFee" class="block text-sm font-medium text-sand-800 mb-1.5">نسبة التشغيل المقتطعة من كل دفعة</label>
              <span class="relative block w-32" dir="ltr">
                <input
                  id="sFee" v-model.number="draft['finance.platform_fee_percent']" type="text" inputmode="decimal" maxlength="4"
                  class="input num !pr-8 text-left text-lg" :aria-invalid="Boolean(err('finance.platform_fee_percent'))"
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sand-500 pointer-events-none">%</span>
              </span>
              <p v-if="err('finance.platform_fee_percent')" class="text-xs text-rose-700 mt-1" role="alert">{{ err('finance.platform_fee_percent') }}</p>
              <p class="text-xs text-sand-500 mt-1.5">تُحفظ على كل دفعة لحظة دفعها، والباقي يُحوَّل لجمعية الطفل. تغييرها يسري على الدفعات الجديدة وحدها، ويراها الكافل قبل أن يكفل.</p>
            </div>
            <div>
              <label for="sFund" class="block text-sm font-medium text-sand-800 mb-1.5">منها لصندوق الاستمرارية</label>
              <span class="relative block w-32" dir="ltr">
                <input
                  id="sFund" v-model.number="draft['finance.continuity_fund_percent']" type="text" inputmode="decimal" maxlength="4"
                  class="input num !pr-8 text-left text-lg" :aria-invalid="Boolean(err('finance.continuity_fund_percent'))"
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sand-500 pointer-events-none">%</span>
              </span>
              <p v-if="err('finance.continuity_fund_percent')" class="text-xs text-rose-700 mt-1" role="alert">{{ err('finance.continuity_fund_percent') }}</p>
              <p class="text-xs text-sand-500 mt-1.5">من كل ما يصل في الشهر، داخل نسبة التشغيل لا فوقها. يغطّي الصندوق شهر الطفل حين يتعثّر قسط كافله ولا يُحلّ.</p>
            </div>
            <p class="text-xs text-sand-500">عملة الأساس <span class="num font-semibold" dir="ltr">{{ view('finance.base_currency') || 'USD' }}</span> ثابتة في إعداد الخادم: كل المبالغ المخزّنة محسوبة بها.</p>
          </div>

          <!-- متابعة الأطفال -->
          <div v-else-if="editing === 'reports'">
            <p id="cadLabel" class="text-sm font-medium text-sand-800">كل كم يُنتظر تقرير عن الطفل</p>
            <div class="mt-2 grid grid-cols-2 gap-2" role="radiogroup" aria-labelledby="cadLabel">
              <button
                v-for="c in CADENCE" :key="c.value" type="button" role="radio" :aria-checked="Number(draft['policy.report_cadence_months']) === c.value"
                class="rounded-xl border px-4 py-3 text-start transition-colors"
                :class="Number(draft['policy.report_cadence_months']) === c.value ? 'border-clay-600 bg-clay-50 text-clay-800' : 'border-sand-200 hover:border-sand-300'"
                @click="draft['policy.report_cadence_months'] = c.value"
              >
                <span class="block font-medium text-sm">{{ c.label }}</span>
                <span class="block text-xs text-sand-500 mt-0.5"><span class="num">{{ perYearOf(c.value) }}</span> في السنة لكل طفل</span>
              </button>
            </div>
            <p v-if="meta.active_orphans" class="text-sm text-sand-600 mt-4">
              على الفريق قرابة <span class="num font-semibold text-sand-900">{{ num(perYearOf(draft['policy.report_cadence_months']) * meta.active_orphans) }}</span> تقريراً في السنة لـ<span class="num">{{ num(meta.active_orphans) }}</span> طفلاً نشطاً.
              ومنها تُحسب التقارير المتأخرة وموعد الزيارة القادمة.
            </p>
          </div>
        </div>

        <footer class="flex items-center gap-3 px-5 py-4 border-t border-sand-200">
          <p class="text-xs text-sand-500 flex-1">
            <template v-if="lastChanged(editing)">آخر تعديل: {{ lastChanged(editing).by }} {{ relative(lastChanged(editing).at) }}</template>
          </p>
          <!-- بلا تغيير: إغلاقٌ لا زرّ حفظٍ باهت -->
          <button v-if="!groupDirty" type="button" class="btn btn-outline" @click="close">إغلاق</button>
          <template v-else>
            <button type="button" class="btn btn-ghost" :disabled="saving" @click="close">إلغاء</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <BaseIcon v-if="saving" name="loader" :size="15" class="animate-spin" />
              {{ saving ? 'جارٍ الحفظ…' : 'حفظ التغييرات' }} <kbd class="kbd !bg-white/15 !text-white/90 !border-white/20">Ctrl S</kbd>
            </button>
          </template>
        </footer>
      </form>
    </Teleport>
  </div>
</template>

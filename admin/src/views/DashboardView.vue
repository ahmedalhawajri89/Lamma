<script setup>
/**
 * النظرة العامة.
 *
 * تُقرأ من أعلى إلى أسفل بترتيب الأسئلة التي يحملها الموظف حين يفتحها:
 *  1. كيف حالنا؟           شريط مؤشرات واحد، ولكل رقم اتجاه أشهره الأخيرة.
 *  2. ماذا ينتظرني؟        «بانتظارك» مضغوطة، بجانبها المخطط الرئيسي.
 *  3. ما الصورة الأعمق؟    بطاقة تحليل واحدة مقسومة، لا عشر بطاقات.
 *  4. من يحتاج كافلاً الآن؟ الحالات العاجلة بأسمائها وأعمارها.
 *
 * وكل ما فيها يتكيّف مع الدور: الخادم لا يرسل المال لمن لا يملكه، والشاشة
 * ترسم ما وصلها فقط. فالمشرف يرى أيتام بلده ومهامه وحالاته العاجلة.
 */
import { ref, computed, onMounted } from 'vue'
import { admin } from '@/api/endpoints'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { useQueueStore } from '@/stores/queue'
import { CATEGORICAL, STATUS } from '@/utils/palette'
import { num, money, EMPTY } from '@/utils/format'
import ChartCard from '@/components/ChartCard.vue'
import FundingBar from '@/components/FundingBar.vue'
import Sparkline from '@/components/Sparkline.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import SinceLastVisit from '@/components/SinceLastVisit.vue'
import AccountantDashboard from '@/components/AccountantDashboard.vue'

const ui = useUiStore()
const auth = useAuthStore()
const queue = useQueueStore()
const data = ref(null)
const loading = ref(true)
const months = ref(12)

async function load() {
  loading.value = true
  try {
    const res = await admin.dashboard({ months: months.value })
    data.value = res.data.data
    // «بانتظارك» تصل ضمن الاستجابة: تُحدّث أرقام الشريط الجانبي بلا طلب آخر
    queue.set(data.value.tasks)
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر تحميل اللوحة.')
  } finally {
    loading.value = false
  }
}

function setMonths(n) {
  if (months.value === n) return
  months.value = n
  load()
}

onMounted(load)

const k = computed(() => data.value?.kpis ?? {})
const monthly = computed(() => data.value?.monthly ?? [])
// من المخزن لا من الاستجابة: تتحدّث حيّةً مع الشريط الجانبي
const tasks = computed(() => queue.tasks)
const scope = computed(() => data.value?.scope ?? {})
const urgent = computed(() => data.value?.urgent_cases ?? [])

/** التحليلات تصل لمن يملكها وحده (المدير والمحاسب)، فتُرسم حين تصل فقط. */
const hasAnalytics = computed(() => monthly.value.length > 0)
const hasFinance = computed(() => k.value.revenue_month !== undefined)

const greeting = computed(() => {
  // الكلمة الأولى من الاسم بلا فاصلة أو علامة عالقة بها
  const first = auth.user?.name?.split(/[\s،,]+/)[0] ?? ''
  return `${new Date().getHours() < 12 ? 'صباح الخير' : 'مساء الخير'}${first ? `، ${first}` : ''}`
})

const today = new Intl.DateTimeFormat('ar-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())

/** سطر تحت التحية: الدور، وبلد المشرف، وتاريخ اليوم. */
const headerLine = computed(() =>
  [scope.value.role_label, scope.value.country, today].filter(Boolean).join(' · '),
)

/** ملخّص بجملة واحدة: كم ينتظر، وهل فيه عاجل. */
const queueSummary = computed(() => {
  const total = tasks.value.reduce((n, t) => n + t.count, 0)
  if (!total) return ''
  const urgentCount = tasks.value.filter((t) => t.tone === 'urgent').length
  return urgentCount ? `${tasks.value.length} مهام، ${urgentCount} منها عاجلة` : `${tasks.value.length} مهام`
})

/* ------------------------------------------------------------------
   المؤشرات: كتالوج بكل ما قد يصل، ويُعرض منه ما أرسله الخادم فعلاً
   بهذا الترتيب، أربعة على الأكثر. و`spark` حقل السلسلة الشهرية الذي
   يرسم اتجاهه، إن كانت التحليلات قد وصلت.
   ------------------------------------------------------------------ */
const KPI_CATALOG = [
  { key: 'orphans_sponsored', label: 'أيتام مكفولون', to: '/orphans', spark: 'sponsored',
    hint: (v) => `من ${num(v.orphans_total)} ${scope.value.country ? 'في بلدك' : 'في المنصة'}` },
  { key: 'monthly_recurring', label: 'الالتزام الشهري', format: 'money', to: '/sponsorships',
    hint: (v) => `${num(v.sponsorships_active)} كفالة نشطة` },
  { key: 'revenue_month', label: 'مقبوضات الشهر', format: 'money', to: '/payments', spark: 'revenue', partial: true,
    change: (v) => v.revenue_month_change },
  { key: 'disbursed_month', label: 'المصروف المعتمد هذا الشهر', format: 'money', to: '/disbursements', spark: 'disbursed', partial: true,
    hint: () => 'حتى اليوم' },
  { key: 'orphans_awaiting', label: 'ينتظرون اكتمال كفالتهم', to: '/orphans?unfunded=1' },
  { key: 'urgent_unfunded', label: 'حالات عاجلة بلا تمويل', to: '/orphans?urgent=1&unfunded=1', tone: 'urgent',
    hint: () => 'تنتظر كافلاً' },
  { key: 'visits_month', label: 'زيارات هذا الشهر', to: '/visits/log',
    hint: (v) => (v.overdue_visits ? `${num(v.overdue_visits)} متأخرة` : 'لا زيارات متأخرة') },
]

const kpiCards = computed(() =>
  KPI_CATALOG.filter((c) => k.value[c.key] !== undefined).slice(0, 4).map((c) => ({
    ...c,
    display: c.format === 'money' ? money(k.value[c.key]) : num(k.value[c.key]),
    hintText: c.hint ? c.hint(k.value) : '',
    changeValue: c.change ? c.change(k.value) : null,
    series: c.spark && hasAnalytics.value ? monthly.value.map((m) => m[c.spark]) : [],
  })),
)

/* ------------------------------------------------------------------
   المخطط الرئيسي: ثلاث قراءات في بطاقة واحدة بتبويبات، بدل ثلاث بطاقات.
   قاعدة محورٍ واحد مطبَّقة: العدد في مخطط والمال في آخر، ولا يجتمعان
   على محورين في رسم واحد.
   ------------------------------------------------------------------ */
const TABS = [
  { key: 'sponsored', label: 'الأيتام المكفولون', note: 'عدد الأيتام الذين لهم كفالة قائمة في نهاية كل شهر.' },
  { key: 'money', label: 'المقبوض والمصروف', note: 'بالدولار وعلى محور واحد. الشهر الجاري متقطّع لأنه لم يكتمل.', finance: true },
  { key: 'flow', label: 'حركة الكفالات', note: 'الجديدة فوق الخط والملغاة تحته، والفرق هو النمو الصافي.' },
]
const tabs = computed(() => TABS.filter((t) => !t.finance || hasFinance.value))
const tab = ref('sponsored')
const activeTab = computed(() => tabs.value.find((t) => t.key === tab.value) ?? tabs.value[0])

function onTabKey(event, index) {
  const step = { ArrowLeft: 1, ArrowRight: -1, Home: -index, End: tabs.value.length - 1 - index }[event.key]
  if (step === undefined) return
  event.preventDefault()
  const next = tabs.value[(index + step + tabs.value.length) % tabs.value.length]
  tab.value = next.key
  document.getElementById(`tab-${next.key}`)?.focus()
}

/** اسم الشهر وحده، والسنة عند أول المحور وعند يناير: محور مقروء بلا ميل. */
const monthName = new Intl.DateTimeFormat('ar-u-nu-latn', { month: 'long' })
const monthLabels = computed(() => monthly.value.map((m, i) => {
  const [y, mo] = m.month.split('-').map(Number)
  const name = monthName.format(new Date(y, mo - 1, 1))
  return i === 0 || mo === 1 ? `${name} ${y}` : name
}))

const lastIndex = computed(() => monthly.value.length - 1)
// القطعة الأخيرة متقطّعة: الشهر الجاري لم يكتمل
const partialSegment = { borderDash: (ctx) => (ctx.p1DataIndex === lastIndex.value ? [5, 4] : undefined) }

const lineBase = {
  borderWidth: 2,
  pointRadius: 0,
  pointHoverRadius: 5,
  pointHoverBackgroundColor: '#fff',
  pointHoverBorderWidth: 2,
  tension: 0.35,
}

const sponsoredChart = computed(() => ({
  labels: monthLabels.value,
  datasets: [{
    ...lineBase,
    label: 'أيتام مكفولون',
    data: monthly.value.map((m) => m.sponsored),
    borderColor: CATEGORICAL[0],
    backgroundColor: 'rgba(15, 92, 92, 0.07)',
    fill: true,
  }],
}))

const moneyChart = computed(() => ({
  labels: monthLabels.value,
  datasets: [
    { ...lineBase, label: 'المقبوض', data: monthly.value.map((m) => m.revenue),
      borderColor: CATEGORICAL[0], backgroundColor: CATEGORICAL[0], segment: partialSegment },
    { ...lineBase, label: 'المصروف', data: monthly.value.map((m) => m.disbursed),
      borderColor: CATEGORICAL[1], backgroundColor: CATEGORICAL[1], segment: partialSegment },
  ],
}))

const flowChart = computed(() => ({
  labels: monthLabels.value,
  datasets: [
    { label: 'كفالات جديدة', data: monthly.value.map((m) => m.new_sponsorships),
      backgroundColor: CATEGORICAL[0], borderRadius: 4, barPercentage: 0.6, categoryPercentage: 0.8 },
    { label: 'كفالات ملغاة', data: monthly.value.map((m) => -m.cancelled_sponsorships),
      backgroundColor: CATEGORICAL[1], borderRadius: 4, barPercentage: 0.6, categoryPercentage: 0.8 },
  ],
}))

const chartOptions = computed(() => ({
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { display: activeTab.value.key !== 'sponsored', position: 'top', align: 'start' },
    tooltip: {
      callbacks: {
        title: (items) => monthly.value[items[0].dataIndex]?.label ?? '',
        label: (item) => {
          const v = Math.abs(item.raw)
          const value = activeTab.value.key === 'money' ? money(v) : num(v)
          return ` ${item.dataset.label}: ${value}`
        },
      },
    },
  },
  scales: {
    x: { ticks: { maxRotation: 0, autoSkip: true, autoSkipPadding: 18 } },
    y: {
      beginAtZero: activeTab.value.key !== 'sponsored',
      ticks: {
        maxTicksLimit: 5,
        callback: (v) => (activeTab.value.key === 'money' ? `$${num(v)}` : num(Math.abs(v))),
      },
    },
  },
}))

const activeChart = computed(() => ({
  sponsored: { type: 'line', data: sponsoredChart.value },
  money: { type: 'line', data: moneyChart.value },
  flow: { type: 'bar', data: flowChart.value },
}[activeTab.value?.key ?? 'sponsored']))

/* ------------------------------------------------------------------
   الصورة الأعمق: أشرطة بنسبها مكتوبة بجانبها، لا مخططات تحتاج مفتاحاً.
   ------------------------------------------------------------------ */
const spending = computed(() => data.value?.spending ?? [])

const buckets = computed(() => {
  const rows = data.value?.funding_buckets ?? []
  const total = rows.reduce((n, r) => n + r.value, 0) || 1
  const tones = [STATUS.critical, STATUS.serious, STATUS.warning, STATUS.good]
  return rows.map((r, i) => ({ ...r, color: tones[i], share: (r.value / total) * 100 }))
})

const countries = computed(() => {
  const rows = (data.value?.by_country ?? []).slice(0, 6)
  const max = Math.max(1, ...rows.map((r) => r.value))
  return rows.map((r) => ({ ...r, share: (r.value / max) * 100 }))
})

const retention = computed(() => data.value?.retention ?? {})
</script>

<template>
  <div class="dash">
    <!-- الرأس -->
    <header class="flex flex-wrap items-end justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl text-sand-900">{{ greeting }}</h1>
        <p class="text-sm text-sand-500 mt-1">{{ headerLine }}</p>
      </div>

      <div v-if="hasAnalytics" class="seg" role="group" aria-label="مدة المخططات">
        <button
          v-for="n in [6, 12, 24]" :key="n" type="button" class="seg__btn"
          :aria-pressed="months === n" @click="setMonths(n)"
        >
          <span class="num">{{ n }}</span> {{ n === 6 ? 'أشهر' : 'شهراً' }}
        </button>
      </div>
    </header>

    <!-- ما جرى والموظف غائب: يظهر حتى يقول «اطّلعت» -->
    <SinceLastVisit />

    <!-- المحاسب: لوحة المال وحدها، لا صورة المنصة كلها -->
    <AccountantDashboard v-if="data?.finance" :finance="data.finance" :monthly="monthly" :spending="spending" />

    <template v-else>
    <!-- ١. المؤشرات: شريط واحد بخلايا مفصولة -->
    <section class="card kpis mb-6" aria-label="المؤشرات الرئيسية">
      <template v-if="loading && !data">
        <div v-for="n in 4" :key="n" class="kpi" aria-hidden="true">
          <div class="h-3 w-24 rounded bg-sand-100" />
          <div class="h-7 w-28 rounded bg-sand-100 mt-3" />
          <div class="h-2.5 w-20 rounded bg-sand-100 mt-3" />
        </div>
      </template>

      <template v-else>
      <RouterLink v-for="c in kpiCards" :key="c.key" :to="c.to" class="kpi group">
        <span class="flex items-center justify-between gap-2 text-sm text-sand-600">
          {{ c.label }}
          <BaseIcon name="chevron" :size="14" class="kpi__go" style="transform: scaleX(-1)" />
        </span>

        <span class="flex items-end justify-between gap-3 mt-2 min-w-0">
          <span class="num text-[1.4rem] sm:text-[1.75rem] leading-none font-semibold min-w-0" :class="c.tone === 'urgent' && k[c.key] ? 'text-rose-700' : 'text-sand-900'">
            {{ c.display }}
          </span>
          <span v-if="c.series.length" class="hidden sm:block w-24 shrink-0">
            <Sparkline :values="c.series" :partial-last="c.partial" :label="`اتجاه ${c.label} في آخر ${months} شهراً`" />
          </span>
        </span>

        <span v-if="c.changeValue !== null" class="mt-2.5 text-xs flex items-center gap-1.5">
          <span
            class="num inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-semibold"
            :class="c.changeValue >= 0 ? 'bg-olive-50 text-olive-700' : 'bg-rose-600/8 text-rose-700'"
          >
            {{ c.changeValue > 0 ? '+' : '' }}{{ c.changeValue }}%
          </span>
          <span class="text-sand-500">عن الفترة نفسها من الشهر الماضي</span>
        </span>
        <span v-else-if="c.hintText" class="mt-2.5 text-xs text-sand-500">{{ c.hintText }}</span>
      </RouterLink>
      </template>
    </section>

    <!-- ٢. «بانتظارك» وبجانبها المخطط الرئيسي (أو الحالات العاجلة لمن لا تحليلات له) -->
    <div class="grid gap-4 lg:grid-cols-5 mb-6" :class="hasAnalytics ? '' : 'items-start'">
      <section class="card lg:col-span-2 overflow-hidden" aria-labelledby="queue-title">
        <header class="flex items-baseline justify-between gap-3 px-5 pt-4 pb-3">
          <h2 id="queue-title" class="font-semibold">بانتظارك</h2>
          <span v-if="!loading && queueSummary" class="text-xs text-sand-500">{{ queueSummary }}</span>
        </header>

        <ul v-if="loading && !data" class="px-2 pb-2" aria-hidden="true">
          <li v-for="n in 4" :key="n" class="flex items-center gap-3 px-3 py-3">
            <div class="size-9 rounded-lg bg-sand-100" />
            <div class="flex-1 space-y-2">
              <div class="h-3 w-2/3 rounded bg-sand-100" />
              <div class="h-2.5 w-1/2 rounded bg-sand-100" />
            </div>
          </li>
        </ul>

        <ul v-else-if="tasks.length" class="px-2 pb-2">
          <li v-for="t in tasks" :key="t.key">
            <RouterLink
              :to="{ path: t.to.path, query: t.to.query }"
              class="task group flex items-center gap-3 rounded-xl px-3 py-2.5"
            >
              <span
                class="num shrink-0 min-w-9 h-9 px-1.5 grid place-items-center rounded-lg text-[0.95rem] font-semibold"
                :class="t.tone === 'urgent' ? 'bg-rose-600/10 text-rose-700' : 'bg-clay-50 text-clay-700'"
              >
                {{ t.count }}
              </span>
              <span class="flex-1 min-w-0">
                <span class="block text-[0.925rem] font-medium text-sand-900 truncate">{{ t.title }}</span>
                <span class="block text-xs text-sand-500 truncate">{{ t.hint }}</span>
              </span>
              <BaseIcon name="chevron" :size="16" class="task__go shrink-0 text-sand-400" style="transform: scaleX(-1)" />
            </RouterLink>
          </li>
        </ul>

        <div v-else class="flex items-center gap-3 px-5 pb-6 pt-2 text-sand-600">
          <span class="size-10 shrink-0 grid place-items-center rounded-full bg-olive-50 text-olive-700">
            <BaseIcon name="check" :size="20" />
          </span>
          <span>
            <span class="block font-medium text-sand-900">لا شيء ينتظرك اليوم</span>
            <span class="block text-sm">كل ما يخصّك مُنجز، وستظهر هنا أي مهمة جديدة.</span>
          </span>
        </div>
      </section>

      <!-- المخطط الرئيسي بتبويبات -->
      <section v-if="hasAnalytics" class="card lg:col-span-3 p-5 flex flex-col" aria-label="الاتجاهات">
        <div class="tabs mb-1" role="tablist" aria-label="اختر القراءة">
          <button
            v-for="(t, i) in tabs" :id="`tab-${t.key}`" :key="t.key" type="button" role="tab"
            class="tabs__btn" :aria-selected="activeTab.key === t.key" :tabindex="activeTab.key === t.key ? 0 : -1"
            aria-controls="trend-panel" @click="tab = t.key" @keydown="onTabKey($event, i)"
          >
            {{ t.label }}
          </button>
        </div>

        <div id="trend-panel" class="flex-1 flex flex-col justify-end" role="tabpanel" :aria-labelledby="`tab-${activeTab.key}`">
          <p class="text-xs text-sand-500 mt-3 mb-3">{{ activeTab.note }}</p>
          <ChartCard :key="activeTab.key" :type="activeChart.type" :data="activeChart.data" :options="chartOptions" :height="268" />
        </div>
      </section>

      <!-- لمن لا تحليلات له: الحالات العاجلة في بلده مكان المخطط -->
      <section v-else class="card lg:col-span-3" aria-labelledby="urgent-title">
        <header class="flex items-baseline justify-between gap-3 px-5 pt-4 pb-3">
          <h2 id="urgent-title" class="font-semibold">
            حالات عاجلة تنتظر كافلاً<template v-if="scope.country"> في {{ scope.country }}</template>
          </h2>
          <RouterLink :to="{ path: '/orphans', query: { urgent: 1, unfunded: 1 } }" class="text-sm text-clay-700 hover:underline shrink-0">
            عرض الكل
          </RouterLink>
        </header>
        <ul v-if="urgent.length" class="px-2 pb-2 grid sm:grid-cols-2">
          <li v-for="o in urgent" :key="o.id">
            <RouterLink :to="`/orphans/${o.id}`" class="task flex items-center gap-3 rounded-xl px-3 py-2.5">
              <img v-if="o.photo_url" :src="o.photo_url" alt="" class="size-11 shrink-0 rounded-lg object-cover bg-sand-100" loading="lazy" />
              <span v-else class="size-11 shrink-0 rounded-lg bg-clay-50 text-clay-700 grid place-items-center font-semibold" aria-hidden="true">{{ o.first_name?.charAt(0) }}</span>
              <span class="flex-1 min-w-0">
                <span class="flex items-baseline justify-between gap-2">
                  <span class="text-sm font-medium text-sand-900 truncate">{{ o.first_name }}</span>
                  <span class="text-xs text-sand-500 shrink-0">{{ o.age }} سنوات</span>
                </span>
                <FundingBar :percent="o.funded_percent" class="mt-1.5" />
              </span>
            </RouterLink>
          </li>
        </ul>
        <p v-else-if="!loading" class="text-sm text-sand-500 px-5 pb-6">لا حالات عاجلة بلا تمويل.</p>
      </section>
    </div>

    <!-- ٣. الصورة الأعمق: بطاقة واحدة بأربعة أقسام -->
    <section v-if="hasAnalytics" class="card deep mb-6" aria-label="تحليل أعمق">
      <div v-if="spending.length" class="deep__pane">
        <h2 class="deep__title">أين يذهب المال</h2>
        <ul class="space-y-3">
          <li v-for="s in spending" :key="s.label">
            <span class="flex justify-between text-sm">
              <span class="text-sand-700">{{ s.label }}</span>
              <span class="num font-semibold text-sand-900">{{ s.value }}%</span>
            </span>
            <span class="bar mt-1.5"><span class="bar__fill" :style="{ width: `${s.value}%` }" /></span>
          </li>
        </ul>
      </div>

      <div class="deep__pane">
        <h2 class="deep__title">الأيتام حسب اكتمال التمويل</h2>
        <!-- شريط واحد مقسوم: الكل في سطر، والتفصيل تحته -->
        <span class="stack" role="img" :aria-label="buckets.map((b) => `${b.label} ${b.value}`).join('، ')">
          <span v-for="b in buckets" :key="b.label" :style="{ width: `${b.share}%`, background: b.color }" />
        </span>
        <ul class="mt-4 space-y-2.5">
          <li v-for="b in buckets" :key="b.label" class="flex items-center gap-2 text-sm">
            <span class="size-2.5 rounded-sm shrink-0" :style="{ background: b.color }" aria-hidden="true" />
            <span class="flex-1 text-sand-700">{{ b.label }}</span>
            <span class="num font-semibold text-sand-900">{{ b.value }}</span>
          </li>
        </ul>
      </div>

      <div class="deep__pane">
        <h2 class="deep__title">أكثر الدول حالات</h2>
        <ul class="space-y-3">
          <li v-for="c in countries" :key="c.label">
            <span class="flex justify-between text-sm">
              <span class="text-sand-700">{{ c.label }}</span>
              <span class="num font-semibold text-sand-900">{{ c.value }}</span>
            </span>
            <span class="bar mt-1.5"><span class="bar__fill" :style="{ width: `${c.share}%` }" /></span>
          </li>
        </ul>
      </div>

      <div class="deep__pane">
        <h2 class="deep__title">استمرار الكفالات</h2>
        <p class="flex items-baseline gap-2">
          <span class="num text-[1.75rem] leading-none font-semibold" :class="(retention.churn_percent ?? 0) > 20 ? 'text-rose-700' : 'text-sand-900'">
            {{ retention.churn_percent ?? EMPTY }}%
          </span>
          <span class="text-sm text-sand-500">معدّل الانقطاع</span>
        </p>
        <dl class="mt-4 space-y-2 text-sm">
          <div class="flex justify-between gap-2">
            <dt class="text-sand-600">عمر الكفالة القائمة</dt>
            <dd class="font-semibold text-sand-900"><span class="num">{{ retention.avg_active_months ?? EMPTY }}</span> شهراً</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt class="text-sand-600">عمر الكفالة المنتهية</dt>
            <dd class="font-semibold text-sand-900"><span class="num">{{ retention.avg_lifetime_months ?? EMPTY }}</span> شهراً</dd>
          </div>
        </dl>
        <template v-if="retention.reasons?.length">
          <p class="text-xs text-sand-500 mt-4 mb-1.5">أكثر أسباب الإلغاء</p>
          <ul class="space-y-1 text-sm">
            <li v-for="r in retention.reasons.slice(0, 3)" :key="r.label" class="flex justify-between gap-2">
              <span class="text-sand-700 truncate">{{ r.label }}</span>
              <span class="num text-sand-500">{{ r.value }}</span>
            </li>
          </ul>
        </template>
      </div>
    </section>

    <!-- ٤. الحالات العاجلة (لمن له تحليلات؛ المشرف يراها أعلاه) -->
    <section v-if="hasAnalytics" class="card" aria-labelledby="urgent-title">
      <header class="flex items-baseline justify-between gap-3 px-5 pt-4 pb-3">
        <h2 id="urgent-title" class="font-semibold">حالات عاجلة تنتظر كافلاً</h2>
        <RouterLink :to="{ path: '/orphans', query: { urgent: 1, unfunded: 1 } }" class="text-sm text-clay-700 hover:underline shrink-0">
          عرض الكل
        </RouterLink>
      </header>

      <ul v-if="urgent.length" class="px-2 pb-2 grid sm:grid-cols-2 xl:grid-cols-3">
        <li v-for="o in urgent" :key="o.id">
          <RouterLink :to="`/orphans/${o.id}`" class="task flex items-center gap-3 rounded-xl px-3 py-2.5">
            <img v-if="o.photo_url" :src="o.photo_url" alt="" class="size-11 shrink-0 rounded-lg object-cover bg-sand-100" loading="lazy" />
            <span v-else class="size-11 shrink-0 rounded-lg bg-clay-50 text-clay-700 grid place-items-center font-semibold" aria-hidden="true">{{ o.first_name?.charAt(0) }}</span>
            <span class="flex-1 min-w-0">
              <span class="flex items-baseline justify-between gap-2">
                <span class="text-sm font-medium text-sand-900 truncate">
                  {{ o.first_name }}
                  <span class="text-xs text-sand-500 font-normal">{{ o.age }} سنوات</span>
                </span>
                <span class="text-xs text-sand-500 shrink-0">
                  <span class="num">{{ money(o.monthly_need) }}</span> شهرياً
                </span>
              </span>
              <FundingBar :percent="o.funded_percent" class="mt-1.5" />
            </span>
          </RouterLink>
        </li>
      </ul>

      <p v-else-if="!loading" class="text-sm text-sand-500 px-5 pb-6">لا حالات عاجلة بلا تمويل.</p>
    </section>
    </template>
  </div>
</template>

<style scoped>
/* ── المؤشرات: خلايا داخل بطاقة واحدة، تفصلها خطوط لا فراغات ── */
.kpis {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (min-width: 1280px) {
  .kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

.kpi {
  display: flex;
  flex-direction: column;
  min-width: 0;
  padding: 1rem;
  border-color: var(--color-sand-100);
  border-style: solid;
  border-width: 0;
  transition: background-color 0.15s ease;
}

@media (min-width: 640px) { .kpi { padding: 1.1rem 1.25rem; } }

a.kpi:hover { background: var(--color-sand-25, #fbfaf7); }
a.kpi:focus-visible { outline: 2px solid var(--color-clay-600); outline-offset: -2px; }

/* خط فاصل بين الخلايا: عمودي بين الجيران، أفقي بين الصفّين على الشاشات الأضيق */
.kpi:nth-child(2n) { border-inline-start-width: 1px; }
.kpi:nth-child(n + 3) { border-top-width: 1px; }

@media (min-width: 1280px) {
  .kpi:nth-child(n) { border-inline-start-width: 1px; border-top-width: 0; }
  .kpi:first-child { border-inline-start-width: 0; }
}

.kpi__go {
  color: var(--color-sand-300, #cfc8b8);
  opacity: 0;
  transition: opacity 0.15s ease;
}

a.kpi:hover .kpi__go,
a.kpi:focus-visible .kpi__go { opacity: 1; }

/* ── صفوف «بانتظارك» والحالات ── */
.task { transition: background-color 0.15s ease; }
.task:hover { background: var(--color-sand-50); }
.task:focus-visible { outline: 2px solid var(--color-clay-600); outline-offset: -2px; }
.task__go { transition: transform 0.15s ease, color 0.15s ease; }
.task:hover .task__go { color: var(--color-clay-700); transform: scaleX(-1) translateX(2px) !important; }

/* ── مدة المخططات: مفتاح مقسوم لا قائمة منسدلة ── */
.seg {
  display: inline-flex;
  padding: 3px;
  border: 1px solid var(--color-sand-200);
  border-radius: 0.7rem;
  background: var(--color-sand-0, #fff);
}

.seg__btn {
  padding: 0.3rem 0.8rem;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  color: var(--color-sand-600);
  transition: background-color 0.15s ease, color 0.15s ease;
}

.seg__btn:hover { color: var(--color-sand-900); }
.seg__btn[aria-pressed='true'] { background: var(--color-clay-600); color: #fff; }
.seg__btn:focus-visible { outline: 2px solid var(--color-clay-600); outline-offset: 2px; }

/* ── تبويبات المخطط: خط تحت المختار ── */
.tabs {
  display: flex;
  gap: 1.25rem;
  overflow-x: auto;
  border-bottom: 1px solid var(--color-sand-100);
  scrollbar-width: none;
}

.tabs__btn {
  position: relative;
  padding: 0 0 0.7rem;
  white-space: nowrap;
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--color-sand-500);
  transition: color 0.15s ease;
}

.tabs__btn:hover { color: var(--color-sand-900); }
.tabs__btn[aria-selected='true'] { color: var(--color-clay-700); }

.tabs__btn::after {
  content: '';
  position: absolute;
  inset-inline: 0;
  bottom: -1px;
  height: 2px;
  border-radius: 2px;
  background: var(--color-clay-600);
  transform: scaleX(0);
  transition: transform 0.2s ease;
}

.tabs__btn[aria-selected='true']::after { transform: scaleX(1); }
.tabs__btn:focus-visible { outline: 2px solid var(--color-clay-600); outline-offset: 4px; border-radius: 4px; }

/* ── الصورة الأعمق: أقسام مفصولة بخطوط داخل بطاقة واحدة ── */
.deep {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
}

@media (min-width: 768px) { .deep { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 1280px) { .deep { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

.deep__pane {
  padding: 1.25rem;
  border-top: 1px solid var(--color-sand-100);
}

.deep__pane:first-child { border-top: 0; }

@media (min-width: 768px) {
  .deep__pane:nth-child(2) { border-top: 0; }
  .deep__pane:nth-child(2n) { border-inline-start: 1px solid var(--color-sand-100); }
}

@media (min-width: 1280px) {
  .deep__pane { border-top: 0; }
  .deep__pane + .deep__pane { border-inline-start: 1px solid var(--color-sand-100); }
}

.deep__title {
  margin-bottom: 1rem;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-sand-900);
}

.bar {
  display: block;
  height: 6px;
  border-radius: 999px;
  background: var(--color-sand-100);
  overflow: hidden;
}

.bar__fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--color-clay-600);
}

.stack {
  display: flex;
  gap: 2px;
  height: 12px;
  border-radius: 999px;
  overflow: hidden;
}

.stack > span { min-width: 4px; }

@media (prefers-reduced-motion: reduce) {
  .kpi, .kpi__go, .task, .task__go, .seg__btn, .tabs__btn, .tabs__btn::after { transition: none; }
}
</style>

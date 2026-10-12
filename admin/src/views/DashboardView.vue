<script setup>
import { ref, computed, onMounted } from 'vue'
import { admin } from '@/api/endpoints'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { useQueueStore } from '@/stores/queue'
import { CATEGORICAL, STATUS, INK } from '@/utils/palette'
import { num, money, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import StatCard from '@/components/StatCard.vue'
import ChartCard from '@/components/ChartCard.vue'
import FundingBar from '@/components/FundingBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const ui = useUiStore()
const auth = useAuthStore()
const queue = useQueueStore()
const data = ref(null)
const loading = ref(true)
const months = ref(12)
const showSpendingTable = ref(false)

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

onMounted(load)

const k = computed(() => data.value?.kpis ?? {})
const monthly = computed(() => data.value?.monthly ?? [])
const tasks = computed(() => data.value?.tasks ?? [])
const scope = computed(() => data.value?.scope ?? {})

/** التحليلات تصل لمن يملكها وحده (المدير والمحاسب)، فتُرسم حين تصل فقط. */
const hasAnalytics = computed(() => monthly.value.length > 0)
const hasFinance = computed(() => k.value.revenue_month !== undefined)
const hasSpending = computed(() => (data.value?.spending ?? []).length > 0)

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

/**
 * المؤشرات: كتالوج بكل ما قد يصل، ويُعرض منه ما أرسله الخادم فعلاً بهذا
 * الترتيب، أربعة على الأكثر. فتتكيّف البطاقات مع الدور تلقائياً: المدير
 * والمحاسب يريان المال، والمشرف يرى أيتام بلده وزياراته.
 */
const KPI_CATALOG = [
  { key: 'orphans_sponsored', label: 'أيتام مكفولون', icon: 'children', tone: 'olive', to: '/orphans',
    hint: (v) => `من ${num(v.orphans_total)} ${scope.value.country ? 'في بلدك' : 'في المنصة'}` },
  { key: 'monthly_recurring', label: 'الالتزام الشهري', icon: 'handshake', tone: 'clay', format: 'money', to: '/sponsorships',
    hint: (v) => `${num(v.sponsorships_active)} كفالة نشطة` },
  { key: 'revenue_month', label: 'مقبوضات الشهر', icon: 'receipt', tone: 'teal', format: 'money', to: '/payments',
    change: (v) => v.revenue_month_change },
  { key: 'disbursed_month', label: 'المصروف المعتمد هذا الشهر', icon: 'wallet', tone: 'amber', format: 'money', to: '/disbursements' },
  { key: 'orphans_awaiting', label: 'ينتظرون اكتمال كفالتهم', icon: 'clock', tone: 'amber', to: '/orphans?unfunded=1' },
  { key: 'urgent_unfunded', label: 'حالات عاجلة بلا تمويل', icon: 'alert', tone: 'rose', to: '/orphans?urgent=1&unfunded=1',
    hint: () => 'تنتظر كافلاً' },
  { key: 'visits_month', label: 'زيارات هذا الشهر', icon: 'map', tone: 'teal', to: '/visits' },
]

const kpiCards = computed(() =>
  KPI_CATALOG.filter((c) => k.value[c.key] !== undefined).slice(0, 4),
)

/* ------------------------------------------------------------------
   المخططات.
   قاعدة محورٍ واحد مطبَّقة: عدد الأيتام المكفولين في مخطط، والمال في
   مخطط آخر. وضعهما على محورين في رسم واحد أشهر خطأ في تصوير البيانات —
   يجعل تقاطع الخطين يبدو ذا معنى وهو من اختيار المقياس لا من البيانات.
   ------------------------------------------------------------------ */

// سلسلة واحدة: لا صندوق مفاتيح، العنوان يسمّيها
const sponsoredChart = computed(() => ({
  labels: monthly.value.map((m) => m.label),
  datasets: [{
    label: 'أيتام مكفولون',
    data: monthly.value.map((m) => m.sponsored),
    borderColor: CATEGORICAL[0],
    backgroundColor: 'rgba(15, 92, 92, 0.08)',
    borderWidth: 2,
    pointRadius: 0,
    pointHoverRadius: 5,
    tension: 0.35,
    fill: true,
  }],
}))

// سلسلتان بالعدد نفسه — محور واحد صحيح
const flowChart = computed(() => ({
  labels: monthly.value.map((m) => m.label),
  datasets: [
    {
      label: 'كفالات جديدة',
      data: monthly.value.map((m) => m.new_sponsorships),
      backgroundColor: STATUS.good,
      borderRadius: 4,
      // فجوة ٢ بكسل بين الأعمدة المتجاورة
      barPercentage: 0.72,
      categoryPercentage: 0.78,
    },
    {
      label: 'كفالات ملغاة',
      data: monthly.value.map((m) => -m.cancelled_sponsorships),
      backgroundColor: STATUS.critical,
      borderRadius: 4,
      barPercentage: 0.72,
      categoryPercentage: 0.78,
    },
  ],
}))

// سلسلتان بالعملة نفسها — محور واحد صحيح
const moneyChart = computed(() => ({
  labels: monthly.value.map((m) => m.label),
  datasets: [
    {
      label: 'المقبوض',
      data: monthly.value.map((m) => m.revenue),
      borderColor: CATEGORICAL[0],
      backgroundColor: 'transparent',
      borderWidth: 2,
      pointRadius: 0,
      pointHoverRadius: 5,
      tension: 0.3,
    },
    {
      label: 'المصروف',
      data: monthly.value.map((m) => m.disbursed),
      borderColor: CATEGORICAL[1],
      backgroundColor: 'transparent',
      borderWidth: 2,
      borderDash: [5, 4],
      pointRadius: 0,
      pointHoverRadius: 5,
      tension: 0.3,
    },
  ],
}))

const spendingChart = computed(() => {
  const rows = data.value?.spending ?? []
  return {
    labels: rows.map((s) => s.label),
    datasets: [{
      data: rows.map((s) => s.value),
      backgroundColor: rows.map((_, i) => CATEGORICAL[i % CATEGORICAL.length]),
      // حلقة بيضاء ٢ بكسل تفصل الشرائح المتلاصقة
      borderColor: INK.surface,
      borderWidth: 2,
      hoverOffset: 6,
    }],
  }
})

const bucketsChart = computed(() => {
  const rows = data.value?.funding_buckets ?? []
  return {
    labels: rows.map((b) => b.label),
    datasets: [{
      label: 'حالات',
      data: rows.map((b) => b.value),
      backgroundColor: [STATUS.critical, STATUS.serious, STATUS.warning, STATUS.good],
      borderRadius: 4,
      barPercentage: 0.65,
    }],
  }
})

const countryChart = computed(() => {
  const rows = (data.value?.by_country ?? []).slice(0, 8)
  return {
    labels: rows.map((c) => c.label),
    datasets: [{
      label: 'أيتام',
      data: rows.map((c) => c.value),
      backgroundColor: CATEGORICAL[0],
      borderRadius: 4,
      barPercentage: 0.7,
    }],
  }
})

/**
 * الأشرطة الأفقية.
 *
 * `x.reverse` نعم: الشريط ينمو من اليمين لليسار مع اتجاه القراءة.
 * `y.reverse` لا: البيانات تصل مرتّبة تنازلياً، وعكس المحور الرأسي يضع
 * الأكبر في الأسفل فيقرأ الترتيب مقلوباً.
 */
const horizontal = {
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    y: { grid: { display: false }, ticks: { font: { family: 'IBM Plex Sans Arabic', size: 11 } } },
    x: { position: 'bottom', reverse: true, grid: { color: INK.grid }, border: { display: false } },
  },
}
</script>

<template>
  <div>
    <PageHeader :title="greeting" :subtitle="headerLine">
      <template v-if="hasAnalytics" #actions>
        <label class="text-xs text-sand-600">
          مدة المخططات
          <select v-model.number="months" class="input !w-auto !py-1.5 ms-1 text-xs" @change="load">
            <option :value="6">6 أشهر</option>
            <option :value="12">12 شهراً</option>
            <option :value="24">24 شهراً</option>
          </select>
        </label>
      </template>
    </PageHeader>

    <!--
      «بانتظارك»: أول ما يراه الموظف. لكل مهمة عددها وسببها وزرّ يفتح
      الشاشة مفلترة عليها مباشرة. قائمة واحدة بفواصل، لا بطاقة لكل مهمة.
    -->
    <section class="card mb-6" aria-labelledby="queue-title">
      <header class="flex items-center justify-between px-5 pt-4 pb-3 border-b border-sand-100">
        <h2 id="queue-title" class="font-semibold">بانتظارك</h2>
        <span v-if="!loading && tasks.length" class="text-xs text-sand-500">
          <span class="num">{{ tasks.length }}</span> {{ tasks.length === 1 ? 'مهمة' : 'مهام' }}
        </span>
      </header>

      <ul v-if="loading" class="divide-y divide-sand-100" aria-hidden="true">
        <li v-for="n in 3" :key="n" class="flex items-center gap-4 px-5 py-4">
          <div class="size-11 rounded-xl bg-sand-100" />
          <div class="flex-1 space-y-2">
            <div class="h-3 w-1/3 rounded bg-sand-100" />
            <div class="h-2.5 w-1/2 rounded bg-sand-100" />
          </div>
        </li>
      </ul>

      <ul v-else-if="tasks.length" class="divide-y divide-sand-100">
        <li v-for="t in tasks" :key="t.key">
          <RouterLink
            :to="{ path: t.to.path, query: t.to.query }"
            class="group flex items-center gap-4 px-5 py-3.5 hover:bg-sand-25 transition-colors"
          >
            <span
              class="num shrink-0 min-w-11 h-11 px-2 grid place-items-center rounded-xl text-lg font-semibold"
              :class="t.tone === 'urgent' ? 'bg-rose-600/10 text-rose-700' : 'bg-clay-50 text-clay-700'"
            >
              {{ t.count }}
            </span>
            <span class="flex-1 min-w-0">
              <span class="block font-medium text-sand-900">{{ t.title }}</span>
              <span class="block text-sm text-sand-500">{{ t.hint }}</span>
            </span>
            <span class="shrink-0 inline-flex items-center gap-1 text-sm font-medium text-clay-700 group-hover:underline">
              افتح
              <BaseIcon name="chevron" :size="14" style="transform: scaleX(-1)" />
            </span>
          </RouterLink>
        </li>
      </ul>

      <div v-else class="flex items-center gap-3 px-5 py-6 text-sand-600">
        <span class="size-10 grid place-items-center rounded-full bg-olive-50 text-olive-700">
          <BaseIcon name="check" :size="20" />
        </span>
        <span>
          <span class="block font-medium text-sand-900">لا شيء ينتظرك اليوم</span>
          <span class="block text-sm">كل ما يخصّك مُنجز، وستظهر هنا أي مهمة جديدة.</span>
        </span>
      </div>
    </section>

    <!-- المؤشرات: ما يناسب الدور وحده، كما أرسله الخادم -->
    <section v-if="kpiCards.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-6" aria-label="المؤشرات الرئيسية">
      <StatCard
        v-for="c in kpiCards" :key="c.key"
        :label="c.label" :value="k[c.key]" :icon="c.icon" :tone="c.tone" :to="c.to"
        :format="c.format ?? 'number'" :hint="c.hint ? c.hint(k) : ''" :change="c.change ? c.change(k) : null"
      />
    </section>

    <template v-if="hasAnalytics">
      <div class="grid gap-4 lg:grid-cols-3 mb-4">
        <!-- سلسلة واحدة: العنوان يسمّيها فلا حاجة لصندوق مفاتيح -->
        <div class="card p-4" :class="hasSpending ? 'lg:col-span-2' : 'lg:col-span-3'">
          <h2 class="text-sm font-semibold mb-3">نمو عدد الأيتام المكفولين</h2>
          <ChartCard type="line" :data="sponsoredChart"
                     :options="{ plugins: { legend: { display: false } } }" :height="240" />
        </div>

        <div v-if="hasSpending" class="card p-4">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold">توزيع الإنفاق</h2>
            <button class="btn btn-ghost !py-1 !px-2 text-xs" type="button"
                    @click="showSpendingTable = !showSpendingTable">
              {{ showSpendingTable ? 'رسم' : 'جدول' }}
            </button>
          </div>

          <!-- عرض جدولي بديل: التزامٌ يقابل تحذير التباين على العنبر -->
          <table v-if="showSpendingTable" class="w-full text-sm">
            <caption class="sr-only">توزيع الإنفاق حسب البند</caption>
            <tbody>
              <tr v-for="(s, i) in data?.spending ?? []" :key="s.label" class="border-b border-sand-100 last:border-0">
                <td class="py-2">
                  <span class="inline-block size-2.5 rounded-sm me-2"
                        :style="{ background: CATEGORICAL[i % CATEGORICAL.length] }" aria-hidden="true" />
                  {{ s.label }}
                </td>
                <td class="py-2 text-end num font-medium">{{ s.value }}%</td>
              </tr>
            </tbody>
          </table>

          <ChartCard
            v-else type="doughnut" :data="spendingChart" :height="240"
            :options="{ cutout: '62%', plugins: { legend: { position: 'bottom' } } }"
          />
        </div>
      </div>

      <div class="grid gap-4 mb-4" :class="hasFinance ? 'lg:grid-cols-2' : ''">
        <div class="card p-4">
          <h2 class="text-sm font-semibold mb-1">حركة الكفالات</h2>
          <p class="text-xs text-sand-500 mb-3">الجديدة فوق الخط والملغاة تحته، والفرق هو النمو الصافي.</p>
          <ChartCard type="bar" :data="flowChart" :height="230" />
        </div>

        <div v-if="hasFinance" class="card p-4">
          <h2 class="text-sm font-semibold mb-1">المقبوض والمصروف</h2>
          <p class="text-xs text-sand-500 mb-3">بالدولار وعلى محور واحد، فالمقارنة بينهما صادقة.</p>
          <ChartCard type="line" :data="moneyChart" :height="230" />
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-3 mb-4">
        <div class="card p-4">
          <h2 class="text-sm font-semibold mb-3">الحالات حسب شريحة التمويل</h2>
          <ChartCard type="bar" :data="bucketsChart" :options="horizontal" :height="200" />
        </div>

        <div class="card p-4">
          <h2 class="text-sm font-semibold mb-3">أكثر الدول حالات</h2>
          <ChartCard type="bar" :data="countryChart" :options="horizontal" :height="200" />
        </div>

        <div class="card p-4">
          <h2 class="text-sm font-semibold mb-3">استمرار الكفالات</h2>
          <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
              <dt class="text-sand-600">معدّل الانقطاع</dt>
              <dd class="num font-semibold" :class="(data?.retention?.churn_percent ?? 0) > 20 ? 'text-rose-700' : 'text-olive-700'">
                {{ data?.retention?.churn_percent ?? EMPTY }}%
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sand-600">متوسط عمر الكفالة المنتهية</dt>
              <dd class="num font-semibold">{{ data?.retention?.avg_lifetime_months ?? EMPTY }} شهراً</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sand-600">متوسط عمر الكفالة القائمة</dt>
              <dd class="num font-semibold">{{ data?.retention?.avg_active_months ?? EMPTY }} شهراً</dd>
            </div>
            <div class="pt-2 border-t border-sand-100">
              <dt class="text-sand-600 mb-2">أسباب الإلغاء</dt>
              <dd v-for="r in data?.retention?.reasons ?? []" :key="r.label"
                  class="flex justify-between text-xs py-0.5">
                <span class="text-sand-700">{{ r.label }}</span>
                <span class="num text-sand-500">{{ r.value }}</span>
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </template>

    <!-- الحالات العاجلة: للمشرف في بلده وحده (يقيّدها الخادم) -->
    <section class="card p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold">
          حالات عاجلة تنتظر كافلاً<template v-if="scope.country"> في {{ scope.country }}</template>
        </h2>
        <RouterLink :to="{ path: '/orphans', query: { urgent: 1, unfunded: 1 } }" class="text-xs text-clay-700 hover:underline flex items-center gap-1">
          الكل <BaseIcon name="chevron" :size="13" style="transform: scaleX(-1)" />
        </RouterLink>
      </div>

      <ul class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
        <li v-for="o in data?.urgent_cases ?? []" :key="o.id">
          <RouterLink
            :to="`/orphans/${o.id}`"
            class="flex items-center gap-3 p-2.5 rounded-xl border border-sand-200 hover:border-clay-300 transition-colors"
          >
            <span class="size-10 shrink-0 grid place-items-center rounded-lg bg-clay-50 text-clay-700 font-semibold num">
              {{ o.age }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium truncate">
                {{ o.first_name }}
                <span class="text-xs text-sand-500 num">· {{ o.reference_code }}</span>
              </p>
              <FundingBar :percent="o.funded_percent" />
            </div>
            <span class="text-xs text-sand-500 num">{{ money(o.monthly_need) }}</span>
          </RouterLink>
        </li>
      </ul>

      <p v-if="!loading && !(data?.urgent_cases ?? []).length" class="text-sm text-sand-500 py-6 text-center">
        لا حالات عاجلة بلا تمويل.
      </p>
    </section>
  </div>
</template>

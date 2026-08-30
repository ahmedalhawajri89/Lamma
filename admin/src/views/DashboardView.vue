<script setup>
import { ref, computed, onMounted } from 'vue'
import { admin } from '@/api/endpoints'
import { useUiStore } from '@/stores/ui'
import { CATEGORICAL, STATUS, INK } from '@/utils/palette'
import { num, money, percent } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import StatCard from '@/components/StatCard.vue'
import ChartCard from '@/components/ChartCard.vue'
import FundingBar from '@/components/FundingBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const ui = useUiStore()
const data = ref(null)
const loading = ref(true)
const months = ref(12)
const showSpendingTable = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await admin.dashboard({ months: months.value })
    data.value = res.data.data
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر تحميل اللوحة.')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const k = computed(() => data.value?.kpis ?? {})
const monthly = computed(() => data.value?.monthly ?? [])

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
    backgroundColor: 'rgba(194, 65, 12, 0.08)',
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
    x: { position: 'bottom', reverse: true, grid: { color: '#e9dfd0' }, border: { display: false } },
  },
}
</script>

<template>
  <div>
    <PageHeader
      title="نظرة عامة"
      :subtitle="loading ? 'جارٍ التحميل…' : 'كل رقم هنا محسوب من قاعدة البيانات، لا مكتوب في الواجهة.'"
    >
      <template #actions>
        <label class="text-xs text-sand-600">
          المدة
          <select v-model.number="months" class="input !w-auto !py-1.5 ms-1 text-xs" @change="load">
            <option :value="6">٦ أشهر</option>
            <option :value="12">١٢ شهراً</option>
            <option :value="24">٢٤ شهراً</option>
          </select>
        </label>
      </template>
    </PageHeader>

    <!-- المؤشرات: أرقام عارية بلا رسم، لأن العدد الواحد لا يحتاج مخططاً -->
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-3" aria-label="المؤشرات الرئيسية">
      <StatCard label="أيتام مكفولون" :value="k.orphans_sponsored" icon="children" tone="olive"
                :hint="`من ${num(k.orphans_total)} في المنصة`" to="/orphans" />
      <StatCard label="الالتزام الشهري" :value="k.monthly_recurring" format="money" icon="handshake"
                tone="clay" :hint="`${num(k.sponsorships_active)} كفالة نشطة`" to="/sponsorships" />
      <StatCard label="مقبوضات الشهر" :value="k.revenue_month" format="money" icon="receipt"
                tone="teal" :change="k.revenue_month_change" to="/payments" />
      <StatCard label="حالات عاجلة بلا تمويل" :value="k.urgent_unfunded" icon="alert" tone="rose"
                hint="تنتظر كافلاً" to="/orphans?urgent=1" />
    </section>

    <!-- الطوابير: ما ينتظر قراراً بشرياً اليوم -->
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-6" aria-label="ما ينتظر إجراءً">
      <StatCard label="تقارير بانتظار الاعتماد" :value="k.queue_reports" icon="file" tone="amber" to="/reports" />
      <StatCard label="رسائل بانتظار المراجعة" :value="k.queue_messages" icon="chat" tone="amber" to="/messages" />
      <StatCard label="ملفات بانتظار التحقق" :value="k.orphans_pending_verification" icon="shield" tone="amber" to="/orphans" />
      <StatCard label="زيارات ميدانية متأخرة" :value="k.overdue_visits" icon="map" tone="rose" to="/visits" />
    </section>

    <div class="grid gap-4 lg:grid-cols-3 mb-4">
      <!-- سلسلة واحدة: العنوان يسمّيها فلا حاجة لصندوق مفاتيح -->
      <div class="card p-4 lg:col-span-2">
        <h2 class="text-sm font-semibold mb-3">نمو عدد الأيتام المكفولين</h2>
        <ChartCard type="line" :data="sponsoredChart"
                   :options="{ plugins: { legend: { display: false } } }" :height="240" />
      </div>

      <div class="card p-4">
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

    <div class="grid gap-4 lg:grid-cols-2 mb-4">
      <div class="card p-4">
        <h2 class="text-sm font-semibold mb-1">حركة الكفالات</h2>
        <p class="text-xs text-sand-500 mb-3">الجديدة فوق الخط والملغاة تحته — الفرق هو النمو الصافي.</p>
        <ChartCard type="bar" :data="flowChart" :height="230" />
      </div>

      <div class="card p-4">
        <h2 class="text-sm font-semibold mb-1">المقبوض والمصروف</h2>
        <p class="text-xs text-sand-500 mb-3">بالدولار، على محور واحد — فالمقارنة بينهما صادقة.</p>
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
              {{ data?.retention?.churn_percent ?? '—' }}%
            </dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sand-600">متوسط عمر الكفالة المنتهية</dt>
            <dd class="num font-semibold">{{ data?.retention?.avg_lifetime_months ?? '—' }} شهراً</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sand-600">متوسط عمر الكفالة القائمة</dt>
            <dd class="num font-semibold">{{ data?.retention?.avg_active_months ?? '—' }} شهراً</dd>
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

    <!-- الحالات التي تنتظر: أول ما ينبغي أن يراه المدير صباحاً -->
    <section class="card p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold">حالات عاجلة تنتظر كافلاً</h2>
        <RouterLink to="/orphans?urgent=1" class="text-xs text-clay-700 hover:underline flex items-center gap-1">
          الكل <BaseIcon name="chevron" :size="13" />
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

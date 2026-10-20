<script setup>
/**
 * لوحة المحاسب: مال الشهر وما يُتابَع منه.
 *
 * كان المحاسب يرى لوحة المدير نفسها: «حالات عاجلة تنتظر كافلاً» و«أكثر
 * الدول حالات»، ولا يرى ما يفتح يومه لأجله. هنا بالترتيب:
 *  1. تحصيل الشهر: كم دخل من المتوقع، وما فشل، وما بقي من أقساطه.
 *  2. من يُتصل به: الدفعات الفاشلة التي لم تُستدرك، بأسماء الكفلاء وأسبابها.
 *  3. الأقساط القادمة في ثلاثين يوماً، يوماً بيوم.
 *  4. المقبوض والمصروف على سنة، والصرف هذا الشهر، والتشغيل مقابل هدفه.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { CATEGORICAL } from '@/utils/palette'
import { money, num, relative } from '@/utils/format'
import ChartCard from './ChartCard.vue'
import BaseIcon from './BaseIcon.vue'

const props = defineProps({
  finance: { type: Object, required: true },
  monthly: { type: Array, default: () => [] },
  spending: { type: Array, default: () => [] },
})

const m = computed(() => props.finance.month)
const share = (v) => (m.value.expected > 0 ? `${(v / m.value.expected) * 100}%` : '0%')
const methodMax = computed(() => Math.max(1, ...m.value.by_method.map((x) => x.amount)))

/* ── الأقساط القادمة ─────────────────────────────────────────────────── */
const days = computed(() => props.finance.upcoming_days)
const dayPeak = computed(() => Math.max(1, ...days.value.map((d) => d.amount)))
const upcomingTotal = computed(() => days.value.reduce((s, d) => s + d.amount, 0))
const upcomingCount = computed(() => days.value.reduce((s, d) => s + d.count, 0))
const shortDay = new Intl.DateTimeFormat('ar-u-nu-latn', { day: 'numeric', month: 'short' })
const fmtDay = (iso) => shortDay.format(new Date(`${iso}T12:00:00`))
/** أكبر أيام الاستحقاق: لتُذكر بأسمائها تحت الأعمدة */
const busiest = computed(() => [...days.value].filter((d) => d.count).sort((a, b) => b.amount - a.amount).slice(0, 3))

/* ── المقبوض والمصروف ────────────────────────────────────────────────── */
const monthName = new Intl.DateTimeFormat('ar-u-nu-latn', { month: 'short' })
const flowChart = computed(() => ({
  labels: props.monthly.map((x) => { const [y, mo] = x.month.split('-').map(Number); return monthName.format(new Date(y, mo - 1, 1)) }),
  datasets: [
    { label: 'المقبوض', data: props.monthly.map((x) => x.revenue), backgroundColor: CATEGORICAL[0], borderRadius: 4, barPercentage: 0.7, categoryPercentage: 0.7 },
    { label: 'المصروف', data: props.monthly.map((x) => x.disbursed), backgroundColor: '#D5A85C', borderRadius: 4, barPercentage: 0.7, categoryPercentage: 0.7 },
  ],
}))
const flowOptions = {
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { display: true, position: 'top', align: 'start' },
    tooltip: { callbacks: { label: (i) => ` ${i.dataset.label}: ${money(i.raw)}` } },
  },
  scales: { x: { ticks: { maxRotation: 0 } }, y: { beginAtZero: true, ticks: { maxTicksLimit: 5, callback: (v) => `$${num(v)}` } } },
}
const yearIn = computed(() => props.monthly.reduce((s, x) => s + (x.revenue ?? 0), 0))
const yearOut = computed(() => props.monthly.reduce((s, x) => s + (x.disbursed ?? 0), 0))

const oh = computed(() => props.finance.overhead)
const over = computed(() => oh.value.actual !== null && oh.value.actual > oh.value.target)
const pos = (p) => `${Math.min(100, Math.max(0, (p / 30) * 100))}%`
const d = computed(() => props.finance.disbursements)
</script>

<template>
  <div class="space-y-5">
    <!-- ══ ١. تحصيل الشهر ══ -->
    <section class="rounded-2xl border border-sand-200 bg-sand-0 grid lg:grid-cols-[minmax(0,1fr)_19rem] overflow-hidden" aria-labelledby="accMonth">
      <div class="p-6">
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
          <h2 id="accMonth" class="font-semibold">تحصيل {{ m.label }}</h2>
          <span class="text-xs text-sand-500">اليوم <span class="num">{{ m.day }}</span> من <span class="num">{{ m.days_in_month }}</span></span>
        </div>

        <div class="flex flex-wrap items-end gap-x-6 gap-y-2 mt-4">
          <p>
            <span class="num text-4xl font-bold text-sand-900">{{ money(m.collected) }}</span>
            <span class="text-sm text-sand-500 ms-1">من <span class="num">{{ money(m.expected) }}</span> متوقعة</span>
          </p>
          <span v-if="m.rate !== null" class="num rounded-full bg-[#064e3b]/10 text-[#064e3b] px-2.5 py-1 text-sm font-semibold">{{ m.rate }}%</span>
          <span v-if="m.change !== null" class="text-xs text-sand-500">
            <span class="num font-semibold" :class="m.change >= 0 ? 'text-olive-700' : 'text-rose-700'">{{ m.change > 0 ? '+' : '' }}{{ m.change }}%</span>
            عن الفترة نفسها من الشهر الماضي
          </span>
        </div>

        <!-- المتوقع مقسوماً: ما دخل، وما فشل، وما بقي -->
        <div class="mt-5 flex h-3 rounded-full overflow-hidden bg-sand-100" aria-hidden="true">
          <span class="bg-[#064e3b]" :style="{ width: share(m.collected) }" />
          <span class="bg-rose-400" :style="{ width: share(m.unrecovered) }" />
          <span class="bg-[#D5A85C]/60" :style="{ width: share(m.upcoming) }" />
        </div>
        <dl class="mt-4 grid grid-cols-3 gap-4 text-sm">
          <div>
            <dt class="flex items-center gap-1.5 text-xs text-sand-500"><span class="size-2 rounded-full bg-[#064e3b]" />حُصّل</dt>
            <dd class="num font-semibold mt-0.5">{{ money(m.collected) }}</dd>
            <dd class="text-xs text-sand-500"><span class="num">{{ m.collected_count }}</span> دفعة</dd>
          </div>
          <RouterLink to="/payments?unrecovered=1" class="group">
            <dt class="flex items-center gap-1.5 text-xs text-sand-500"><span class="size-2 rounded-full bg-rose-400" />فشل ولم يُستدرك</dt>
            <dd class="num font-semibold mt-0.5 text-rose-700 group-hover:underline">{{ money(m.unrecovered) }}</dd>
            <dd class="text-xs text-sand-500"><span class="num">{{ m.unrecovered_count }}</span> دفعة</dd>
          </RouterLink>
          <div>
            <dt class="flex items-center gap-1.5 text-xs text-sand-500"><span class="size-2 rounded-full bg-[#D5A85C]/60" />باقٍ هذا الشهر</dt>
            <dd class="num font-semibold mt-0.5">{{ money(m.upcoming) }}</dd>
            <dd class="text-xs text-sand-500">{{ m.upcoming_count ? `${m.upcoming_count} قسطاً قادماً` : 'لا أقساط باقية' }}</dd>
          </div>
        </dl>
        <p v-if="m.refunded" class="text-xs text-sand-500 mt-3">واستُرد هذا الشهر <span class="num font-semibold text-sand-700">{{ money(m.refunded) }}</span>.</p>
      </div>

      <!-- بأي وسيلة دخل -->
      <div class="border-t lg:border-t-0 lg:border-s border-sand-100 bg-sand-25 p-6">
        <p class="text-sm font-semibold mb-4">بأي وسيلة دخل</p>
        <ul class="space-y-3.5">
          <li v-for="x in m.by_method" :key="x.method">
            <div class="flex justify-between text-sm"><span>{{ x.label }}</span><span class="num font-medium">{{ money(x.amount) }}</span></div>
            <div class="h-1.5 rounded-full bg-sand-100 mt-1.5"><div class="h-full rounded-full bg-[#176b56]" :style="{ width: `${(x.amount / methodMax) * 100}%` }" /></div>
            <p class="text-xs text-sand-500 mt-1"><span class="num">{{ x.count }}</span> دفعة</p>
          </li>
          <li v-if="!m.by_method.length" class="text-sm text-sand-500">لا دفعات بعد هذا الشهر.</li>
        </ul>
      </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-5">
      <!-- ══ ٢. من يُتصل به ══ -->
      <section class="lg:col-span-3 rounded-2xl border border-sand-200 bg-sand-0 flex flex-col" aria-labelledby="accRecover">
        <header class="flex flex-wrap items-baseline gap-x-3 px-5 pt-5 pb-3">
          <h2 id="accRecover" class="font-semibold">دفعات تنتظر استدراكاً</h2>
          <span class="text-xs text-sand-500">فشلت، ولم تأتِ بعدها دفعة ناجحة على الكفالة نفسها</span>
        </header>
        <ul v-if="finance.to_recover.length" class="divide-y divide-sand-100 flex-1">
          <li v-for="p in finance.to_recover" :key="p.id" class="flex items-center gap-3 px-5 py-3">
            <span class="size-9 shrink-0 rounded-full bg-rose-100 text-rose-700 grid place-items-center text-sm font-semibold" aria-hidden="true">{{ (p.sponsor ?? '؟').trim().charAt(0) }}</span>
            <div class="min-w-0 flex-1">
              <RouterLink :to="`/sponsors/${p.sponsor_id}`" class="text-sm font-medium text-sand-900 hover:text-clay-700">{{ p.sponsor }}</RouterLink>
              <p class="text-xs text-sand-500 truncate"><span class="num">{{ p.sponsor_code }}</span> · {{ p.reason || 'بلا سبب من البوابة' }}</p>
            </div>
            <div class="text-end shrink-0">
              <p class="num text-sm font-semibold text-rose-700">{{ money(p.amount) }}</p>
              <p class="text-xs text-sand-500">{{ relative(p.failed_at) }}</p>
            </div>
          </li>
        </ul>
        <div v-else class="flex-1 flex items-center gap-3 px-5 pb-6 text-sand-600">
          <span class="size-10 shrink-0 grid place-items-center rounded-full bg-olive-50 text-olive-700"><BaseIcon name="check" :size="20" /></span>
          <span><span class="block font-medium text-sand-900">لا دفعات معلّقة</span><span class="text-sm">كل ما فشل استُدرك بدفعة بعده.</span></span>
        </div>
        <RouterLink
          v-if="finance.to_recover_total.count" to="/payments?unrecovered=1"
          class="flex items-center justify-between px-5 py-3 border-t border-sand-100 text-sm text-clay-700 hover:bg-sand-25 rounded-b-2xl"
        >
          <span>كل ما ينتظر استدراكاً: <span class="num font-semibold">{{ finance.to_recover_total.count }}</span> بـ <span class="num font-semibold">{{ money(finance.to_recover_total.amount) }}</span></span>
          <BaseIcon name="chevron" :size="15" style="transform: scaleX(-1)" />
        </RouterLink>
      </section>

      <!-- ══ ٣. الأقساط القادمة ══ -->
      <section class="lg:col-span-2 rounded-2xl border border-sand-200 bg-sand-0 p-5 flex flex-col" aria-labelledby="accUpcoming">
        <h2 id="accUpcoming" class="font-semibold">الأقساط القادمة</h2>
        <p class="text-xs text-sand-500">في الثلاثين يوماً القادمة</p>
        <p class="mt-3"><span class="num text-2xl font-bold">{{ money(upcomingTotal) }}</span> <span class="text-sm text-sand-500">من <span class="num">{{ upcomingCount }}</span> قسطاً</span></p>

        <div class="mt-4 flex items-end gap-[3px] h-20" role="img" :aria-label="`أقساط ${upcomingCount} موزّعة على 30 يوماً`">
          <span
            v-for="x in days" :key="x.date" class="flex-1 rounded-t-[3px]"
            :class="x.count ? 'bg-[#176b56]/70' : 'bg-sand-100'"
            :style="{ height: x.count ? `${Math.max(8, (x.amount / dayPeak) * 100)}%` : '3px' }"
            :title="`${fmtDay(x.date)}: ${x.count} قسط · ${money(x.amount)}`"
          />
        </div>
        <div class="flex justify-between text-[0.7rem] text-sand-400 mt-1.5"><span>اليوم</span><span>{{ fmtDay(days.at(-1).date) }}</span></div>

        <ul v-if="busiest.length" class="mt-4 space-y-1.5 text-sm">
          <li v-for="x in busiest" :key="x.date" class="flex justify-between">
            <span class="text-sand-600">{{ fmtDay(x.date) }} · <span class="num">{{ x.count }}</span> قسط</span>
            <span class="num font-medium">{{ money(x.amount) }}</span>
          </li>
        </ul>
        <p v-if="finance.overdue.count" class="mt-auto pt-4 text-sm text-rose-700">
          <span class="num font-semibold">{{ finance.overdue.count }}</span> كفالة فات موعد قسطها (<span class="num">{{ money(finance.overdue.amount) }}</span>)
        </p>
        <RouterLink v-else to="/sponsorships?due_soon=1" class="mt-auto pt-4 text-sm text-clay-700 hover:underline">لا أقساط متأخرة · اعرض ما يستحق قريباً</RouterLink>
      </section>
    </div>

    <div class="grid gap-5 lg:grid-cols-5">
      <!-- ══ ٤. المقبوض والمصروف ══ -->
      <section v-if="monthly.length" class="lg:col-span-3 rounded-2xl border border-sand-200 bg-sand-0 p-5" aria-labelledby="accFlow">
        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 mb-3">
          <h2 id="accFlow" class="font-semibold">المقبوض والمصروف</h2>
          <span class="text-xs text-sand-500">آخر <span class="num">{{ monthly.length }}</span> شهراً:
            دخل <span class="num font-semibold text-sand-800">{{ money(yearIn) }}</span> وصُرف <span class="num font-semibold text-sand-800">{{ money(yearOut) }}</span></span>
        </div>
        <ChartCard type="bar" :data="flowChart" :options="flowOptions" :height="400" />
      </section>

      <!-- ══ الصرف والتشغيل ══ -->
      <section class="lg:col-span-2 rounded-2xl border border-sand-200 bg-sand-0 p-5 space-y-5" aria-labelledby="accSpend">
        <div>
          <h2 id="accSpend" class="font-semibold">الصرف هذا الشهر</h2>
          <p class="mt-2"><span class="num text-2xl font-bold">{{ money(d.month_total) }}</span> <span class="text-sm text-sand-500">معتمَد</span></p>
          <ul class="mt-3 space-y-2 text-sm">
            <li>
              <RouterLink to="/disbursements?status=pending" class="flex justify-between hover:text-clay-700">
                <span class="text-sand-600">بانتظار الاعتماد</span>
                <span class="num" :class="d.pending_count ? 'font-semibold text-[#a8741a]' : 'text-sand-500'">{{ d.pending_count ? `${d.pending_count} · ${money(d.pending_amount)}` : 'لا شيء' }}</span>
              </RouterLink>
            </li>
            <li>
              <RouterLink to="/disbursements?no_receipt=1" class="flex justify-between hover:text-clay-700">
                <span class="text-sand-600">بلا إيصال هذا الشهر</span>
                <span class="num" :class="d.no_receipt_month ? 'font-semibold text-[#a8741a]' : 'text-sand-500'">{{ d.no_receipt_month ? `${d.no_receipt_month} من ${d.month_count}` : 'كلها مرفقة' }}</span>
              </RouterLink>
            </li>
          </ul>
        </div>

        <div class="border-t border-sand-100 pt-4">
          <div class="flex items-baseline justify-between text-sm">
            <span class="font-medium">نسبة التشغيل في <span class="num">{{ oh.year }}</span></span>
            <span class="num text-lg font-bold" :class="over ? 'text-rose-700' : 'text-[#064e3b]'">{{ oh.actual ?? '–' }}%</span>
          </div>
          <div class="relative h-1.5 rounded-full bg-sand-100 mt-2" aria-hidden="true">
            <span class="absolute inset-y-0 start-0 rounded-full" :class="over ? 'bg-rose-400' : 'bg-[#176b56]'" :style="{ width: pos(oh.actual ?? 0) }" />
            <span class="absolute -top-1 -bottom-1 w-0.5 bg-sand-900" :style="{ insetInlineStart: pos(oh.target) }" />
          </div>
          <p class="text-xs mt-1.5" :class="over ? 'text-rose-700' : 'text-sand-500'">الهدف <span class="num">{{ oh.target }}%</span>، {{ over ? 'والفعلي فوقه' : 'والفعلي ضمنه' }}.</p>
        </div>

        <div v-if="spending.length" class="border-t border-sand-100 pt-4">
          <p class="text-sm font-medium mb-2.5">أين يذهب المال</p>
          <ul class="space-y-2">
            <li v-for="s in spending.slice(0, 5)" :key="s.label" class="text-sm">
              <div class="flex justify-between"><span class="text-sand-600">{{ s.label }}</span><span class="num">{{ s.value }}%</span></div>
              <div class="h-1 rounded-full bg-sand-100 mt-1"><div class="h-full rounded-full" :style="{ width: `${s.value}%`, background: s.color }" /></div>
            </li>
          </ul>
        </div>
      </section>
    </div>
  </div>
</template>

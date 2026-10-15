<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { date, num, relative } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import ReportFormModal from '@/components/ReportFormModal.vue'
import ReportReviewDrawer from '@/components/ReportReviewDrawer.vue'
import NewArrivalsBar from '@/components/NewArrivalsBar.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const DEFAULTS = { q: '', status: '', supervisor_id: '', year: '', quarter: '', returned: false, queue: false }
const { items, meta, loading, filters, reset, load, error } = useResource(admin.reports.list, DEFAULTS)

/** مسار التقرير بترتيبه: يُكتب، يُرسَل، يُعتمد، يُنشر للكافل */
const STATUSES = [
  { value: 'draft', label: 'مسوّدة' },
  { value: 'submitted', label: 'بانتظار المراجعة' },
  { value: 'approved', label: 'معتمد' },
  { value: 'published', label: 'منشور للكافل' },
]

/**
 * العنوان لم يعد عموداً: كان «تقرير الربع 4 لعام 2026» في كل صف، يكرّر
 * عمود الفترة بجانبه. يُقرأ في الدرج حين يُفتح التقرير.
 */
const columns = [
  { key: 'orphan', label: 'الطفل' },
  { key: 'period_label', label: 'الفترة' },
  { key: 'supervisor', label: 'كتبه' },
  { key: 'visit_date', label: 'الزيارة' },
  { key: 'status', label: 'الحالة' },
]

const dirty = computed(() => ['q', 'status', 'supervisor_id', 'year', 'quarter', 'returned', 'queue'].some((k) => filters[k] !== DEFAULTS[k]))

/* ── الفترة: سنة وربع في اختيار واحد ─────────────────────────────────── */

const now = new Date()
const currentYear = now.getFullYear()
const currentQuarter = Math.floor(now.getMonth() / 3) + 1

/** آخر ثمانية أرباع، الأحدث أولاً */
const periods = computed(() => Array.from({ length: 8 }, (_, i) => {
  const index = currentYear * 4 + (currentQuarter - 1) - i
  const y = Math.floor(index / 4)
  const q = (index % 4) + 1
  return { value: `${y}-${q}`, label: `الربع ${q} من ${y}` }
}))

const period = computed({
  get: () => (filters.year && filters.quarter ? `${filters.year}-${filters.quarter}` : ''),
  set: (v) => {
    const [y, q] = v ? v.split('-') : ['', '']
    filters.year = y
    filters.quarter = q
  },
})

/* ── تغطية الربع ─────────────────────────────────────────────────────── */

const coverage = computed(() => meta.value.coverage ?? null)

/** الأجزاء بترتيب المسار، وكلٌّ بلونه ومعناه */
const segments = computed(() => {
  const c = coverage.value
  if (!c?.orphans) return []
  return [
    { key: 'published', label: 'منشور للكافل', n: c.published, color: 'bg-olive-600' },
    { key: 'approved', label: 'معتمد', n: c.approved, color: 'bg-clay-500' },
    { key: 'submitted', label: 'بانتظار المراجعة', n: c.submitted, color: 'bg-amber-400' },
    { key: 'draft', label: 'مسوّدة', n: c.draft, color: 'bg-sand-400' },
    { key: 'missing', label: 'لم يُكتب', n: c.missing, color: 'bg-rose-500' },
  ].filter((s) => s.n > 0)
})

/** التنقل بين الأرباع من شريط التغطية نفسه */
function shiftQuarter(step) {
  const c = coverage.value
  if (!c) return
  const index = c.year * 4 + (c.quarter - 1) + step
  filters.year = String(Math.floor(index / 4))
  filters.quarter = String((index % 4) + 1)
}
const atCurrent = computed(() => coverage.value && coverage.value.year === currentYear && coverage.value.quarter === currentQuarter)

const subtitle = computed(() => {
  if (loading.value && !meta.value.total) return 'جارٍ التحميل…'
  const parts = [`${num(meta.value.all_count ?? meta.value.total)} تقرير`]
  const waiting = meta.value.status_counts?.submitted
  if (waiting && auth.can('reports.approve')) parts.push(`${num(waiting)} بانتظار مراجعتك`)
  return parts.join(' · ')
})

/* ── المراجعة في درج ─────────────────────────────────────────────────── */

/**
 * التقرير المفتوح للمراجعة في الرابط (`?review=12`): فيُفتح من «بانتظارك»
 * أو من رابط يُرسَل لزميل، ويبقى مفتوحاً بعد تحديث الصفحة.
 */
const reviewId = computed(() => (route.query.review ? Number(route.query.review) : null))
const order = computed(() => items.value.map((r) => r.id))

function openReview(id) {
  router.replace({ query: { ...route.query, review: id } })
}

function closeReview() {
  const { review, ...rest } = route.query
  router.replace({ query: rest })
}

const formOpen = ref(false)
const editing = ref(null)

function openEdit(report) {
  editing.value = report
  formOpen.value = true
}

function onSaved() {
  // الدرج مغلق ما دامت النافذة مفتوحة، ويعود بعدها فيجلب المحتوى الجديد
  formOpen.value = false
  editing.value = null
  load()
}
</script>

<template>
  <div>
    <NewArrivalsBar @refresh="load" />
    <PageHeader title="التقارير الربعية" :subtitle="subtitle">
      <template #actions>
        <button
          v-if="auth.can('reports.create')" class="btn btn-primary" type="button"
          @click="editing = null; formOpen = true"
        >
          <BaseIcon name="plus" :size="16" />
          تقرير جديد
        </button>
      </template>
    </PageHeader>

    <!--
      الوعد على الموقع «تقرير كل ثلاثة أشهر لكل طفل مكفول». هذا الشريط يقيسه:
      كم طفلاً نشطاً وصل تقريره، وكم ما زال في الطريق، وكم لم يُكتب له شيء.
    -->
    <section v-if="coverage" class="card p-4 mb-4" aria-labelledby="coverageTitle">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-1">
          <button class="btn btn-ghost !p-1.5" type="button" aria-label="الربع السابق" @click="shiftQuarter(-1)">
            <BaseIcon name="chevron" :size="16" />
          </button>
          <h2 id="coverageTitle" class="text-sm font-semibold">
            تغطية الربع {{ coverage.quarter }} من {{ coverage.year }}
          </h2>
          <button class="btn btn-ghost !p-1.5" type="button" aria-label="الربع التالي" :disabled="atCurrent" @click="shiftQuarter(1)">
            <BaseIcon name="chevron" :size="16" style="transform: scaleX(-1)" />
          </button>
        </div>
        <p class="text-sm text-sand-600">
          <span class="num font-semibold text-sand-900">{{ num(coverage.published) }}</span>
          من <span class="num">{{ num(coverage.orphans) }}</span> طفلاً نشطاً وصل تقريرهم
        </p>
      </div>

      <div class="flex h-2.5 rounded-full overflow-hidden bg-sand-100" role="img"
           :aria-label="segments.map((s) => `${s.label} ${s.n}`).join('، ')">
        <span v-for="s in segments" :key="s.key" :class="s.color" :style="{ width: `${(s.n / coverage.orphans) * 100}%` }" />
      </div>

      <ul class="flex flex-wrap gap-x-5 gap-y-1.5 mt-3 text-xs text-sand-600">
        <li v-for="s in segments" :key="s.key" class="flex items-center gap-1.5">
          <span class="size-2 rounded-full" :class="s.color" />
          <RouterLink
            v-if="s.key === 'missing'"
            :to="{ path: '/orphans', query: { missing_report: `${coverage.year}-${coverage.quarter}` } }"
            class="text-rose-700 hover:underline font-medium"
          >{{ s.label }} <span class="num">{{ num(s.n) }}</span></RouterLink>
          <template v-else>{{ s.label }} <span class="num font-medium text-sand-800">{{ num(s.n) }}</span></template>
        </li>
      </ul>
    </section>

    <StatusTabs
      v-model="filters.status" :options="STATUSES"
      :counts="meta.status_counts ?? {}" :total="meta.all_count ?? null"
      label="حالة التقرير"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="ابحث باسم الطفل أو معرّفه…" :dirty="dirty" @reset="reset">
      <select v-model="period" class="input !w-auto text-sm" aria-label="الفترة">
        <option value="">كل الفترات</option>
        <option v-for="p in periods" :key="p.value" :value="p.value">{{ p.label }}</option>
      </select>

      <select v-if="(meta.supervisors ?? []).length > 1" v-model="filters.supervisor_id" class="input !w-auto text-sm" aria-label="كاتب التقرير">
        <option value="">كل المشرفين</option>
        <option v-for="s in meta.supervisors" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
      </select>

      <button type="button" class="chip" :aria-pressed="filters.returned" @click="filters.returned = !filters.returned">
        <BaseIcon name="undo" :size="14" /> أُرجع للتعديل
      </button>
    </FilterBar>

    <DataTable :error="error" @retry="load" :columns="columns" :rows="items" :loading="loading"
      :empty-text="dirty ? 'لا تقارير تطابق هذه الفلاتر.' : 'لا تقارير بعد.'"
      clickable @row-click="openReview($event.id)"
    >
      <template #orphan="{ row }">
        <span class="flex items-center gap-3 min-w-0">
          <img v-if="row.orphan?.photo_url" :src="row.orphan.photo_url" alt="" class="size-9 rounded-full object-cover shrink-0" width="36" height="36" loading="lazy" />
          <span v-else class="size-9 rounded-full bg-clay-50 text-clay-700 grid place-items-center text-sm font-semibold shrink-0">{{ row.orphan?.name?.charAt(0) }}</span>
          <span class="min-w-0">
            <span class="block font-medium text-sand-900">{{ row.orphan?.name }}</span>
            <span class="block text-xs text-sand-500 num">{{ row.orphan?.reference_code }}</span>
          </span>
        </span>
      </template>

      <template #supervisor="{ row }">{{ row.supervisor ?? '' }}</template>
      <template #visit_date="{ row }">{{ date(row.visit_date) }}</template>

      <!-- الحالة ومعها ما يهمّ فيها: كم ينتظر المُرسَل، وهل قرأ الكافل المنشور -->
      <template #status="{ row }">
        <span class="flex flex-wrap items-center gap-1.5">
          <StatusBadge :label="row.status_label" :value="row.status" />
          <span v-if="row.returned_at && row.status === 'draft'" class="badge bg-amber-50 text-amber-800">أُرجع للتعديل</span>
        </span>
        <span v-if="row.status === 'submitted' && row.updated_at" class="block text-xs text-sand-500 mt-0.5">
          أُرسل {{ relative(row.updated_at) }}
        </span>
        <span v-else-if="row.status === 'published'" class="block text-xs mt-0.5" :class="row.views_count ? 'text-olive-700' : 'text-sand-400'">
          {{ row.views_count ? 'قرأه الكافل' : 'لم يُقرأ بعد' }}
        </span>
      </template>

      <template #empty-action>
        <button v-if="dirty" class="btn btn-outline mt-4" type="button" @click="reset">مسح الفلاتر</button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <ReportFormModal
      :open="formOpen" :report="editing"
      @close="formOpen = false; editing = null" @saved="onSaved"
    />

    <ReportReviewDrawer
      :report-id="formOpen ? null : reviewId" :order="order"
      @close="closeReview" @open="openReview" @changed="load" @edit="openEdit"
    />
  </div>
</template>

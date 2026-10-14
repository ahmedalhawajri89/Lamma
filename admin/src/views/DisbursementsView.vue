<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, num, EMPTY } from '@/utils/format'
import { saveBlob } from '@/utils/download'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import PeriodSelect from '@/components/PeriodSelect.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import DisbursementFormModal from '@/components/DisbursementFormModal.vue'
import DisbursementDrawer from '@/components/DisbursementDrawer.vue'

const auth = useAuthStore()
const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const DEFAULTS = { q: '', status: '', category_id: '', scope: '', no_receipt: false, from: '', to: '', sort: 'spent_on', dir: 'desc' }
const { items, meta, loading, filters, reset, load, error } = useResource(admin.disbursements.list, DEFAULTS)

const STATUSES = [
  { value: 'pending', label: 'بانتظار الاعتماد' },
  { value: 'approved', label: 'معتمَد' },
]

const columns = [
  { key: 'category', label: 'البند ولمن' },
  { key: 'amount', label: 'المبلغ', sortable: true, sortKey: 'amount_base', align: 'end' },
  { key: 'spent_on', label: 'تاريخ الصرف', sortable: true },
  { key: 'recorder', label: 'سجّله' },
  { key: 'status', label: 'الحالة' },
]

function onSort(key) {
  if (filters.sort === key) filters.dir = filters.dir === 'asc' ? 'desc' : 'asc'
  else { filters.sort = key; filters.dir = 'desc' }
}

const period = ref(null)
const dirty = computed(() => ['q', 'status', 'category_id', 'scope', 'no_receipt', 'from', 'to'].some((k) => filters[k] !== DEFAULTS[k]))
function resetAll() { reset(); period.value?.reset() }

/* ── أين ذهب المال: توزيع المعتمَد على البنود، كما في صفحة الشفافية ──── */

const breakdown = computed(() => meta.value.by_category ?? [])
const approvedTotal = computed(() => meta.value.sum_approved ?? 0)
const share = (t) => (approvedTotal.value ? (t / approvedTotal.value) * 100 : 0)

/** نسبة التشغيل مقابل المستهدفة في الإعدادات */
const overheadTone = computed(() => {
  const p = meta.value.overhead_percent
  if (p === null || p === undefined) return 'text-sand-500'
  return p <= meta.value.overhead_target ? 'text-olive-700' : 'text-rose-700'
})

function toggleCategory(id) {
  filters.category_id = String(filters.category_id) === String(id) ? '' : String(id)
}

/* ── التصدير ─────────────────────────────────────────────────────────── */

const exporting = ref(false)
async function exportCsv() {
  exporting.value = true
  try {
    const params = Object.fromEntries(Object.entries(filters).filter(([k, v]) => v !== '' && v !== false && !['page', 'sort', 'dir'].includes(k)))
    saveBlob(await admin.disbursements.export(params), 'disbursements.csv')
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر التصدير.')
  } finally {
    exporting.value = false
  }
}

/* ── التسجيل والدرج ──────────────────────────────────────────────────── */

const formOpen = ref(false)
const categories = ref([])

onMounted(async () => {
  if (!auth.can('disbursements.record')) return
  try {
    const { data } = await admin.disbursements.categories()
    categories.value = data.data ?? []
  } catch {
    // النافذة وحدها تتعطّل
  }
})

function onSaved() {
  formOpen.value = false
  load()
}

const openId = computed(() => (route.query.open ? Number(route.query.open) : null))
const current = computed(() => items.value.find((d) => d.id === openId.value) ?? null)
function open(id) { router.replace({ query: { ...route.query, open: id ?? undefined } }) }
function close() { const { open: _, ...rest } = route.query; router.replace({ query: rest }) }

/** بعد الاعتماد: الصف يتغيّر مكانه، والدرج إلى التالي بانتظار الاعتماد */
function onApproved({ id, next }) {
  const row = items.value.find((d) => d.id === id)
  if (row) Object.assign(row, { is_approved: true, approved_at: new Date().toISOString(), approver: auth.user?.name })
  meta.value = { ...meta.value, pending_count: Math.max(0, (meta.value.pending_count ?? 1) - 1) }
  if (next) open(next)
  else { close(); load() }
}
</script>

<template>
  <div>
    <PageHeader title="الصرف" :subtitle="loading && !meta.total ? 'جارٍ التحميل…' : `${num(meta.all_count ?? 0)} عملية صرف بـ ${money(meta.sum_all ?? 0)}`">
      <template #actions>
        <button class="btn btn-outline" type="button" :disabled="exporting || !meta.total" @click="exportCsv">
          <BaseIcon :name="exporting ? 'loader' : 'download'" :size="16" :class="exporting ? 'animate-spin' : ''" />
          تصدير
        </button>
        <button v-if="auth.can('disbursements.record')" class="btn btn-primary" type="button" @click="formOpen = true">
          <BaseIcon name="plus" :size="16" />
          تسجيل صرف
        </button>
      </template>
    </PageHeader>

    <!--
      أين ذهب المال في الفترة: المعتمَد وحده، بالأرقام نفسها التي تنشرها صفحة
      الشفافية. كل بند فلترٌ بنقرة، ونسبة التشغيل مقابل المستهدفة.
    -->
    <section v-if="breakdown.length" class="card p-4 mb-4" aria-labelledby="whereTitle">
      <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
        <h2 id="whereTitle" class="text-sm font-semibold">
          أين ذهب المال <span class="text-sand-500 font-normal">· المعتمَد <span class="num">{{ money(approvedTotal) }}</span></span>
        </h2>
        <p class="text-sm">
          التشغيل
          <span class="num font-semibold" :class="overheadTone">{{ meta.overhead_percent ?? 0 }}%</span>
          <span class="text-sand-500"> من المستهدف <span class="num">{{ meta.overhead_target }}%</span></span>
        </p>
      </div>

      <div class="flex h-3 rounded-full overflow-hidden bg-sand-100" role="img"
           :aria-label="breakdown.map((c) => `${c.name} ${Math.round(share(c.total))}%`).join('، ')">
        <span v-for="c in breakdown" :key="c.id" :style="{ width: `${share(c.total)}%`, background: c.color }" />
      </div>

      <ul class="flex flex-wrap gap-2 mt-3">
        <li v-for="c in breakdown" :key="c.id">
          <button
            type="button" class="chip" :aria-pressed="String(filters.category_id) === String(c.id)"
            @click="toggleCategory(c.id)"
          >
            <span class="size-2.5 rounded-sm" :style="{ background: c.color }" aria-hidden="true" />
            {{ c.name }}
            <span class="chip__count">{{ Math.round(share(c.total)) }}% · {{ money(c.total) }}</span>
          </button>
        </li>
      </ul>
    </section>

    <StatusTabs
      v-model="filters.status" :options="STATUSES"
      :counts="{ pending: meta.pending_count ?? 0, approved: meta.approved_count ?? 0 }" :total="meta.all_count ?? null"
      label="حالة الاعتماد"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="اسم الطفل أو معرّفه أو البيان…" :dirty="dirty" @reset="resetAll">
      <PeriodSelect ref="period" v-model:from="filters.from" v-model:to="filters.to" />
      <button type="button" class="chip" :aria-pressed="filters.scope === 'general'" @click="filters.scope = filters.scope === 'general' ? '' : 'general'">
        مصروف عام
      </button>
      <button type="button" class="chip" :aria-pressed="filters.no_receipt" @click="filters.no_receipt = !filters.no_receipt">
        <BaseIcon name="alert" :size="14" /> بلا إيصال
        <span v-if="meta.no_receipt_count" class="chip__count">{{ num(meta.no_receipt_count) }}</span>
      </button>
    </FilterBar>

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading"
      :sort="filters.sort" :dir="filters.dir" @sort="onSort"
      :empty-text="dirty ? 'لا صرف يطابق هذه الفلاتر.' : 'لا صرف مسجّل بعد.'"
      clickable @retry="load" @row-click="(r) => open(r.id)"
    >
      <template #category="{ row }">
        <span class="flex items-center gap-3 min-w-0">
          <span class="size-2.5 rounded-sm shrink-0" :style="{ background: row.category?.color }" aria-hidden="true" />
          <span class="min-w-0">
            <span class="block font-medium text-sand-900">{{ row.category?.name }}</span>
            <span class="block text-xs text-sand-500">
              <RouterLink v-if="row.orphan" :to="`/orphans/${row.orphan.id}`" class="hover:text-clay-700" @click.stop>{{ row.orphan.name }}</RouterLink>
              <template v-else>مصروف عام</template>
            </span>
          </span>
        </span>
      </template>

      <template #amount="{ row }">
        <span class="num font-semibold">{{ money(row.amount, row.currency) }}</span>
        <span v-if="row.currency && row.currency !== 'USD'" class="block text-xs text-sand-500 num">≈ {{ money(row.amount_base) }}</span>
      </template>

      <template #spent_on="{ row }">{{ date(row.spent_on) }}</template>

      <template #recorder="{ row }">
        {{ row.recorder ?? EMPTY }}
        <!-- غياب الإيصال يهمّ قبل الاعتماد؛ بعده يكفيه فلتر «بلا إيصال» -->
        <span v-if="!row.has_receipt && !row.is_approved" class="block text-xs text-amber-700">بلا إيصال</span>
      </template>

      <template #status="{ row }">
        <span v-if="row.is_approved" class="badge bg-olive-100 text-olive-700">معتمَد</span>
        <span v-else class="badge bg-amber-100 text-amber-800">بانتظار الاعتماد</span>
      </template>

      <template #empty-action>
        <button v-if="dirty" class="btn btn-outline mt-4" type="button" @click="resetAll">مسح الفلاتر</button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <DisbursementDrawer :item="current" :order="items" @close="close" @open="open" @approved="onApproved" />

    <DisbursementFormModal :open="formOpen" :categories="categories" @close="formOpen = false" @saved="onSaved" />
  </div>
</template>

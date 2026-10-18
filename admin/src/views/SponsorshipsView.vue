<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { money, date, num, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import SponsorshipFormModal from '@/components/SponsorshipFormModal.vue'
import SponsorshipDrawer from '@/components/SponsorshipDrawer.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const DEFAULTS = { q: '', status: '', zakat_only: false, due_soon: false, overdue: false, sort: 'id', dir: 'desc' }
const { items, meta, loading, filters, reset, load, error } = useResource(admin.sponsorships.list, DEFAULTS)

const STATUSES = [
  { value: 'active', label: 'نشطة' },
  { value: 'paused', label: 'موقوفة مؤقتاً' },
  { value: 'pending', label: 'بانتظار أول دفعة' },
  { value: 'ended', label: 'منتهية' },
]

const canMoney = computed(() => auth.can('payments.view'))

/**
 * لا عمود إجراءات: الإجراءات في الدرج بعد رؤية الكفالة كاملة. والمال
 * والكافل لمن يملك رؤيتهما (والخادم لا يرسلهما لغيره أصلاً).
 */
const columns = computed(() => [
  { key: 'orphan', label: 'الطفل' },
  auth.can('sponsors.view') && { key: 'sponsor', label: 'الكافل' },
  canMoney.value && { key: 'monthly_amount', label: 'المبلغ', sortable: true, align: 'end' },
  canMoney.value && { key: 'next_due_at', label: 'القسط القادم', sortable: true },
  { key: 'status', label: 'الحالة' },
].filter(Boolean))

function onSort(key) {
  if (filters.sort === key) filters.dir = filters.dir === 'asc' ? 'desc' : 'asc'
  else { filters.sort = key; filters.dir = key === 'next_due_at' ? 'asc' : 'desc' }
}

const dirty = computed(() => ['q', 'status', 'zakat_only', 'due_soon', 'overdue'].some((k) => filters[k] !== DEFAULTS[k]))

const subtitle = computed(() => {
  if (loading.value && !meta.value.total) return 'جارٍ التحميل…'
  const m = meta.value
  const parts = [`${num(m.all_count ?? m.total)} كفالة`]
  if (m.monthly_commitment) parts.push(`التزام شهري ${money(m.monthly_commitment)}`)
  if (m.due_week_count) parts.push(`${num(m.due_week_count)} قسطاً هذا الأسبوع بـ ${money(m.due_week_amount)}`)
  return parts.join(' · ')
})

/* ── القسط القادم بلغة من يتابع ──────────────────────────────────────── */

const today = () => { const d = new Date(); d.setHours(0, 0, 0, 0); return d }
const daysTo = (v) => Math.round((new Date(`${v}T00:00:00`) - today()) / 86400000)
const daysLabel = (n) => (n === 1 ? 'يوم واحد' : n === 2 ? 'يومين' : n <= 10 ? `${num(n)} أيام` : `${num(n)} يوماً`)

function due(row) {
  if (row.status === 'paused') return { text: row.paused_at ? `موقوفة منذ ${date(row.paused_at)}` : 'لا خصم أثناء الإيقاف', tone: 'text-sand-500' }
  if (row.status === 'pending') return { text: row.hold_expires_at ? `حجز حتى ${date(row.hold_expires_at)}` : 'عند أول دفعة', tone: 'text-amber-700' }
  if (row.status !== 'active' || !row.next_due_at) return { text: row.ended_at ? `انتهت ${date(row.ended_at)}` : EMPTY, tone: 'text-sand-400' }
  const d = daysTo(row.next_due_at)
  if (d < 0) return { text: `متأخر ${daysLabel(-d)}`, tone: 'text-rose-700 font-medium', badge: true }
  if (d === 0) return { text: 'اليوم', tone: 'text-amber-700 font-medium' }
  if (d <= 7) return { text: `بعد ${daysLabel(d)}`, tone: 'text-amber-700' }
  return { text: date(row.next_due_at), tone: 'text-sand-700' }
}

const CYCLE = { 3: 'كل 3 أشهر', 12: 'سنوياً' }

/* ── الدرج والتعديل ──────────────────────────────────────────────────── */

const openId = computed(() => (route.query.open ? Number(route.query.open) : null))
function open(id) { router.replace({ query: { ...route.query, open: id } }) }
function close() { const { open: _, ...rest } = route.query; router.replace({ query: rest }) }

const drawer = ref(null)
const formOpen = ref(false)
const editing = ref(null)

function edit(s) {
  editing.value = s
  formOpen.value = true
}

function onSaved() {
  formOpen.value = false
  load()
  drawer.value?.reload()
}
</script>

<template>
  <div>
    <PageHeader title="الكفالات" :subtitle="subtitle" />

    <StatusTabs
      v-model="filters.status" :options="STATUSES"
      :counts="meta.status_counts ?? {}" :total="meta.all_count ?? null" label="حالة الكفالة"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="مرجع، طفل، كافل، أو رمزه…" :dirty="dirty" @reset="reset">
      <button v-if="canMoney" type="button" class="chip" :aria-pressed="filters.overdue" @click="filters.overdue = !filters.overdue">
        <BaseIcon name="clock" :size="14" /> متأخرة عن موعدها
        <span v-if="meta.overdue_count" class="chip__count">{{ num(meta.overdue_count) }}</span>
      </button>
      <button v-if="canMoney" type="button" class="chip" :aria-pressed="filters.due_soon" @click="filters.due_soon = !filters.due_soon">
        تستحق خلال أسبوع
        <span v-if="meta.due_week_count" class="chip__count">{{ num(meta.due_week_count) }}</span>
      </button>
      <button type="button" class="chip" :aria-pressed="filters.zakat_only" @click="filters.zakat_only = !filters.zakat_only">
        من الزكاة
      </button>
    </FilterBar>

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading"
      :sort="filters.sort" :dir="filters.dir" @sort="onSort"
      :empty-text="dirty ? 'لا كفالات تطابق هذه الفلاتر.' : 'لا كفالات بعد.'"
      clickable @retry="load" @row-click="(r) => open(r.id)"
    >
      <template #orphan="{ row }">
        <span class="flex items-center gap-3 min-w-0">
          <img v-if="row.orphan?.photo_url" :src="row.orphan.photo_url" alt="" class="size-9 rounded-full object-cover shrink-0" width="36" height="36" loading="lazy" />
          <span v-else class="size-9 rounded-full bg-clay-50 text-clay-700 grid place-items-center text-sm font-semibold shrink-0">{{ row.orphan?.name?.charAt(0) }}</span>
          <span class="min-w-0">
            <RouterLink :to="`/orphans/${row.orphan?.id}`" class="block font-medium text-sand-900 hover:text-clay-700 truncate" @click.stop>
              {{ row.orphan?.name }}
            </RouterLink>
            <span class="block text-xs text-sand-500 num">{{ row.reference_code }}</span>
          </span>
        </span>
      </template>

      <template #sponsor="{ row }">
        <RouterLink v-if="row.sponsor" :to="`/sponsors/${row.sponsor.id}`" class="text-sand-800 hover:text-clay-700" @click.stop>
          {{ row.sponsor.name ?? row.sponsor.sponsor_code }}
        </RouterLink>
        <span v-else>{{ EMPTY }}</span>
      </template>

      <template #monthly_amount="{ row }">
        <span class="num font-medium">{{ money(row.monthly_amount, row.currency) }}</span>
        <span class="block text-xs text-sand-500">
          <template v-if="row.interval_months > 1">{{ CYCLE[row.interval_months] }} </template>
          <span v-if="row.is_zakat" class="text-teal-700">· زكاة</span>
        </span>
      </template>

      <template #next_due_at="{ row }">
        <span class="text-sm" :class="due(row).tone">{{ due(row).text }}</span>
      </template>

      <template #status="{ row }"><StatusBadge :label="row.status_label" :value="row.status" /></template>

      <template #empty-action>
        <button v-if="dirty" class="btn btn-outline mt-4" type="button" @click="reset">مسح الفلاتر</button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <SponsorshipDrawer ref="drawer" :id="formOpen ? null : openId" @close="close" @changed="load" @edit="edit" />

    <SponsorshipFormModal
      :open="formOpen" :sponsorship="editing"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

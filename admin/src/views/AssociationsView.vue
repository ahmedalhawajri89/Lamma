<script setup>
/**
 * الجمعيات الشريكة: من تعرف أسر الأطفال وتزورها، وإليها تُحوَّل الكفالات.
 *
 * التوثيق هو ما يفتح نشر أطفال الجمعية للكفالة، فلا يُوثَّق إلا بتأكيد أنها
 * وقّعت سياسة حماية الطفل وأقرّت بأن أطفالها في أسرهم. والإيقاف يُخفي
 * أطفالها من الموقع بسببٍ مكتوب، وتبقى كفالاتهم القائمة.
 */
import { computed, onMounted, ref } from 'vue'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { confirmDialog } from '@/composables/useConfirm'
import { useUiStore } from '@/stores/ui'
import { num, date } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import AssociationFormModal from '@/components/AssociationFormModal.vue'

const ui = useUiStore()

const DEFAULTS = { q: '', status: '', country_id: '' }
const { items, meta, loading, filters, reset, load, error } = useResource(admin.associations.list, DEFAULTS)
const dirty = computed(() => Boolean(filters.q || filters.country_id))

const STATUSES = [
  { value: 'under_review', label: 'قيد المراجعة' },
  { value: 'verified', label: 'موثّقة' },
  { value: 'suspended', label: 'موقوفة' },
]

const columns = [
  { key: 'name', label: 'الجمعية' },
  { key: 'reach', label: 'ما تتابعه' },
  { key: 'status', label: 'الحالة' },
  { key: 'actions', label: '' },
]

const countries = ref([])
onMounted(async () => {
  try {
    const { data } = await admin.references()
    countries.value = data.data?.countries ?? []
  } catch {
    // النموذج وحده يتأثر: حقل البلد فارغ
  }
})

/* ── النموذج ─────────────────────────────────────────────────────────── */
const formOpen = ref(false)
const editing = ref(null)
function create() { editing.value = null; formOpen.value = true }
function edit(row) { editing.value = row; formOpen.value = true }
function onSaved() { formOpen.value = false; load() }

/* ── التوثيق والإيقاف ────────────────────────────────────────────────── */
async function verify(row) {
  const ok = await confirmDialog({
    title: `توثيق «${row.name_ar}»`,
    message: 'بالتوثيق تؤكّد أنك راجعت تسجيلها، وأنها وقّعت سياسة حماية الطفل، وأقرّت بأن أطفالها يعيشون في أسرهم.\nبعده يصير نشر أطفالها للكفالة ممكناً، وتظهر لها صفحة على الموقع.',
    confirmLabel: 'وثّق الجمعية',
  })
  if (!ok) return
  try {
    const { data } = await admin.associations.verify(row.id, { safeguarding_signed: true, family_care_attested: true })
    ui.toast(data.message)
    load()
  } catch (e) {
    ui.toast(e.response?.data?.errors?.status?.[0] ?? e.response?.data?.message ?? 'تعذّر التوثيق.', 'error')
  }
}

async function suspend(row) {
  const answer = await confirmDialog({
    title: `إيقاف «${row.name_ar}»`,
    message: 'يُخفى أطفالها من الموقع فوراً، ولا تُسند إليها أسرٌ جديدة. كفالاتهم القائمة مستمرة.',
    reason: 'required', reasonLabel: 'سبب الإيقاف', maxLength: 300,
    confirmLabel: 'أوقف الجمعية', danger: true,
  })
  if (!answer) return
  try {
    const { data } = await admin.associations.suspend(row.id, answer.reason)
    ui.toast(data.message)
    load()
  } catch (e) {
    ui.toast(e.response?.data?.message ?? 'تعذّر الإيقاف.', 'error')
  }
}
</script>

<template>
  <div>
    <PageHeader title="الجمعيات الشريكة" subtitle="من تعرف أسر الأطفال وتزورها، ويُنشر أطفالها عبرها">
      <template #actions>
        <button class="btn btn-primary" type="button" @click="create">
          <BaseIcon name="plus" :size="16" /> جمعية جديدة
        </button>
      </template>
    </PageHeader>

    <StatusTabs
      v-model="filters.status" :options="STATUSES" :counts="meta.status_counts ?? {}" :total="meta.all_count ?? null"
      all-label="الكل" label="الحالة"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="اسم الجمعية أو رقم تسجيلها…" :dirty="dirty" @reset="reset">
      <select v-model="filters.country_id" class="input !w-auto" aria-label="البلد">
        <option value="">كل البلدان</option>
        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </FilterBar>

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading"
      clickable empty-text="لا جمعيات بهذه الفلاتر."
      @retry="load" @row-click="edit"
    >
      <template #name="{ row }">
        <span class="flex items-center gap-3">
          <span class="size-9 shrink-0 rounded-xl grid place-items-center bg-teal-600/10 text-teal-700" aria-hidden="true">
            <BaseIcon name="handshake" :size="18" />
          </span>
          <span class="min-w-0">
            <span class="font-medium">{{ row.name_ar }}</span>
            <span class="block text-xs text-sand-500">
              {{ row.country }}<template v-if="row.registration_number"> · <span class="num" dir="ltr">{{ row.registration_number }}</span></template>
            </span>
          </span>
        </span>
      </template>

      <template #reach="{ row }">
        <span class="text-sm"><span class="num">{{ num(row.families_count ?? 0) }}</span> أسرة · <span class="num">{{ num(row.children_count ?? 0) }}</span> طفلاً</span>
        <span class="block text-xs text-sand-500"><span class="num">{{ num(row.staff_count ?? 0) }}</span> من فريقها في اللوحة</span>
      </template>

      <template #status="{ row }">
        <StatusBadge :label="row.status_label" :value="row.status" />
        <span v-if="row.status === 'verified' && row.verified_at" class="block text-xs text-sand-500 mt-1">منذ {{ date(row.verified_at) }}</span>
        <span v-else-if="row.status === 'suspended' && row.suspension_reason" class="block text-xs text-rose-700 mt-1 max-w-56 truncate" :title="row.suspension_reason">{{ row.suspension_reason }}</span>
      </template>

      <template #actions="{ row }">
        <span class="flex items-center justify-end gap-2" @click.stop>
          <a v-if="row.public_url" :href="row.public_url" target="_blank" rel="noopener" class="btn btn-ghost !px-2.5" title="صفحتها على الموقع" aria-label="صفحتها على الموقع">
            <BaseIcon name="external" :size="16" />
          </a>
          <button v-if="row.status !== 'verified'" type="button" class="btn btn-outline !py-1.5" @click="verify(row)">
            <BaseIcon name="check" :size="15" /> وثّق
          </button>
          <button v-if="row.status !== 'suspended'" type="button" class="btn btn-ghost !py-1.5 text-rose-700" @click="suspend(row)">أوقف</button>
        </span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <AssociationFormModal
      :open="formOpen" :association="editing" :countries="countries"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

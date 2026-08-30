<script setup>
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'

const auth = useAuthStore()
const ui = useUiStore()

const { items, meta, loading, filters, reset, load } = useResource(admin.disbursements.list, {
  pending_only: false, from: '', to: '',
})

const columns = [
  { key: 'spent_on', label: 'تاريخ الصرف' },
  { key: 'category', label: 'البند' },
  { key: 'orphan', label: 'الطفل' },
  { key: 'amount', label: 'المبلغ', align: 'end' },
  { key: 'recorder', label: 'سجّله' },
  { key: 'status', label: 'الاعتماد' },
]

async function approve(row) {
  try {
    await admin.disbursements.approve(row.id)
    ui.success('اعتُمد الصرف. صار جزءاً من أرقام الشفافية المنشورة.')
    load()
  } catch (e) {
    ui.error(e.userMessage)
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="الصرف"
      :subtitle="`${money(meta.sum_all)} إجمالاً · ${money(meta.sum_pending)} بانتظار الاعتماد`"
    />

    <div class="card p-3 mb-4 bg-clay-50 border-clay-200 text-sm text-clay-800">
      الصرف المعتمَد وحده هو ما تُحسب منه نسب الشفافية المنشورة على الموقع.
    </div>

    <FilterBar :has-search="false" @reset="reset">
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.pending_only" type="checkbox" class="accent-clay-600" /> بانتظار الاعتماد فقط
      </label>
      <input v-model="filters.from" type="date" class="input !w-auto text-sm" aria-label="من تاريخ" />
      <input v-model="filters.to" type="date" class="input !w-auto text-sm" aria-label="إلى تاريخ" />
    </FilterBar>

    <DataTable :columns="columns" :rows="items" :loading="loading">
      <template #spent_on="{ row }">{{ date(row.spent_on) }}</template>
      <template #category="{ row }">
        <span class="inline-flex items-center gap-1.5">
          <span class="size-2.5 rounded-sm" :style="{ background: row.category?.color }" aria-hidden="true" />
          {{ row.category?.name }}
        </span>
      </template>
      <template #orphan="{ row }">
        <span v-if="row.orphan">{{ row.orphan.name }}</span>
        <span v-else class="text-sand-500 text-xs">مصروف عام</span>
      </template>
      <template #amount="{ row }"><span class="num font-medium">{{ money(row.amount) }}</span></template>
      <template #recorder="{ row }">{{ row.recorder ?? '—' }}</template>
      <template #status="{ row }">
        <span v-if="row.is_approved" class="badge bg-olive-100 text-olive-700">معتمَد</span>
        <button
          v-else-if="auth.can('disbursements.approve')"
          class="btn btn-outline !py-1 !px-2 text-xs" type="button" @click="approve(row)"
        >
          اعتماد
        </button>
        <span v-else class="badge bg-amber-100 text-amber-700">بانتظار الاعتماد</span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

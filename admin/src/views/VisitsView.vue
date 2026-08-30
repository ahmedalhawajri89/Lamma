<script setup>
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { date, dateTime } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'

const { items, meta, loading, filters, reset } = useResource(admin.visits.list, { overdue: false })

const columns = [
  { key: 'visited_at', label: 'تاريخ الزيارة' },
  { key: 'orphan', label: 'الطفل' },
  { key: 'supervisor', label: 'المشرف' },
  { key: 'findings', label: 'الملاحظات' },
  { key: 'next_visit_due', label: 'الزيارة القادمة' },
]
</script>

<template>
  <div>
    <PageHeader title="الزيارات الميدانية" :subtitle="`${meta.total} زيارة موثّقة · ${meta.overdue ?? 0} متأخرة`" />

    <FilterBar :has-search="false" @reset="reset">
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.overdue" type="checkbox" class="accent-clay-600" /> المتأخرة فقط
      </label>
    </FilterBar>

    <DataTable :columns="columns" :rows="items" :loading="loading">
      <template #visited_at="{ row }">{{ dateTime(row.visited_at) }}</template>
      <template #orphan="{ row }">
        <RouterLink :to="`/orphans/${row.orphan?.id}`" class="text-clay-700 hover:underline">
          {{ row.orphan?.name }}
        </RouterLink>
      </template>
      <template #supervisor="{ row }">{{ row.supervisor ?? '—' }}</template>
      <template #findings="{ row }">
        <span class="line-clamp-1 max-w-md text-sand-700">{{ row.findings }}</span>
      </template>
      <template #next_visit_due="{ row }">
        <span :class="row.is_overdue ? 'text-rose-700 font-medium' : ''">{{ date(row.next_visit_due) }}</span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

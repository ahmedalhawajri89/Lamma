<script setup>
import { ref } from 'vue'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { date, dateTime, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import VisitFormModal from '@/components/VisitFormModal.vue'

const auth = useAuthStore()

const { items, meta, loading, filters, reset, load, error } = useResource(admin.visits.list, { overdue: false })

const columns = [
  { key: 'visited_at', label: 'تاريخ الزيارة' },
  { key: 'orphan', label: 'الطفل' },
  { key: 'supervisor', label: 'المشرف' },
  { key: 'findings', label: 'الملاحظات' },
  { key: 'next_visit_due', label: 'الزيارة القادمة' },
]

const formOpen = ref(false)

function onSaved() {
  formOpen.value = false
  load()
}

</script>

<template>
  <div>
    <PageHeader title="الزيارات الميدانية" :subtitle="`${meta.total} زيارة موثّقة · ${meta.overdue ?? 0} متأخرة`">
      <template #actions>
        <button
          v-if="auth.can('visits.record')" class="btn btn-primary" type="button"
          @click="formOpen = true"
        >
          <BaseIcon name="plus" :size="16" />
          تسجيل زيارة
        </button>
      </template>
    </PageHeader>

    <FilterBar :has-search="false" @reset="reset">
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.overdue" type="checkbox" class="accent-clay-600" /> المتأخرة فقط
      </label>
    </FilterBar>

    <DataTable :error="error" @retry="load" :columns="columns" :rows="items" :loading="loading">
      <template #visited_at="{ row }">{{ dateTime(row.visited_at) }}</template>
      <template #orphan="{ row }">
        <RouterLink :to="`/orphans/${row.orphan?.id}`" class="text-clay-700 hover:underline">
          {{ row.orphan?.name }}
        </RouterLink>
      </template>
      <template #supervisor="{ row }">{{ row.supervisor ?? EMPTY }}</template>
      <template #findings="{ row }">
        <span class="line-clamp-1 max-w-md text-sand-700">{{ row.findings }}</span>
      </template>
      <template #next_visit_due="{ row }">
        <span :class="row.is_overdue ? 'text-rose-700 font-medium' : ''">{{ date(row.next_visit_due) }}</span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <VisitFormModal
      :open="formOpen"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

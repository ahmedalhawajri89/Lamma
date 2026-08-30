<script setup>
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { money } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'

const { items, meta, loading, filters, reset } = useResource(admin.families.list, { q: '' })

const columns = [
  { key: 'family_code', label: 'الرمز' },
  { key: 'guardian', label: 'الوصي' },
  { key: 'country', label: 'الموقع' },
  { key: 'orphans_count', label: 'أيتام', align: 'end' },
  { key: 'members_count', label: 'الأفراد', align: 'end' },
  { key: 'monthly_income', label: 'الدخل الشهري', align: 'end' },
  { key: 'housing', label: 'السكن' },
]
</script>

<template>
  <div>
    <PageHeader title="الأسر" :subtitle="`${meta.total} أسرة — منها تُقرأ الدولة والمدينة والوصي لكل يتيم`" />

    <FilterBar v-model:search="filters.q" search-placeholder="رمز الأسرة أو اسم الوصي…" @reset="reset" />

    <DataTable :columns="columns" :rows="items" :loading="loading">
      <template #family_code="{ row }"><span class="num text-xs text-sand-600">{{ row.family_code }}</span></template>
      <template #guardian="{ row }">
        {{ row.guardian?.full_name }}
        <span class="text-xs text-sand-500">({{ row.guardian?.relation_label }})</span>
      </template>
      <template #country="{ row }">
        {{ row.country?.name }}
        <span class="text-xs text-sand-500">{{ row.city?.name }}</span>
      </template>
      <template #orphans_count="{ row }"><span class="num">{{ row.orphans_count }}</span></template>
      <template #members_count="{ row }"><span class="num">{{ row.members_count }}</span></template>
      <template #monthly_income="{ row }"><span class="num">{{ money(row.monthly_income) }}</span></template>
      <template #housing="{ row }">
        <span class="badge" :class="row.housing_status === 'displaced' ? 'bg-rose-600/10 text-rose-700' : 'bg-sand-100 text-sand-700'">
          {{ row.housing_status_label }}
        </span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

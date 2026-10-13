<script setup>
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { money, date, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'

const { items, meta, loading, filters, reset, error, load } = useResource(admin.sponsors.list, {
  q: '', active_only: false, zakat_only: false,
})

const columns = [
  { key: 'sponsor_code', label: 'الرمز' },
  { key: 'name', label: 'الاسم' },
  { key: 'country', label: 'الدولة' },
  { key: 'active_sponsorships_count', label: 'كفالات نشطة', align: 'end' },
  { key: 'total_given', label: 'إجمالي ما قدّمه', align: 'end' },
  { key: 'sponsoring_since', label: 'كافل منذ' },
]
</script>

<template>
  <div>
    <PageHeader title="الكفلاء" :subtitle="`${meta.total} كافل`" />

    <FilterBar v-model:search="filters.q" search-placeholder="اسم أو بريد أو رمز…" @reset="reset">
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.active_only" type="checkbox" class="accent-clay-600" /> نشط فقط
      </label>
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.zakat_only" type="checkbox" class="accent-clay-600" /> يفضّل الزكاة
      </label>
    </FilterBar>

    <DataTable :error="error" @retry="load" :columns="columns" :rows="items" :loading="loading">
      <template #sponsor_code="{ row }"><span class="num text-xs text-sand-600">{{ row.sponsor_code }}</span></template>
      <template #name="{ row }">
        <span class="font-medium">{{ row.is_anonymous ? 'كافل يفضّل عدم ذكر اسمه' : row.name }}</span>
        <span v-if="!row.is_anonymous" class="block text-xs text-sand-500 num" dir="ltr">{{ row.email }}</span>
      </template>
      <template #country="{ row }">{{ row.country?.name ?? EMPTY }}</template>
      <template #active_sponsorships_count="{ row }">
        <span class="num">{{ row.active_sponsorships_count ?? 0 }}</span>
      </template>
      <template #total_given="{ row }"><span class="num font-medium">{{ money(row.total_given) }}</span></template>
      <template #sponsoring_since="{ row }">{{ date(row.sponsoring_since) }}</template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

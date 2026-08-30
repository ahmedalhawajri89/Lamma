<script setup>
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { money, dateTime } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const { items, meta, loading, filters, reset } = useResource(admin.payments.list, {
  q: '', status: '', method: '', from: '', to: '',
})

const columns = [
  { key: 'reference_code', label: 'المرجع' },
  { key: 'sponsor', label: 'الكافل' },
  { key: 'orphan', label: 'الطفل' },
  { key: 'amount', label: 'المبلغ', align: 'end' },
  { key: 'method', label: 'الوسيلة' },
  { key: 'paid_at', label: 'تاريخ الدفع' },
  { key: 'status', label: 'الحالة' },
]
</script>

<template>
  <div>
    <PageHeader title="المدفوعات" :subtitle="`${meta.settled_count ?? 0} دفعة ناجحة · ${money(meta.settled_amount)}`" />

    <FilterBar v-model:search="filters.q" search-placeholder="مرجع أو رقم إيصال…" @reset="reset">
      <select v-model="filters.status" class="input !w-auto text-sm" aria-label="الحالة">
        <option value="">كل الحالات</option>
        <option value="succeeded">مكتملة</option>
        <option value="failed">فاشلة</option>
        <option value="refunded">مُستردّة</option>
      </select>
      <select v-model="filters.method" class="input !w-auto text-sm" aria-label="الوسيلة">
        <option value="">كل الوسائل</option>
        <option value="card">بطاقة</option>
        <option value="apple_pay">Apple Pay</option>
        <option value="bank_transfer">تحويل بنكي</option>
        <option value="cash">نقداً</option>
      </select>
      <input v-model="filters.from" type="date" class="input !w-auto text-sm" aria-label="من تاريخ" />
      <input v-model="filters.to" type="date" class="input !w-auto text-sm" aria-label="إلى تاريخ" />
    </FilterBar>

    <DataTable :columns="columns" :rows="items" :loading="loading">
      <template #reference_code="{ row }">
        <span class="num text-xs text-sand-600">{{ row.reference_code }}</span>
      </template>
      <template #sponsor="{ row }">{{ row.sponsor?.name ?? '—' }}</template>
      <template #orphan="{ row }">{{ row.sponsorship?.orphan?.name ?? '—' }}</template>
      <template #amount="{ row }"><span class="num font-medium">{{ money(row.amount) }}</span></template>
      <template #method="{ row }">
        {{ row.method_label }}
        <span v-if="row.card" class="text-xs text-sand-500 num">{{ row.card }}</span>
      </template>
      <template #paid_at="{ row }">{{ dateTime(row.paid_at) }}</template>
      <template #status="{ row }">
        <StatusBadge :label="row.status_label" :value="row.status" />
        <p v-if="row.failure_reason" class="text-xs text-sand-500 mt-0.5">{{ row.failure_reason }}</p>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

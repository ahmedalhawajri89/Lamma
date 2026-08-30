<script setup>
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { relative } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const { items, meta, loading, filters, reset } = useResource(admin.users.list, {
  q: '', role: '', staff_only: true,
})

const columns = [
  { key: 'name', label: 'الاسم' },
  { key: 'roles', label: 'الدور' },
  { key: 'country', label: 'البلد المُسنَد' },
  { key: 'last_login_at', label: 'آخر دخول' },
  { key: 'status', label: 'الحالة' },
]

const roleLabels = {
  admin: 'مدير النظام',
  accountant: 'محاسب',
  field_supervisor: 'مشرف ميداني',
  sponsor: 'كافل',
}
</script>

<template>
  <div>
    <PageHeader title="المستخدمون" :subtitle="`${meta.total} حساب`" />

    <div class="card p-3 mb-4 bg-sand-100 text-sm text-sand-700">
      المشرف الميداني يرى أيتام بلده المُسنَد وحده — يُطبَّق ذلك على مستوى الاستعلام لا بإخفاء الأزرار.
    </div>

    <FilterBar v-model:search="filters.q" search-placeholder="اسم أو بريد…" @reset="reset">
      <select v-model="filters.role" class="input !w-auto text-sm" aria-label="الدور">
        <option value="">كل الأدوار</option>
        <option value="admin">مدير النظام</option>
        <option value="accountant">محاسب</option>
        <option value="field_supervisor">مشرف ميداني</option>
        <option value="sponsor">كافل</option>
      </select>
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.staff_only" type="checkbox" class="accent-clay-600" /> الموظفون فقط
      </label>
    </FilterBar>

    <DataTable :columns="columns" :rows="items" :loading="loading">
      <template #name="{ row }">
        <span class="font-medium">{{ row.name }}</span>
        <span class="block text-xs text-sand-500 num" dir="ltr">{{ row.email }}</span>
      </template>
      <template #roles="{ row }">
        <span v-for="r in row.roles" :key="r" class="badge bg-clay-50 text-clay-700 me-1">
          {{ roleLabels[r] ?? r }}
        </span>
      </template>
      <template #country="{ row }">{{ row.staff_profile?.assigned_country_id ? '—' : 'كل الدول' }}</template>
      <template #last_login_at="{ row }">{{ relative(row.last_login_at) }}</template>
      <template #status="{ row }"><StatusBadge :label="row.status_label" :value="row.status" /></template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

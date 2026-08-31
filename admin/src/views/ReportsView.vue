<script setup>
import { onMounted, ref } from 'vue'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { date } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import ReportFormModal from '@/components/ReportFormModal.vue'

const auth = useAuthStore()
const ui = useUiStore()

const { items, meta, loading, filters, reset, load } = useResource(admin.reports.list, {
  status: '', queue: false,
})

const columns = [
  { key: 'orphan', label: 'الطفل' },
  { key: 'period_label', label: 'الفترة' },
  { key: 'title', label: 'العنوان' },
  { key: 'supervisor', label: 'المشرف' },
  { key: 'visit_date', label: 'تاريخ الزيارة' },
  { key: 'status', label: 'الحالة' },
  { key: 'actions', label: '', align: 'end' },
]

const formOpen = ref(false)
const orphans = ref([])

async function loadOrphans() {
  if (!auth.can('reports.create')) return

  try {
    const { data } = await admin.orphans.list({ per_page: 200, sort: 'first_name', dir: 'asc' })
    orphans.value = data.data ?? []
  } catch {
    // فشل القائمة لا يمنع تصفّح الجدول — النافذة وحدها تتعطّل
  }
}

function onSaved() {
  formOpen.value = false
  load()
}

onMounted(loadOrphans)

async function act(fn, row, message) {
  try {
    await fn(row.id)
    ui.success(message)
    load()
  } catch (e) {
    ui.error(e.fieldErrors?.status?.[0] ?? e.userMessage)
  }
}
</script>

<template>
  <div>
    <PageHeader title="التقارير الربعية" :subtitle="`${meta.total} تقرير`">
      <template #actions>
        <button
          v-if="auth.can('reports.create')" class="btn btn-primary" type="button"
          @click="formOpen = true"
        >
          <BaseIcon name="plus" :size="16" />
          تقرير جديد
        </button>
      </template>
    </PageHeader>

    <div class="card p-3 mb-4 bg-sand-100 text-sm text-sand-700">
      المسار: يكتبه المشرف مسوّدةً، ثم يرسله للمراجعة، ثم يعتمده غيره، ثم يُنشر للكافل.
      ولا يعتمد المشرف تقريره بنفسه.
    </div>

    <FilterBar :has-search="false" @reset="reset">
      <select v-model="filters.status" class="input !w-auto text-sm" aria-label="الحالة">
        <option value="">كل الحالات</option>
        <option value="draft">مسوّدة</option>
        <option value="submitted">مُرسل للمراجعة</option>
        <option value="approved">معتمَد</option>
        <option value="published">منشور</option>
      </select>
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.queue" type="checkbox" class="accent-clay-600" /> طابور المراجعة فقط
      </label>
    </FilterBar>

    <DataTable :columns="columns" :rows="items" :loading="loading">
      <template #orphan="{ row }">
        <RouterLink :to="`/orphans/${row.orphan?.id}`" class="text-clay-700 hover:underline">
          {{ row.orphan?.name }}
        </RouterLink>
      </template>
      <template #visit_date="{ row }">{{ date(row.visit_date) }}</template>
      <template #status="{ row }"><StatusBadge :label="row.status_label" :value="row.status" /></template>
      <template #actions="{ row }">
        <button
          v-if="row.status === 'draft'" class="btn btn-ghost !py-1 !px-2 text-xs" type="button"
          @click="act(admin.reports.submit, row, 'أُرسل التقرير للمراجعة.')"
        >
          إرسال
        </button>
        <button
          v-else-if="row.status === 'submitted' && auth.can('reports.approve')"
          class="btn btn-outline !py-1 !px-2 text-xs" type="button"
          @click="act(admin.reports.approve, row, 'اعتُمد التقرير وأُخذت لقطة صرف الربع.')"
        >
          اعتماد
        </button>
        <button
          v-else-if="row.status === 'approved' && auth.can('reports.publish')"
          class="btn btn-primary !py-1 !px-2 text-xs" type="button"
          @click="act(admin.reports.publish, row, 'نُشر التقرير للكفلاء.')"
        >
          نشر
        </button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <ReportFormModal
      :open="formOpen" :orphans="orphans"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

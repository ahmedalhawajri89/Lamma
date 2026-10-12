<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { date } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import InlineNote from '@/components/InlineNote.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import ReportFormModal from '@/components/ReportFormModal.vue'
import ReportReviewDrawer from '@/components/ReportReviewDrawer.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const { items, meta, loading, filters, reset, load } = useResource(admin.reports.list, {
  status: '', queue: false, returned: false,
})

const columns = [
  { key: 'orphan', label: 'الطفل' },
  { key: 'period_label', label: 'الفترة' },
  { key: 'title', label: 'العنوان' },
  { key: 'supervisor', label: 'المشرف' },
  { key: 'visit_date', label: 'تاريخ الزيارة' },
  { key: 'status', label: 'الحالة' },
]

/**
 * التقرير المفتوح للمراجعة في الرابط (`?review=12`): فيُفتح من «بانتظارك»
 * أو من رابط يُرسَل لزميل، ويبقى مفتوحاً بعد تحديث الصفحة.
 */
const reviewId = computed(() => (route.query.review ? Number(route.query.review) : null))
const order = computed(() => items.value.map((r) => r.id))

function openReview(id) {
  router.replace({ query: { ...route.query, review: id } })
}

function closeReview() {
  const { review, ...rest } = route.query
  router.replace({ query: rest })
}

const formOpen = ref(false)
const editing = ref(null)
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

function openEdit(report) {
  editing.value = report
  formOpen.value = true
}

function onSaved() {
  // الدرج مغلق ما دامت النافذة مفتوحة، ويعود بعدها فيجلب المحتوى الجديد
  formOpen.value = false
  editing.value = null
  load()
}

onMounted(loadOrphans)

</script>

<template>
  <div>
    <PageHeader title="التقارير الربعية" :subtitle="`${meta.total} تقرير`">
      <template #actions>
        <button
          v-if="auth.can('reports.create')" class="btn btn-primary" type="button"
          @click="editing = null; formOpen = true"
        >
          <BaseIcon name="plus" :size="16" />
          تقرير جديد
        </button>
      </template>
    </PageHeader>

    <InlineNote>
      افتح التقرير لتقرأه كاملاً ثم تقرّر: اعتماد، أو إرجاع لكاتبه بملاحظة، أو نشر للكافل.
      ولا يعتمد المشرف تقريره بنفسه.
    </InlineNote>

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
      <label v-if="auth.can('reports.create')" class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.returned" type="checkbox" class="accent-clay-600" /> المُرجَعة للتعديل
      </label>
    </FilterBar>

    <DataTable :columns="columns" :rows="items" :loading="loading" clickable @row-click="openReview($event.id)">
      <template #orphan="{ row }">
        <span class="font-medium text-sand-900">{{ row.orphan?.name }}</span>
        <span class="block text-xs text-sand-500 num">{{ row.orphan?.reference_code }}</span>
      </template>
      <template #title="{ row }">
        <span class="line-clamp-1">{{ row.title }}</span>
      </template>
      <template #visit_date="{ row }">{{ date(row.visit_date) }}</template>
      <template #status="{ row }">
        <span class="inline-flex items-center gap-1.5">
          <StatusBadge :label="row.status_label" :value="row.status" />
          <span
            v-if="row.returned_at"
            class="text-[0.7rem] font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5"
          >
            أُرجع للتعديل
          </span>
        </span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <ReportFormModal
      :open="formOpen" :orphans="orphans" :report="editing"
      @close="formOpen = false; editing = null" @saved="onSaved"
    />

    <ReportReviewDrawer
      :report-id="formOpen ? null : reviewId" :order="order"
      @close="closeReview" @open="openReview" @changed="load" @edit="openEdit"
    />
  </div>
</template>

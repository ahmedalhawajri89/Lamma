<script setup>
import { ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { date, dateTime, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import FormModal from '@/components/FormModal.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import VisitFormModal from '@/components/VisitFormModal.vue'

const auth = useAuthStore()

const { items, meta, loading, filters, reset, load, error } = useResource(admin.visits.list, {
  q: '', supervisor_id: '', from: '', to: '', overdue: false,
})

const columns = [
  { key: 'visited_at', label: 'تاريخ الزيارة' },
  { key: 'orphan', label: 'الطفل' },
  { key: 'supervisor', label: 'المشرف' },
  { key: 'findings', label: 'الملاحظات' },
  { key: 'next_visit_due', label: 'الزيارة القادمة' },
]

// قائمة المشرفين تصل مع أول صفحة؛ وفلترها لمن يرى أكثر من مشرف
const supervisors = ref([])
watch(meta, (m) => { if (m?.supervisors?.length) supervisors.value = m.supervisors })

const formOpen = ref(false)

/**
 * الزيارة كاملة: كانت الملاحظات مقطوعة بسطر واحد في الجدول، والاحتياجات
 * المسجَّلة لا تظهر في أي مكان.
 */
const viewing = ref(null)

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

    <FilterBar v-model:search="filters.q" search-placeholder="اسم الطفل أو رمزه…" @reset="reset">
      <select
        v-if="supervisors.length > 1 && !auth.user?.staff_profile?.assigned_country_id"
        v-model="filters.supervisor_id" class="input !w-auto text-sm" aria-label="المشرف"
      >
        <option value="">كل المشرفين</option>
        <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <label class="flex items-center gap-1.5 text-sm text-sand-600">
        من <input v-model="filters.from" type="date" lang="en" class="input !w-auto text-sm" />
      </label>
      <label class="flex items-center gap-1.5 text-sm text-sand-600">
        إلى <input v-model="filters.to" type="date" lang="en" class="input !w-auto text-sm" />
      </label>
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.overdue" type="checkbox" class="accent-clay-600" /> المتأخرة فقط
      </label>
    </FilterBar>

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading"
      clickable @retry="load" @row-click="viewing = $event"
    >
      <template #visited_at="{ row }">{{ dateTime(row.visited_at) }}</template>
      <template #orphan="{ row }">
        <RouterLink :to="`/orphans/${row.orphan?.id}`" class="text-clay-700 hover:underline" @click.stop>
          {{ row.orphan?.name }}
        </RouterLink>
        <span class="block text-xs text-sand-500 num">{{ row.orphan?.reference_code }}</span>
      </template>
      <template #supervisor="{ row }">{{ row.supervisor ?? EMPTY }}</template>
      <template #findings="{ row }">
        <span class="line-clamp-1 max-w-md text-sand-700">{{ row.findings }}</span>
        <span v-if="row.needs_flagged?.length" class="text-xs text-amber-700">
          {{ row.needs_flagged.length }} {{ row.needs_flagged.length === 1 ? 'احتياج' : 'احتياجات' }}
        </span>
      </template>
      <template #next_visit_due="{ row }">
        <span :class="row.is_overdue ? 'text-rose-700 font-medium' : ''">{{ date(row.next_visit_due) }}</span>
        <span v-if="row.is_overdue" class="block text-xs text-rose-700">متأخرة</span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <VisitFormModal
      :open="formOpen"
      @close="formOpen = false" @saved="onSaved"
    />

    <!-- تفاصيل الزيارة -->
    <FormModal
      :open="viewing !== null" wide
      :title="viewing ? `زيارة ${viewing.orphan?.name ?? ''}` : 'زيارة'"
      :subtitle="viewing ? `${dateTime(viewing.visited_at)} · ${viewing.supervisor ?? ''}` : ''"
      @close="viewing = null"
    >
      <template v-if="viewing">
        <section>
          <h3 class="text-xs font-semibold text-sand-500 mb-1.5">الملاحظات</h3>
          <p class="text-sand-800 leading-loose whitespace-pre-line">{{ viewing.findings }}</p>
        </section>

        <section v-if="viewing.needs_flagged?.length">
          <h3 class="text-xs font-semibold text-sand-500 mb-1.5">احتياجات رُصدت</h3>
          <ul class="flex flex-wrap gap-1.5">
            <li v-for="n in viewing.needs_flagged" :key="n" class="badge bg-amber-100 text-amber-800">{{ n }}</li>
          </ul>
        </section>

        <dl class="grid sm:grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="text-xs text-sand-500">الزيارة القادمة</dt>
            <dd :class="viewing.is_overdue ? 'text-rose-700 font-medium' : 'text-sand-900'">
              {{ date(viewing.next_visit_due) }}<template v-if="viewing.is_overdue"> · متأخرة</template>
            </dd>
          </div>
          <div v-if="viewing.coordinates">
            <dt class="text-xs text-sand-500">الموقع</dt>
            <dd>
              <a
                :href="`https://www.openstreetmap.org/?mlat=${viewing.coordinates.lat}&mlon=${viewing.coordinates.lng}#map=17/${viewing.coordinates.lat}/${viewing.coordinates.lng}`"
                target="_blank" rel="noopener" class="text-clay-700 hover:underline num"
              >{{ viewing.coordinates.lat.toFixed(5) }}, {{ viewing.coordinates.lng.toFixed(5) }}</a>
            </dd>
          </div>
        </dl>
      </template>

      <template #actions="{ close }">
        <RouterLink v-if="viewing?.orphan" :to="`/orphans/${viewing.orphan.id}`" class="btn btn-outline">ملف الطفل</RouterLink>
        <button class="btn btn-primary" type="button" @click="close">إغلاق</button>
      </template>
    </FormModal>
  </div>
</template>

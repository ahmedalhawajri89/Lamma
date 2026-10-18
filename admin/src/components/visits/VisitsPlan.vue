<script setup>
/**
 * خطة الزيارات: كل طفل مفتوح الملف بموعد زيارته القادمة.
 *
 * الطفل الذي لم يُزر قط لم يكن يظهر في أي مكان (السجل يعرض ما حدث)، فكان
 * يُنسى تماماً. هنا أولاً، ثم من فات موعده، ثم الأقرب استحقاقاً.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { date, num, relative } from '@/utils/format'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const emit = defineEmits(['record'])
const auth = useAuthStore()

const DEFAULTS = { q: '', bucket: '' }
const { items, meta, loading, error, filters, reset, load } = useResource(admin.visits.schedule, DEFAULTS)
defineExpose({ load })

const BUCKETS = [
  { value: 'overdue', label: 'فات موعدها' },
  { value: 'never', label: 'لم يُزر قط' },
  { value: 'week', label: 'هذا الأسبوع' },
  { value: 'month', label: 'خلال 30 يوماً' },
  { value: 'later', label: 'لاحقاً' },
]

const columns = [
  { key: 'orphan', label: 'الطفل' },
  { key: 'due', label: 'موعد الزيارة' },
  { key: 'last', label: 'آخر زيارة' },
  { key: 'action', label: '', align: 'end' },
]

const dirty = computed(() => filters.q !== '' || filters.bucket !== '')

/** العدد مع تمييزه: يوم واحد، يومين، 3 أيام، 12 يوماً */
const daysLabel = (n) => (n === 1 ? 'يوم واحد' : n === 2 ? 'يومين' : n <= 10 ? `${num(n)} أيام` : `${num(n)} يوماً`)

/** الموعد بلغة من يخطّط يومه: «متأخرة 12 يوماً»، «اليوم»، «بعد 3 أيام» */
function due(row) {
  if (!row.last_visited_at) return { text: 'لم يُزر قط', tone: 'bg-rose-600/10 text-rose-700' }
  const d = row.days
  if (d === null) return { text: 'بلا موعد', tone: 'bg-sand-100 text-sand-600' }
  if (d < 0) return { text: `متأخرة ${daysLabel(-d)}`, tone: 'bg-rose-600/10 text-rose-700' }
  if (d === 0) return { text: 'اليوم', tone: 'bg-amber-100 text-amber-800' }
  if (d <= 7) return { text: `بعد ${daysLabel(d)}`, tone: 'bg-amber-100 text-amber-800' }
  return { text: date(row.next_due), tone: 'bg-sand-100 text-sand-700' }
}

const subtitle = computed(() => {
  const c = meta.value.bucket_counts ?? {}
  const parts = [`${num(meta.value.all_count ?? 0)} طفلاً مفتوح الملف`]
  if (c.overdue) parts.push(`${num(c.overdue)} فات موعدهم`)
  if (c.never) parts.push(`${num(c.never)} لم يُزاروا قط`)
  return parts.join(' · ')
})
</script>

<template>
  <div>
    <p class="text-sm text-sand-600 mb-3" aria-live="polite">{{ subtitle }}</p>

    <StatusTabs
      v-model="filters.bucket" :options="BUCKETS"
      :counts="meta.bucket_counts ?? {}" :total="meta.all_count ?? null" label="موعد الزيارة"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="ابحث باسم الطفل أو معرّفه…" :dirty="dirty" @reset="reset" />

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading" row-key="orphan_id"
      :empty-text="filters.bucket === 'overdue' ? 'لا زيارات فات موعدها.' : 'لا أطفال في هذا العرض.'"
      @retry="load"
    >
      <template #orphan="{ row }">
        <span class="flex items-center gap-3 min-w-0">
          <img v-if="row.orphan.photo_url" :src="row.orphan.photo_url" alt="" class="size-9 rounded-full object-cover shrink-0" width="36" height="36" loading="lazy" />
          <span v-else class="size-9 rounded-full bg-clay-50 text-clay-700 grid place-items-center text-sm font-semibold shrink-0">{{ row.orphan.name?.charAt(0) }}</span>
          <span class="min-w-0">
            <RouterLink :to="`/orphans/${row.orphan.id}?tab=followup`" class="block font-medium text-sand-900 hover:text-clay-700 truncate">
              {{ row.orphan.full_name }}
            </RouterLink>
            <span class="block text-xs text-sand-500">
              <span class="num">{{ row.orphan.reference_code }}</span><template v-if="row.orphan.city"> · {{ row.orphan.city }}</template>
            </span>
          </span>
        </span>
      </template>

      <template #due="{ row }">
        <span class="badge" :class="due(row).tone">{{ due(row).text }}</span>
        <span v-if="row.last_visited_at && row.days !== null && row.days > 7" class="sr-only">{{ date(row.next_due) }}</span>
      </template>

      <template #last="{ row }">
        <template v-if="row.last_visited_at">
          <span class="text-sm text-sand-800" :title="date(row.last_visited_at)">{{ relative(row.last_visited_at) }}</span>
          <span v-if="row.last_supervisor" class="block text-xs text-sand-500">{{ row.last_supervisor }}</span>
        </template>
        <span v-else class="text-xs text-sand-400">لا زيارات</span>
      </template>

      <template #action="{ row }">
        <button
          v-if="auth.can('visits.record')" class="btn btn-outline !py-1.5 text-sm" type="button"
          @click.stop="emit('record', row.orphan)"
        >
          <BaseIcon name="plus" :size="14" /> سجّل زيارة
        </button>
      </template>

      <template #empty-action>
        <button v-if="dirty" class="btn btn-outline mt-4" type="button" @click="reset">مسح الفلاتر</button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

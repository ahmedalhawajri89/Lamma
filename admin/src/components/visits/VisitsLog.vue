<script setup>
/**
 * سجل الزيارات: ما رآه المشرفون، والاحتياجات التي رصدوها.
 *
 * الاحتياجات أعلامٌ مغلقة لا نصٌّ حرّ، لأجل سؤالٍ واحد: «كم طفلاً يحتاج
 * دعماً نفسياً؟». وكان لا يُجاب عنه في أي مكان. هنا يُجاب عنه للفترة
 * المختارة، وكل احتياجٍ فلترٌ بنقرة.
 */
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { date, dateTime, num, relative, EMPTY } from '@/utils/format'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import FormModal from '@/components/FormModal.vue'
import PeriodSelect from '@/components/PeriodSelect.vue'

const auth = useAuthStore()

const DEFAULTS = { q: '', supervisor_id: '', from: '', to: '', need: '' }
const { items, meta, loading, filters, reset, load, error } = useResource(admin.visits.list, DEFAULTS)
defineExpose({ load })

const dirty = computed(() => Object.keys(DEFAULTS).some((k) => filters[k] !== DEFAULTS[k]))

const columns = [
  { key: 'orphan', label: 'الطفل' },
  { key: 'visited_at', label: 'الزيارة' },
  { key: 'findings', label: 'ما رُصد' },
  { key: 'next_visit_due', label: 'الزيارة التالية' },
]

// قائمة المشرفين تصل مع أول صفحة
const supervisors = ref([])
watch(meta, (m) => { if (m?.supervisors?.length) supervisors.value = m.supervisors })

const period = ref(null)
function resetAll() { reset(); period.value?.reset() }

const needs = computed(() => Object.entries(meta.value.needs ?? {}))

const viewing = ref(null)
</script>

<template>
  <div>
    <!-- ما رُصد في الفترة: كل احتياج بعدد أطفاله، وفلترٌ بنقرة -->
    <section v-if="needs.length || filters.need" class="card px-4 py-3 mb-4" aria-label="الاحتياجات المرصودة">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-semibold text-sand-500 me-1">احتياجات رُصدت</span>
        <button
          v-for="[need, n] in needs" :key="need" type="button" class="chip"
          :aria-pressed="filters.need === need" @click="filters.need = filters.need === need ? '' : need"
        >
          {{ need }} <span class="chip__count">{{ num(n) }} {{ n === 1 ? 'طفل' : 'أطفال' }}</span>
        </button>
      </div>
    </section>

    <FilterBar v-model:search="filters.q" search-placeholder="اسم الطفل أو رمزه…" :dirty="dirty" @reset="resetAll">
      <PeriodSelect ref="period" v-model:from="filters.from" v-model:to="filters.to" :presets="['week', 'days30', 'month', 'quarter']" />
      <select
        v-if="supervisors.length > 1 && (auth.isAdmin || !auth.user?.staff_profile?.assigned_country_id)"
        v-model="filters.supervisor_id" class="input !w-auto text-sm" aria-label="المشرف"
      >
        <option value="">كل المشرفين</option>
        <option v-for="s in supervisors" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
      </select>
    </FilterBar>

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading"
      :empty-text="dirty ? 'لا زيارات تطابق هذه الفلاتر.' : 'لا زيارات مسجّلة بعد.'"
      clickable @retry="load" @row-click="viewing = $event"
    >
      <template #orphan="{ row }">
        <span class="flex flex-col items-start">
          <RouterLink :to="`/orphans/${row.orphan?.id}?tab=followup`" class="font-medium text-sand-900 hover:text-clay-700" @click.stop>
            {{ row.orphan?.name }}
          </RouterLink>
          <span class="text-xs text-sand-500 num">{{ row.orphan?.reference_code }}</span>
        </span>
      </template>

      <template #visited_at="{ row }">
        <span class="text-sm" :title="dateTime(row.visited_at)">{{ date(row.visited_at) }}</span>
        <span class="block text-xs text-sand-500">{{ row.supervisor ?? EMPTY }}</span>
      </template>

      <template #findings="{ row }">
        <span class="line-clamp-2 max-w-lg text-sand-700">{{ row.findings }}</span>
        <span v-if="row.needs_flagged?.length" class="flex flex-wrap gap-1 mt-1">
          <span v-for="n in row.needs_flagged" :key="n" class="badge bg-amber-50 text-amber-800 !text-[0.7rem]">{{ n }}</span>
        </span>
      </template>

      <template #next_visit_due="{ row }">
        <span :class="row.is_overdue ? 'text-rose-700 font-medium' : 'text-sand-700'">{{ date(row.next_visit_due) }}</span>
        <span v-if="row.is_overdue" class="block text-xs text-rose-700">فات موعدها</span>
      </template>

      <template #empty-action>
        <button v-if="dirty" class="btn btn-outline mt-4" type="button" @click="resetAll">مسح الفلاتر</button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <!-- تفاصيل الزيارة -->
    <FormModal
      :open="viewing !== null" wide
      :title="viewing ? `زيارة ${viewing.orphan?.name ?? ''}` : 'زيارة'"
      :subtitle="viewing ? `${dateTime(viewing.visited_at)} · ${viewing.supervisor ?? ''} · ${relative(viewing.visited_at)}` : ''"
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
            <dt class="text-xs text-sand-500">الزيارة التالية</dt>
            <dd :class="viewing.is_overdue ? 'text-rose-700 font-medium' : 'text-sand-900'">
              {{ date(viewing.next_visit_due) }}<template v-if="viewing.is_overdue"> · فات موعدها</template>
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
        <RouterLink v-if="viewing?.orphan" :to="`/orphans/${viewing.orphan.id}?tab=followup`" class="btn btn-outline">ملف الطفل</RouterLink>
        <button class="btn btn-primary" type="button" @click="close">إغلاق</button>
      </template>
    </FormModal>
  </div>
</template>

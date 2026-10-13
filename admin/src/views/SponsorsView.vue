<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { money, date, num, relative, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const router = useRouter()

const DEFAULTS = { q: '', segment: '', country_id: '', zakat_only: false, overdue: false, sort: 'id', dir: 'desc' }
const { items, meta, loading, filters, reset, error, load } = useResource(admin.sponsors.list, DEFAULTS)

/**
 * مراحل الكافل. «سابق» و«لم يكفل بعد» هما من يُتواصل معهم: من توقّف، ومن
 * سجّل ولم يبدأ. وكانوا مختلطين بالنشطين في قائمة واحدة بلا تمييز.
 */
const SEGMENTS = [
  { value: 'active', label: 'نشط' },
  { value: 'paused', label: 'موقوف مؤقتاً' },
  { value: 'pending', label: 'بانتظار الدفع' },
  { value: 'past', label: 'سابق' },
  { value: 'never', label: 'لم يكفل بعد' },
]

const columns = [
  { key: 'name', label: 'الكافل' },
  { key: 'country', label: 'الدولة' },
  { key: 'sponsorships', label: 'الكفالات' },
  { key: 'monthly_commitment', label: 'التزام شهري', sortable: true, align: 'end' },
  { key: 'total_given', label: 'ما قدّمه', sortable: true, align: 'end' },
  { key: 'last_payment_at', label: 'آخر دفعة', sortable: true },
]

function onSort(key) {
  if (filters.sort === key) filters.dir = filters.dir === 'asc' ? 'desc' : 'asc'
  else { filters.sort = key; filters.dir = 'desc' }
}

const dirty = computed(() => ['q', 'segment', 'country_id', 'zakat_only', 'overdue'].some((k) => filters[k] !== DEFAULTS[k]))

const subtitle = computed(() => {
  if (loading.value && !meta.value.total) return 'جارٍ التحميل…'
  const parts = [`${num(meta.value.all_count ?? meta.value.total)} كافل`]
  if (meta.value.segment_counts?.active) parts.push(`${num(meta.value.segment_counts.active)} نشطون`)
  if (meta.value.monthly_commitment) parts.push(`التزام شهري ${money(meta.value.monthly_commitment)}`)
  return parts.join(' · ')
})

const countries = ref([])
onMounted(async () => {
  try {
    const { data } = await admin.references()
    countries.value = data.data?.countries ?? []
  } catch {
    // فلتر الدولة يغيب، والقائمة تعمل
  }
})
</script>

<template>
  <div>
    <PageHeader title="الكفلاء" :subtitle="subtitle" />

    <StatusTabs
      v-model="filters.segment" :options="SEGMENTS"
      :counts="meta.segment_counts ?? {}" :total="meta.all_count ?? null" label="مرحلة الكافل"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="اسم أو بريد أو رمز…" :dirty="dirty" @reset="reset">
      <select v-if="countries.length" v-model="filters.country_id" class="input !w-auto text-sm" aria-label="دولة الكافل">
        <option value="">كل الدول</option>
        <option v-for="c in countries" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
      </select>

      <!-- من فات موعد قسطه: يُتابَع قبل أن تسقط كفالته -->
      <button type="button" class="chip" :aria-pressed="filters.overdue" @click="filters.overdue = !filters.overdue">
        <BaseIcon name="clock" :size="14" /> قسطه متأخر
        <span v-if="meta.overdue_count" class="chip__count">{{ num(meta.overdue_count) }}</span>
      </button>
      <button type="button" class="chip" :aria-pressed="filters.zakat_only" @click="filters.zakat_only = !filters.zakat_only">
        يفضّل الزكاة
      </button>
    </FilterBar>

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading"
      :sort="filters.sort" :dir="filters.dir" @sort="onSort"
      :empty-text="dirty ? 'لا كفلاء يطابقون هذه الفلاتر.' : 'لا كفلاء بعد.'"
      clickable @retry="load" @row-click="(r) => router.push(`/sponsors/${r.id}`)"
    >
      <template #name="{ row }">
        <span class="flex items-center gap-3 min-w-0">
          <span class="size-9 rounded-full bg-amber-50 text-amber-800 grid place-items-center text-sm font-semibold shrink-0">
            {{ row.name?.charAt(0) ?? '؟' }}
          </span>
          <span class="min-w-0">
            <span class="flex items-center gap-1.5">
              <!--
                المجهولية للموقع العام لا للإدارة: الموظف يعرف مع من يتعامل
              -->
              <RouterLink :to="`/sponsors/${row.id}`" class="font-medium text-sand-900 hover:text-clay-700 truncate" @click.stop>
                {{ row.name ?? EMPTY }}
              </RouterLink>
              <span v-if="row.is_anonymous" class="badge bg-sand-100 text-sand-600">مجهول للعامة</span>
              <span v-if="row.overdue_sponsorships_count" class="badge bg-rose-600/10 text-rose-700">قسطه متأخر</span>
            </span>
            <span class="block text-xs text-sand-500"><span class="num">{{ row.sponsor_code }}</span> · <span class="num" dir="ltr">{{ row.email }}</span></span>
          </span>
        </span>
      </template>

      <template #country="{ row }">{{ row.country?.name ?? EMPTY }}</template>

      <template #sponsorships="{ row }">
        <span v-if="row.sponsorships_count" class="text-sm">
          <span class="num font-medium">{{ row.active_sponsorships_count ?? 0 }}</span>
          <span class="text-sand-500"> نشطة من <span class="num">{{ row.sponsorships_count }}</span></span>
        </span>
        <span v-else class="text-xs text-sand-400">لم يكفل بعد</span>
      </template>

      <template #monthly_commitment="{ row }">
        <span v-if="row.monthly_commitment" class="num">{{ money(row.monthly_commitment) }}</span>
        <span v-else class="text-sand-400">·</span>
      </template>

      <template #total_given="{ row }">
        <span class="num font-medium" :class="row.total_given ? '' : 'text-sand-400 font-normal'">{{ money(row.total_given ?? 0) }}</span>
      </template>

      <template #last_payment_at="{ row }">
        <span v-if="row.last_payment_at" class="text-sm" :title="date(row.last_payment_at)">{{ relative(row.last_payment_at) }}</span>
        <span v-else class="text-xs text-sand-400">لا دفعات</span>
      </template>

      <template #empty-action>
        <button v-if="dirty" class="btn btn-outline mt-4" type="button" @click="reset">مسح الفلاتر</button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

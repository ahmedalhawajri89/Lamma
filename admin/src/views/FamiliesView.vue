<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { money, num, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import FamilyFormModal from '@/components/FamilyFormModal.vue'

const router = useRouter()
const auth = useAuthStore()

const DEFAULTS = { q: '', country_id: '', housing_status: '', without_orphans: false, sort: 'id', dir: 'desc' }
const { items, meta, loading, filters, reset, load, error } = useResource(admin.families.list, DEFAULTS)

/** السكن تبويباتٌ بأعدادها: النازحة أولاً بعد «الكل»، فهي الأشد حاجة */
const HOUSING = [
  { value: 'displaced', label: 'نزوح' },
  { value: 'hosted', label: 'استضافة' },
  { value: 'rented', label: 'إيجار' },
  { value: 'owned', label: 'ملك' },
]

/**
 * الأسرة تُقرأ بوصيّها ومكانها؛ ودخل الفرد بجانب دخل الأسرة، لأن $120
 * لأسرة من سبعة غير $120 لأسرة من اثنين. وهو ما يُرتَّب به «الأشد حاجة».
 */
const columns = [
  { key: 'family', label: 'الأسرة' },
  { key: 'location', label: 'المكان' },
  { key: 'orphans_count', label: 'الأيتام', sortable: true, align: 'end' },
  { key: 'members_count', label: 'الأفراد', sortable: true, align: 'end' },
  { key: 'monthly_income', label: 'الدخل الشهري', sortable: true, sortKey: 'per_capita', align: 'end' },
  { key: 'housing', label: 'السكن' },
]

function onSort(key) {
  if (filters.sort === key) {
    filters.dir = filters.dir === 'asc' ? 'desc' : 'asc'
  } else {
    filters.sort = key
    // دخل الفرد يبدأ بالأقل (الأحوج)، والأعداد بالأكثر
    filters.dir = key === 'per_capita' ? 'asc' : 'desc'
  }
}

const countries = computed(() => (auth.isAdmin || !auth.user?.staff_profile?.assigned_country_id ? references.value.countries ?? [] : []))

const dirty = computed(() => ['q', 'country_id', 'housing_status', 'without_orphans'].some((k) => filters[k] !== DEFAULTS[k]))

const subtitle = computed(() => {
  if (loading.value && !meta.value.total) return 'جارٍ التحميل…'
  const parts = [`${num(meta.value.all_count ?? meta.value.total)} أسرة`]
  const displaced = meta.value.housing_counts?.displaced
  if (displaced) parts.push(`${num(displaced)} نازحة`)
  if (meta.value.without_orphans_count) parts.push(`${num(meta.value.without_orphans_count)} بلا أيتام مسجّلين`)
  return parts.join(' · ')
})

/* ── الإنشاء ─────────────────────────────────────────────────────────── */

const formOpen = ref(false)
const references = ref({})

async function loadReferences() {
  try {
    const { data } = await admin.references()
    references.value = data.data ?? {}
  } catch {
    // فشل القوائم لا يمنع تصفّح الجدول، النافذة وحدها تتعطّل
  }
}

/** أسرة جديدة تُفتح صفحتها: الخطوة التالية عادةً إضافة أيتامها */
function onSaved(family) {
  formOpen.value = false
  if (family?.id) router.push(`/families/${family.id}`)
  else load()
}

onMounted(loadReferences)
</script>

<template>
  <div>
    <PageHeader title="الأسر" :subtitle="subtitle">
      <template #actions>
        <button
          v-if="auth.can('families.manage')" class="btn btn-primary" type="button"
          @click="formOpen = true"
        >
          <BaseIcon name="plus" :size="16" />
          أسرة جديدة
        </button>
      </template>
    </PageHeader>

    <StatusTabs
      v-model="filters.housing_status" :options="HOUSING"
      :counts="meta.housing_counts ?? {}" :total="meta.all_count ?? null"
      label="السكن"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="رمز الأسرة أو اسم الوصي أو اسم يتيم…" :dirty="dirty" @reset="reset">
      <select v-if="countries.length" v-model="filters.country_id" class="input !w-auto text-sm" aria-label="الدولة">
        <option value="">كل الدول</option>
        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>

      <button type="button" class="chip" :aria-pressed="filters.without_orphans" @click="filters.without_orphans = !filters.without_orphans">
        بلا أيتام مسجّلين
        <span v-if="meta.without_orphans_count" class="chip__count">{{ num(meta.without_orphans_count) }}</span>
      </button>

      <button
        type="button" class="chip" :aria-pressed="filters.sort === 'per_capita' && filters.dir === 'asc'"
        @click="filters.sort === 'per_capita' && filters.dir === 'asc' ? (filters.sort = 'id', filters.dir = 'desc') : (filters.sort = 'per_capita', filters.dir = 'asc')"
      >
        الأشد حاجة أولاً
      </button>
    </FilterBar>

    <DataTable :error="error" @retry="load"
      :columns="columns" :rows="items" :loading="loading"
      :sort="filters.sort" :dir="filters.dir" @sort="onSort"
      :empty-text="dirty ? 'لا أسر تطابق هذه الفلاتر.' : 'لا أسر بعد.'"
      clickable @row-click="(r) => router.push(`/families/${r.id}`)"
    >
      <template #family="{ row }">
        <span class="flex items-center gap-3 min-w-0">
          <span class="size-9 rounded-full bg-sand-100 text-sand-600 grid place-items-center shrink-0">
            <BaseIcon name="home" :size="16" />
          </span>
          <span class="min-w-0">
            <RouterLink :to="`/families/${row.id}`" class="block font-medium text-sand-900 hover:text-clay-700 truncate" @click.stop>
              {{ row.guardian?.full_name ?? EMPTY }}
              <span class="text-xs font-normal text-sand-500">({{ row.guardian?.relation_label }})</span>
            </RouterLink>
            <span class="block num text-xs text-sand-500">{{ row.family_code }}</span>
          </span>
        </span>
      </template>

      <template #location="{ row }">
        {{ row.country?.name ?? EMPTY }}<span v-if="row.city?.name" class="text-sand-500">، {{ row.city.name }}</span>
      </template>

      <!-- العدد يفتح أيتام الأسرة في شاشتهم -->
      <template #orphans_count="{ row }">
        <RouterLink
          v-if="row.orphans_count" :to="{ path: '/orphans', query: { family_id: row.id } }"
          class="num text-clay-700 hover:underline" @click.stop
        >{{ row.orphans_count }}</RouterLink>
        <span v-else class="badge bg-amber-50 text-amber-800">لا أيتام</span>
      </template>

      <template #members_count="{ row }"><span class="num">{{ row.members_count }}</span></template>

      <template #monthly_income="{ row }">
        <template v-if="row.monthly_income !== null">
          <span class="num">{{ money(row.monthly_income) }}</span>
          <span v-if="row.per_capita_income !== null" class="block text-xs text-sand-500">
            <span class="num">{{ money(row.per_capita_income) }}</span> للفرد
          </span>
        </template>
        <span v-else class="text-xs text-sand-400">غير مسجّل</span>
      </template>

      <template #housing="{ row }">
        <span class="badge" :class="row.housing_status === 'displaced' ? 'bg-rose-600/10 text-rose-700' : 'bg-sand-100 text-sand-700'">
          {{ row.housing_status_label }}
        </span>
      </template>

      <template #empty-action>
        <button v-if="dirty" class="btn btn-outline mt-4" type="button" @click="reset">مسح الفلاتر</button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <FamilyFormModal
      :open="formOpen" :family="null" :references="references"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { money, num, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import FundingBar from '@/components/FundingBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import OrphanFormModal from '@/components/OrphanFormModal.vue'

const router = useRouter()
const auth = useAuthStore()

const { items, meta, loading, filters, reset, load, error } = useResource(admin.orphans.list, {
  q: '', status: '', country_id: '', program_id: '', family_id: '',
  urgent: false, unfunded: false, unverified: false, sort: 'created_at', dir: 'desc',
})

/** الأعمدة القابلة للترتيب يقبلها الخادم وحدها (OrphanController::SORTS) */
const columns = [
  { key: 'reference_code', label: 'المعرّف', sortable: true },
  { key: 'first_name', label: 'الاسم', sortable: true },
  { key: 'age', label: 'العمر', sortable: true, sortKey: 'date_of_birth' },
  { key: 'country', label: 'الدولة' },
  { key: 'program', label: 'البرنامج' },
  { key: 'funded_percent', label: 'التمويل', sortable: true },
  { key: 'monthly_need', label: 'الحاجة الشهرية', align: 'end', sortable: true },
  { key: 'status', label: 'الحالة' },
]

/** نقرة على عمود: ترتيبه تنازلياً، والثانية تعكسه. والعمر عكس تاريخ الميلاد */
function onSort(key) {
  if (filters.sort === key) {
    filters.dir = filters.dir === 'asc' ? 'desc' : 'asc'
  } else {
    filters.sort = key
    filters.dir = key === 'first_name' || key === 'reference_code' ? 'asc' : 'desc'
  }
}

// المشرف مقيّد ببلده، فلا معنى لفلتر الدولة عنده
const countries = computed(() => (auth.user?.staff_profile?.assigned_country_id ? [] : references.value.countries ?? []))
const programs = computed(() => references.value.programs ?? [])

/* ── الإنشاء ─────────────────────────────────────────────────────────── */

const formOpen = ref(false)
const references = ref({})

/**
 * القوائم المرجعية تُجلب مرة عند فتح الشاشة لا عند كل فتح للنافذة.
 * والأسرة يُبحث عنها في النافذة نفسها (SearchSelect)، لا تُحمَّل كلها هنا.
 */
async function loadReferences() {
  // للفلاتر (الدول والبرامج) ولنافذة الإنشاء معاً
  try {
    const { data } = await admin.references()
    references.value = data.data ?? {}
  } catch {
    // الصمت مقصود: فشل القوائم لا يمنع تصفّح الجدول، والنافذة وحدها تتعطّل
  }
}

function onSaved(orphan) {
  formOpen.value = false
  if (orphan?.id) router.push(`/orphans/${orphan.id}`)
  else load()
}

onMounted(loadReferences)
</script>

<template>
  <div>
    <PageHeader title="الأيتام" :subtitle="loading && !meta.total ? 'جارٍ التحميل…' : `${num(meta.total)} حالة`">
      <template #actions>
        <button
          v-if="auth.can('orphans.create')" class="btn btn-primary" type="button"
          @click="formOpen = true"
        >
          <BaseIcon name="plus" :size="16" />
          ملف جديد
        </button>
      </template>
    </PageHeader>

    <FilterBar v-model:search="filters.q" search-placeholder="ابحث بالاسم أو المعرّف…" @reset="reset">
      <select v-model="filters.status" class="input !w-auto text-sm" aria-label="الحالة">
        <option value="">كل الحالات</option>
        <option value="active">نشط</option>
        <option value="pending_verification">بانتظار التوثيق</option>
        <option value="fully_funded">مكتمل التمويل</option>
        <option value="graduated">متخرّج</option>
        <option value="draft">مسوّدة</option>
        <option value="deceased">متوفّى</option>
      </select>

      <select v-if="countries.length" v-model="filters.country_id" class="input !w-auto text-sm" aria-label="الدولة">
        <option value="">كل الدول</option>
        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>

      <select v-if="programs.length" v-model="filters.program_id" class="input !w-auto text-sm" aria-label="البرنامج">
        <option value="">كل البرامج</option>
        <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>

      <label class="flex items-center gap-1.5 text-sm text-sand-700 cursor-pointer">
        <input v-model="filters.urgent" type="checkbox" class="accent-clay-600" /> عاجل
      </label>
      <label class="flex items-center gap-1.5 text-sm text-sand-700 cursor-pointer">
        <input v-model="filters.unfunded" type="checkbox" class="accent-clay-600" /> غير مكتمل
      </label>
      <label class="flex items-center gap-1.5 text-sm text-sand-700 cursor-pointer">
        <input v-model="filters.unverified" type="checkbox" class="accent-clay-600" /> بلا توثيق
      </label>
    </FilterBar>

    <DataTable :error="error" @retry="load"
      :columns="columns" :rows="items" :loading="loading"
      :sort="filters.sort" :dir="filters.dir" @sort="onSort"
      clickable @row-click="(r) => router.push(`/orphans/${r.id}`)"
    >
      <template #reference_code="{ row }">
        <span class="num text-xs text-sand-600">{{ row.reference_code }}</span>
      </template>

      <template #first_name="{ row }">
        <span class="flex items-center gap-2">
          <img
            v-if="row.photo_url" :src="row.photo_url" alt="" width="30" height="30"
            class="size-[30px] rounded-full object-cover" loading="lazy"
          />
          <span v-else class="size-[30px] rounded-full bg-sand-100 grid place-items-center text-xs text-sand-500">
            {{ row.first_name?.charAt(0) }}
          </span>
          <span class="font-medium">{{ row.first_name }}</span>
          <BaseIcon v-if="row.is_urgent" name="alert" :size="14" class="text-rose-600" role="img" aria-label="عاجل" />
        </span>
      </template>

      <template #age="{ row }"><span class="num">{{ row.age }}</span></template>
      <template #country="{ row }">{{ row.family?.country?.name ?? EMPTY }}</template>
      <template #program="{ row }">{{ row.program?.name ?? EMPTY }}</template>
      <!-- لا شريط تمويل لملف لم يُنشر بعد: «0%» عليه يوحي بإهمالٍ لا وجود له -->
      <template #funded_percent="{ row }">
        <FundingBar v-if="['active', 'fully_funded'].includes(row.status)" :percent="row.funded_percent" />
        <span v-else class="text-xs text-sand-400">لم يُنشر بعد</span>
      </template>
      <template #monthly_need="{ row }"><span class="num">{{ money(row.monthly_need) }}</span></template>
      <template #status="{ row }"><StatusBadge :label="row.status_label" :value="row.status" /></template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <OrphanFormModal
      :open="formOpen" :references="references"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

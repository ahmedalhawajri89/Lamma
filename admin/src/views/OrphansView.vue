<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { money, EMPTY } from '@/utils/format'
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

const { items, meta, loading, filters, reset, load } = useResource(admin.orphans.list, {
  q: '', status: '', urgent: false, unfunded: false, unverified: false, sort: 'created_at', dir: 'desc',
})

const columns = [
  { key: 'reference_code', label: 'المعرّف' },
  { key: 'first_name', label: 'الاسم' },
  { key: 'age', label: 'العمر' },
  { key: 'country', label: 'الدولة' },
  { key: 'program', label: 'البرنامج' },
  { key: 'funded_percent', label: 'التمويل' },
  { key: 'monthly_need', label: 'الحاجة الشهرية', align: 'end' },
  { key: 'status', label: 'الحالة' },
]

/* ── الإنشاء ─────────────────────────────────────────────────────────── */

const formOpen = ref(false)
const references = ref({})
const families = ref([])

/**
 * القوائم المرجعية تُجلب مرة عند فتح الشاشة لا عند كل فتح للنافذة.
 *
 * البرامج والأسر لا تتغيّر بين نقرتين، وإعادة جلبها في كل مرة تُبطئ فتح
 * النافذة بلا أن تعطي المستخدم شيئاً.
 */
async function loadReferences() {
  if (!auth.can('orphans.create')) return

  try {
    const [refs, fams] = await Promise.all([
      admin.references(),
      admin.families.list({ per_page: 200 }),
    ])
    references.value = refs.data.data ?? {}
    families.value = fams.data.data ?? []
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
    <PageHeader title="الأيتام" :subtitle="`${meta.total} حالة`">
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
        <option value="pending_verification">بانتظار التحقق</option>
        <option value="fully_funded">مكتمل التمويل</option>
        <option value="graduated">متخرّج</option>
        <option value="draft">مسوّدة</option>
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

    <DataTable
      :columns="columns" :rows="items" :loading="loading"
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
      <template #funded_percent="{ row }"><FundingBar :percent="row.funded_percent" /></template>
      <template #monthly_need="{ row }"><span class="num">{{ money(row.monthly_need) }}</span></template>
      <template #status="{ row }"><StatusBadge :label="row.status_label" :value="row.status" /></template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <OrphanFormModal
      :open="formOpen" :references="references" :families="families"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { money } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import FamilyFormModal from '@/components/FamilyFormModal.vue'

const auth = useAuthStore()

const { items, meta, loading, filters, reset, load, error } = useResource(admin.families.list, { q: '', country_id: '' })

const columns = [
  { key: 'family_code', label: 'الرمز' },
  { key: 'guardian', label: 'الوصي' },
  { key: 'country', label: 'الموقع' },
  { key: 'orphans_count', label: 'أيتام', align: 'end' },
  { key: 'members_count', label: 'الأفراد', align: 'end' },
  { key: 'monthly_income', label: 'الدخل الشهري', align: 'end' },
  { key: 'housing', label: 'السكن' },
]

const formOpen = ref(false)
const editing = ref(null)
const references = ref({})

async function loadReferences() {
  // للفلتر (لكل من يرى الأسر) ولنافذة الإنشاء

  try {
    const { data } = await admin.references()
    references.value = data.data ?? {}
  } catch {
    // فشل القوائم لا يمنع تصفّح الجدول — النافذة وحدها تتعطّل
  }
}

function create() {
  editing.value = null
  formOpen.value = true
}

/**
 * التحرير يفتح من الصفّ لا من عمود أزرار.
 *
 * الجدول سبعة أعمدة أصلاً، وعمودٌ ثامن للأزرار يضيّق ما قبله على الشاشات
 * الصغيرة. والصفّ كله هدفٌ أكبر من أيقونة.
 */
function edit(family) {
  if (!auth.can('families.manage')) return
  editing.value = family
  formOpen.value = true
}

function onSaved() {
  formOpen.value = false
  load()
}

onMounted(loadReferences)
</script>

<template>
  <div>
    <PageHeader title="الأسر" :subtitle="`${meta.total} أسرة، ومنها تُقرأ الدولة والمدينة والوصي لكل يتيم`">
      <template #actions>
        <button
          v-if="auth.can('families.manage')" class="btn btn-primary" type="button"
          @click="create"
        >
          <BaseIcon name="plus" :size="16" />
          أسرة جديدة
        </button>
      </template>
    </PageHeader>

    <FilterBar v-model:search="filters.q" search-placeholder="رمز الأسرة أو اسم الوصي…" @reset="reset">
      <!-- المشرف مقيّد ببلده، فالفلتر لمن يرى أكثر من بلد -->
      <select
        v-if="!auth.user?.staff_profile?.assigned_country_id && references.countries?.length"
        v-model="filters.country_id" class="input !w-auto text-sm" aria-label="الدولة"
      >
        <option value="">كل الدول</option>
        <option v-for="c in references.countries" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </FilterBar>

    <DataTable :error="error" @retry="load"
      :columns="columns" :rows="items" :loading="loading"
      :clickable="auth.can('families.manage')"
      @row-click="edit"
    >
      <template #family_code="{ row }"><span class="num text-xs text-sand-600">{{ row.family_code }}</span></template>
      <template #guardian="{ row }">
        {{ row.guardian?.full_name }}
        <span class="text-xs text-sand-500">({{ row.guardian?.relation_label }})</span>
      </template>
      <template #country="{ row }">
        {{ row.country?.name }}
        <span class="text-xs text-sand-500">{{ row.city?.name }}</span>
      </template>
      <!-- العدد يفتح أيتام الأسرة نفسها -->
      <template #orphans_count="{ row }">
        <RouterLink
          v-if="row.orphans_count" :to="{ path: '/orphans', query: { family_id: row.id } }"
          class="num text-clay-700 hover:underline" @click.stop
        >{{ row.orphans_count }}</RouterLink>
        <span v-else class="num text-sand-400">0</span>
      </template>
      <template #members_count="{ row }"><span class="num">{{ row.members_count }}</span></template>
      <template #monthly_income="{ row }"><span class="num">{{ money(row.monthly_income) }}</span></template>
      <template #housing="{ row }">
        <span class="badge" :class="row.housing_status === 'displaced' ? 'bg-rose-600/10 text-rose-700' : 'bg-sand-100 text-sand-700'">
          {{ row.housing_status_label }}
        </span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <FamilyFormModal
      :open="formOpen" :family="editing" :references="references"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

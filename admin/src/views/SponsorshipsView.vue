<script setup>
import { ref } from 'vue'
import { confirmDialog } from '@/composables/useConfirm'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import SponsorshipFormModal from '@/components/SponsorshipFormModal.vue'

const auth = useAuthStore()
const ui = useUiStore()
const { items, meta, loading, filters, reset, load } = useResource(admin.sponsorships.list, {
  q: '', status: '', zakat_only: false, due_soon: false,
})

const columns = [
  { key: 'reference_code', label: 'المرجع' },
  { key: 'orphan', label: 'الطفل' },
  { key: 'sponsor', label: 'الكافل' },
  { key: 'monthly_amount', label: 'المبلغ الشهري', align: 'end' },
  { key: 'next_due_at', label: 'القسط القادم' },
  { key: 'status', label: 'الحالة' },
  { key: 'actions', label: 'إجراءات', align: 'end' },
]

const formOpen = ref(false)
const editing = ref(null)

/** الكفالة الملغاة سجلٌّ تاريخي لا تُعدَّل. */
const editable = (row) => auth.can('sponsorships.manage')
  && ['active', 'pending', 'paused'].includes(row.status)

function edit(row) {
  if (!editable(row)) return
  editing.value = row
  formOpen.value = true
}

function onSaved() {
  formOpen.value = false
  load()
}

async function cancel(row) {
  const answer = await confirmDialog({
    title: 'إلغاء الكفالة',
    message: 'يتوقف الخصم من الكافل، ويعود الطفل إلى قائمة من ينتظرون كافلاً.',
    reason: 'optional',
    reasonLabel: 'سبب الإلغاء',
    confirmLabel: 'ألغِ الكفالة',
    danger: true,
  })
  if (!answer) return

  try {
    await admin.sponsorships.cancel(row.id, answer.reason)
    ui.success('أُلغيت الكفالة.')
    load()
  } catch (e) {
    ui.error(e.userMessage)
  }
}
</script>

<template>
  <div>
    <PageHeader title="الكفالات" :subtitle="`${meta.total} كفالة`" />

    <FilterBar v-model:search="filters.q" search-placeholder="ابحث بالمرجع…" @reset="reset">
      <select v-model="filters.status" class="input !w-auto text-sm" aria-label="الحالة">
        <option value="">كل الحالات</option>
        <option value="active">نشطة</option>
        <option value="pending">بانتظار أول دفعة</option>
        <option value="paused">موقوفة</option>
        <option value="cancelled">ملغاة</option>
      </select>
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.zakat_only" type="checkbox" class="accent-clay-600" /> من الزكاة
      </label>
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.due_soon" type="checkbox" class="accent-clay-600" /> تستحق خلال أسبوع
      </label>
    </FilterBar>

    <DataTable :columns="columns" :rows="items" :loading="loading">
      <template #reference_code="{ row }">
        <span class="num text-xs text-sand-600">{{ row.reference_code }}</span>
      </template>
      <template #orphan="{ row }">
        <RouterLink :to="`/orphans/${row.orphan?.id}`" class="text-clay-700 hover:underline">
          {{ row.orphan?.name }}
        </RouterLink>
      </template>
      <template #sponsor="{ row }">{{ row.sponsor?.name ?? row.sponsor?.sponsor_code ?? EMPTY }}</template>
      <template #monthly_amount="{ row }">
        <span class="num">{{ money(row.monthly_amount) }}</span>
        <span v-if="row.is_zakat" class="badge bg-teal-600/10 text-teal-700 ms-1">زكاة</span>
      </template>
      <template #next_due_at="{ row }">{{ date(row.next_due_at) }}</template>
      <template #status="{ row }"><StatusBadge :label="row.status_label" :value="row.status" /></template>
      <template #actions="{ row }">
        <span class="inline-flex items-center gap-0.5">
          <button
            v-if="editable(row)" class="btn btn-ghost p-1.5" type="button"
            title="تعديل" @click.stop="edit(row)"
          >
            <BaseIcon name="edit" :size="14" />
          </button>

          <button
            v-if="editable(row)"
            class="btn btn-ghost !py-1 !px-2 text-xs text-rose-700" type="button"
            @click.stop="cancel(row)"
          >
            إلغاء
          </button>
        </span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <SponsorshipFormModal
      :open="formOpen" :sponsorship="editing"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

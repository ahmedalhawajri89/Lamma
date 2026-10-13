<script setup>
import { onMounted, ref } from 'vue'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, EMPTY } from '@/utils/format'
import { confirmDialog } from '@/composables/useConfirm'
import PageHeader from '@/components/PageHeader.vue'
import InlineNote from '@/components/InlineNote.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import DisbursementFormModal from '@/components/DisbursementFormModal.vue'

const auth = useAuthStore()
const ui = useUiStore()

const { items, meta, loading, filters, reset, load } = useResource(admin.disbursements.list, {
  pending_only: false, from: '', to: '',
})

const columns = [
  { key: 'spent_on', label: 'تاريخ الصرف' },
  { key: 'category', label: 'البند' },
  { key: 'orphan', label: 'الطفل' },
  { key: 'amount', label: 'المبلغ', align: 'end' },
  { key: 'recorder', label: 'سجّله' },
  { key: 'receipt', label: 'الإيصال' },
  { key: 'status', label: 'الاعتماد' },
]

const formOpen = ref(false)
const categories = ref([])
const orphans = ref([])

async function loadReferences() {
  if (!auth.can('disbursements.record')) return

  try {
    const [cats, list] = await Promise.all([
      admin.disbursements.categories(),
      admin.orphans.list({ per_page: 200, sort: 'first_name', dir: 'asc' }),
    ])
    categories.value = cats.data.data ?? []
    orphans.value = list.data.data ?? []
  } catch {
    // فشل القوائم لا يمنع تصفّح الجدول — النافذة وحدها تتعطّل
  }
}

function onSaved() {
  formOpen.value = false
  load()
}

/**
 * الزرّ يظهر لمن يستطيع الاعتماد فعلاً.
 *
 * `DisbursementPolicy` تمنع من سجّل الصرف أن يعتمده — مبدأ العينين، وهو
 * الحارس الوحيد بين خطأٍ في الإدخال ورقمٍ منشور على صفحة الشفافية. وعرضُ
 * الزرّ لصاحب الصرف يعني نقرةً يردّها الخادم بـ٤٠٣ بلا سبب مفهوم.
 */
function canApprove(row) {
  return auth.can('disbursements.approve')
    && row.recorded_by !== auth.user?.id
    && row.updated_by !== auth.user?.id
}

/** المبلغ بعملته، ومعادله بالدولار إن اختلفت */
function amountLabel(row) {
  if (!row.currency || row.currency === 'USD') return money(row.amount)
  return `${row.amount.toLocaleString('en-US', { minimumFractionDigits: 2 })} ${row.currency}`
}

const approving = ref(null)

onMounted(loadReferences)

/**
 * الاعتماد يُنشر في الشفافية ولا يُتراجع عنه، فيُؤكَّد بما سيُعتمد فعلاً.
 * وبلا إيصال يُنبَّه المعتمِد: يعتمد ما لم يرَ دليله.
 */
async function approve(row) {
  const what = [
    row.category?.name,
    row.orphan ? `لـ ${row.orphan.name}` : 'مصروف عام',
    amountLabel(row),
    row.currency && row.currency !== 'USD' ? `(≈ ${money(row.amount_base)})` : '',
  ].filter(Boolean).join(' · ')

  const ok = await confirmDialog({
    title: 'اعتماد الصرف',
    message: `${what}\n${row.has_receipt ? 'راجعتَ الإيصال؟' : 'لا إيصال مرفق بهذا الصرف.'} الاعتماد يُدخله أرقام الشفافية المنشورة ولا يُتراجع عنه.`,
    confirmLabel: 'اعتمد',
  })
  if (!ok) return

  approving.value = row.id
  try {
    await admin.disbursements.approve(row.id)
    ui.success('اعتُمد الصرف. صار جزءاً من أرقام الشفافية المنشورة.')
    load()
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    approving.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="الصرف"
      :subtitle="`${money(meta.sum_all)} إجمالاً · ${money(meta.sum_pending)} بانتظار الاعتماد`"
    >
      <template #actions>
        <button
          v-if="auth.can('disbursements.record')" class="btn btn-primary" type="button"
          @click="formOpen = true"
        >
          <BaseIcon name="plus" :size="16" />
          تسجيل صرف
        </button>
      </template>
    </PageHeader>

    <InlineNote>
      الصرف المعتمَد وحده هو ما تُحسب منه نسب الشفافية المنشورة على الموقع.
    </InlineNote>

    <FilterBar :has-search="false" @reset="reset">
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.pending_only" type="checkbox" class="accent-clay-600" /> بانتظار الاعتماد فقط
      </label>
      <input v-model="filters.from" type="date" class="input !w-auto text-sm" aria-label="من تاريخ" />
      <input v-model="filters.to" type="date" class="input !w-auto text-sm" aria-label="إلى تاريخ" />
    </FilterBar>

    <DataTable :columns="columns" :rows="items" :loading="loading">
      <template #spent_on="{ row }">{{ date(row.spent_on) }}</template>
      <template #category="{ row }">
        <span class="inline-flex items-center gap-1.5">
          <span class="size-2.5 rounded-sm" :style="{ background: row.category?.color }" aria-hidden="true" />
          {{ row.category?.name }}
        </span>
      </template>
      <template #orphan="{ row }">
        <span v-if="row.orphan">{{ row.orphan.name }}</span>
        <span v-else class="text-sand-500 text-xs">مصروف عام</span>
      </template>
      <template #amount="{ row }">
        <span class="num font-medium">{{ amountLabel(row) }}</span>
        <span v-if="row.currency && row.currency !== 'USD'" class="block text-xs text-sand-500 num">≈ {{ money(row.amount_base) }}</span>
      </template>
      <template #receipt="{ row }">
        <a
          v-if="row.receipt_url" :href="row.receipt_url" target="_blank" rel="noopener"
          class="inline-flex items-center gap-1 text-sm text-clay-700 hover:underline"
        >
          <BaseIcon name="eye" :size="14" /> عرض
        </a>
        <span v-else class="text-xs text-sand-400">لا إيصال</span>
      </template>
      <template #recorder="{ row }">{{ row.recorder ?? EMPTY }}</template>
      <template #status="{ row }">
        <span v-if="row.is_approved" class="badge bg-olive-100 text-olive-700">معتمَد</span>
        <button
          v-else-if="canApprove(row)"
          class="btn btn-outline !py-1 !px-2 text-xs" type="button"
          :disabled="approving === row.id" @click="approve(row)"
        >
          اعتماد
        </button>
        <span
          v-else class="badge bg-amber-100 text-amber-700"
        >بانتظار الاعتماد</span>
        <span v-if="!row.is_approved && (row.recorded_by === auth.user?.id || row.updated_by === auth.user?.id)" class="block text-xs text-sand-500 mt-1">
          {{ row.recorded_by === auth.user?.id ? 'سجّلته أنت، فيعتمده غيرك' : 'عدّلته أنت، فيعتمده غيرك' }}
        </span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <DisbursementFormModal
      :open="formOpen" :categories="categories" :orphans="orphans"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

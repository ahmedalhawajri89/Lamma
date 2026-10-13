<script setup>
import { ref } from 'vue'
import { confirmDialog } from '@/composables/useConfirm'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, dateTime, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import PaymentFormModal from '@/components/PaymentFormModal.vue'

const auth = useAuthStore()
const ui = useUiStore()

const { items, meta, loading, filters, reset, load, error } = useResource(admin.payments.list, {
  q: '', status: '', method: '', from: '', to: '',
})

const columns = [
  { key: 'reference_code', label: 'المرجع' },
  { key: 'sponsor', label: 'الكافل' },
  { key: 'orphan', label: 'الطفل' },
  { key: 'amount', label: 'المبلغ', align: 'end' },
  { key: 'method', label: 'الوسيلة' },
  { key: 'paid_at', label: 'تاريخ الدفع' },
  { key: 'status', label: 'الحالة' },
]

const formOpen = ref(false)
const busyId = ref(null)
function onSaved() {
  formOpen.value = false
  load()
}

/**
 * الاسترداد لا يحذف الدفعة: يغيّر حالتها.
 *
 * حذف صفٍّ مالي يجعل التدقيق مستحيلاً — لا يبقى أثرٌ لما وقع ثم رُدّ.
 * والسبب مطلوب لأنه ما سيقرأه المدقّق بعد سنة، لا نحن اليوم.
 */
async function refund(row) {
  const answer = await confirmDialog({
    title: `استرداد الدفعة ${row.reference_code}`,
    message: 'يُسجَّل الاسترداد في السجل المالي ولا يمكن التراجع عنه.',
    reason: 'required',
    reasonLabel: 'سبب الاسترداد',
    confirmLabel: 'سجّل الاسترداد',
    danger: true,
  })
  if (!answer) return
  const reason = answer.reason

  busyId.value = row.id
  try {
    const { data } = await admin.payments.refund(row.id, reason)
    ui.success(data.message ?? 'سُجّل الاسترداد.')
    await load()
  } catch (e) {
    ui.error(e.fieldErrors?.reason?.[0] ?? e.userMessage ?? 'تعذّر الاسترداد.')
  } finally {
    busyId.value = null
  }
}

</script>

<template>
  <div>
    <PageHeader title="المدفوعات" :subtitle="`${meta.settled_count ?? 0} دفعة ناجحة · ${money(meta.settled_amount)}`">
      <template #actions>
        <button
          v-if="auth.can('payments.record')" class="btn btn-primary" type="button"
          @click="formOpen = true"
        >
          <BaseIcon name="plus" :size="16" />
          دفعة يدوية
        </button>
      </template>
    </PageHeader>

    <FilterBar v-model:search="filters.q" search-placeholder="مرجع أو رقم إيصال…" @reset="reset">
      <select v-model="filters.status" class="input !w-auto text-sm" aria-label="الحالة">
        <option value="">كل الحالات</option>
        <option value="succeeded">مكتملة</option>
        <option value="failed">فاشلة</option>
        <option value="refunded">مُستردّة</option>
      </select>
      <select v-model="filters.method" class="input !w-auto text-sm" aria-label="الوسيلة">
        <option value="">كل الوسائل</option>
        <option value="card">بطاقة</option>
        <option value="apple_pay">Apple Pay</option>
        <option value="bank_transfer">تحويل بنكي</option>
        <option value="cash">نقداً</option>
      </select>
      <input v-model="filters.from" type="date" class="input !w-auto text-sm" aria-label="من تاريخ" />
      <input v-model="filters.to" type="date" class="input !w-auto text-sm" aria-label="إلى تاريخ" />
    </FilterBar>

    <DataTable :error="error" @retry="load" :columns="columns" :rows="items" :loading="loading">
      <template #reference_code="{ row }">
        <span class="num text-xs text-sand-600">{{ row.reference_code }}</span>
      </template>
      <template #sponsor="{ row }">{{ row.sponsor?.name ?? EMPTY }}</template>
      <template #orphan="{ row }">{{ row.sponsorship?.orphan?.name ?? EMPTY }}</template>
      <template #amount="{ row }"><span class="num font-medium">{{ money(row.amount, row.currency) }}</span></template>
      <template #method="{ row }">
        {{ row.method_label }}
        <span v-if="row.card" class="text-xs text-sand-500 num">{{ row.card }}</span>
      </template>
      <template #paid_at="{ row }">{{ dateTime(row.paid_at) }}</template>
      <template #status="{ row }">
        <span class="inline-flex items-center gap-2">
          <StatusBadge :label="row.status_label" :value="row.status" />

          <!-- الاسترداد للناجحة وحدها: `PaymentPolicy::refund` تشترط ذلك -->
          <button
            v-if="auth.can('payments.refund') && row.status === 'succeeded'"
            class="btn btn-ghost !py-0.5 !px-1.5 text-xs text-rose-700 hover:bg-rose-600/10"
            type="button" :disabled="busyId === row.id" @click="refund(row)"
          >
            استرداد
          </button>
        </span>
        <p v-if="row.failure_reason" class="text-xs text-sand-500 mt-0.5">{{ row.failure_reason }}</p>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <PaymentFormModal
      :open="formOpen"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

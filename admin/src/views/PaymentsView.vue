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
import FormModal from '@/components/FormModal.vue'

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
const viewing = ref(null)

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
    viewing.value = null
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
    <PageHeader title="المدفوعات" :subtitle="`${meta.total ?? 0} دفعة · المحصّل منها ${money(meta.settled_amount)} (${meta.settled_count ?? 0} ناجحة)`">
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

    <FilterBar v-model:search="filters.q" search-placeholder="مرجع، إيصال، رقم حوالة، أو اسم…" @reset="reset">
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
      <label class="flex items-center gap-1.5 text-sm text-sand-600">
        من <input v-model="filters.from" type="date" lang="en" class="input !w-auto text-sm" />
      </label>
      <label class="flex items-center gap-1.5 text-sm text-sand-600">
        إلى <input v-model="filters.to" type="date" lang="en" class="input !w-auto text-sm" />
      </label>
    </FilterBar>

    <DataTable :error="error" @retry="load" :columns="columns" :rows="items" :loading="loading" clickable @row-click="viewing = $event">
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
      <!-- الفاشلة لا تاريخ دفع لها، فتاريخ المحاولة -->
      <template #paid_at="{ row }">
        {{ dateTime(row.paid_at ?? row.created_at) }}
        <span v-if="!row.paid_at" class="block text-xs text-sand-500">تاريخ المحاولة</span>
      </template>
      <template #status="{ row }">
        <StatusBadge :label="row.status_label" :value="row.status" />
        <p v-if="row.failure_reason" class="text-xs text-sand-500 mt-0.5">{{ row.failure_reason }}</p>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <PaymentFormModal
      :open="formOpen"
      @close="formOpen = false" @saved="onSaved"
    />

    <!--
      تفاصيل الدفعة، ومنها الاسترداد. كان زرّه في كل صف بلون أحمر: إجراءٌ لا
      يُتراجع عنه على بعد نقرة من أي دفعة، دون رؤية تفاصيلها أولاً.
    -->
    <FormModal
      :open="viewing !== null" wide
      :title="viewing ? `الدفعة ${viewing.reference_code}` : 'الدفعة'"
      :subtitle="viewing?.receipt_number ? `إيصال ${viewing.receipt_number}` : ''"
      @close="viewing = null"
    >
      <dl v-if="viewing" class="grid sm:grid-cols-3 gap-3 text-sm">
        <div><dt class="text-xs text-sand-500">الكافل</dt><dd>{{ viewing.sponsor?.name ?? EMPTY }}</dd></div>
        <div><dt class="text-xs text-sand-500">الطفل</dt><dd>{{ viewing.sponsorship?.orphan?.name ?? EMPTY }}</dd></div>
        <div><dt class="text-xs text-sand-500">الكفالة</dt><dd class="num">{{ viewing.sponsorship?.reference_code ?? EMPTY }}</dd></div>
        <div>
          <dt class="text-xs text-sand-500">المبلغ</dt>
          <dd class="num font-medium">{{ money(viewing.amount, viewing.currency) }}</dd>
          <dd v-if="viewing.currency !== 'USD'" class="text-xs text-sand-500 num">≈ {{ money(viewing.amount_base) }}</dd>
        </div>
        <div><dt class="text-xs text-sand-500">الوسيلة</dt><dd>{{ viewing.method_label }} <span v-if="viewing.card" class="num text-sand-500">{{ viewing.card }}</span></dd></div>
        <div><dt class="text-xs text-sand-500">رقم الحوالة أو الإيصال</dt><dd class="num" dir="ltr">{{ viewing.gateway_reference ?? EMPTY }}</dd></div>
        <div><dt class="text-xs text-sand-500">{{ viewing.paid_at ? 'تاريخ الدفع' : 'تاريخ المحاولة' }}</dt><dd>{{ dateTime(viewing.paid_at ?? viewing.created_at) }}</dd></div>
        <div><dt class="text-xs text-sand-500">الحالة</dt><dd><StatusBadge :label="viewing.status_label" :value="viewing.status" /></dd></div>
        <div v-if="viewing.failure_reason"><dt class="text-xs text-sand-500">سبب الفشل</dt><dd>{{ viewing.failure_reason }}</dd></div>
      </dl>

      <p v-if="viewing?.refunded_at" class="text-sm bg-sand-50 rounded-lg px-3 py-2 text-sand-700">
        استُردّت في {{ dateTime(viewing.refunded_at) }}<template v-if="viewing.refund_reason">: {{ viewing.refund_reason }}</template>
      </p>

      <template #actions="{ close }">
        <!-- الاسترداد للناجحة وحدها: `PaymentPolicy::refund` تشترط ذلك -->
        <button
          v-if="viewing && auth.can('payments.refund') && viewing.status === 'succeeded'"
          class="btn btn-outline text-rose-700 me-auto" type="button"
          :disabled="busyId === viewing.id" @click="refund(viewing)"
        >
          <BaseIcon name="undo" :size="15" /> استرداد
        </button>
        <button class="btn btn-primary" type="button" @click="close">إغلاق</button>
      </template>
    </FormModal>
  </div>
</template>

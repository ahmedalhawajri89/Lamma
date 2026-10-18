<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { confirmDialog } from '@/composables/useConfirm'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, dateTime, num, EMPTY } from '@/utils/format'
import { saveBlob } from '@/utils/download'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import PeriodSelect from '@/components/PeriodSelect.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import PaymentFormModal from '@/components/PaymentFormModal.vue'
import FormModal from '@/components/FormModal.vue'
import NewArrivalsBar from '@/components/NewArrivalsBar.vue'

const auth = useAuthStore()
const ui = useUiStore()

const DEFAULTS = { q: '', status: '', method: '', from: '', to: '', unrecovered: false, sort: 'date', dir: 'desc' }
const { items, meta, loading, filters, reset, load, error } = useResource(admin.payments.list, DEFAULTS)

const STATUSES = [
  { value: 'succeeded', label: 'مكتملة' },
  { value: 'failed', label: 'فاشلة' },
  { value: 'refunded', label: 'مُستردّة' },
  { value: 'pending', label: 'معلّقة', rare: true },
]

const columns = [
  { key: 'amount', label: 'الدفعة', sortable: true, sortKey: 'amount_base' },
  { key: 'parties', label: 'الكافل والطفل' },
  { key: 'method', label: 'الوسيلة' },
  { key: 'date', label: 'التاريخ', sortable: true },
  { key: 'status', label: 'الحالة' },
]

function onSort(key) {
  if (filters.sort === key) filters.dir = filters.dir === 'asc' ? 'desc' : 'asc'
  else { filters.sort = key; filters.dir = 'desc' }
}

const period = ref(null)
const dirty = computed(() => ['q', 'status', 'method', 'from', 'to', 'unrecovered'].some((k) => filters[k] !== DEFAULTS[k]))
function resetAll() { reset(); period.value?.reset() }

/* ── التصدير ─────────────────────────────────────────────────────────── */

const exporting = ref(false)
async function exportCsv() {
  exporting.value = true
  try {
    const params = Object.fromEntries(Object.entries(filters).filter(([k, v]) => v !== '' && v !== false && !['page', 'sort', 'dir'].includes(k)))
    saveBlob(await admin.payments.export(params), 'payments.csv')
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر التصدير.')
  } finally {
    exporting.value = false
  }
}

/* ── الدفعة اليدوية والتفاصيل ────────────────────────────────────────── */

const formOpen = ref(false)
const busyId = ref(null)
function onSaved() {
  formOpen.value = false
  load()
}

/**
 * الاسترداد لا يحذف الدفعة: يغيّر حالتها. حذف صفٍّ مالي يجعل التدقيق
 * مستحيلاً، والسبب مطلوب لأنه ما سيقرأه المدقّق بعد سنة.
 */
const viewing = ref(null)

async function refund(row) {
  const answer = await confirmDialog({
    title: `استرداد الدفعة ${row.reference_code}`,
    message: `يُردّ ${money(row.amount, row.currency)} للكافل، ويُسجَّل الاسترداد في السجل المالي. لا يُتراجع عنه.`,
    reason: 'required',
    reasonLabel: 'سبب الاسترداد',
    confirmLabel: 'سجّل الاسترداد',
    danger: true,
  })
  if (!answer) return

  busyId.value = row.id
  try {
    const { data } = await admin.payments.refund(row.id, answer.reason)
    ui.success(data.message ?? 'سُجّل الاسترداد.')
    viewing.value = null
    await load()
  } catch (e) {
    ui.error(e.fieldErrors?.reason?.[0] ?? e.userMessage ?? 'تعذّر الاسترداد.')
  } finally {
    busyId.value = null
  }
}

const time = (v) => (v ? new Intl.DateTimeFormat('ar', { hour: '2-digit', minute: '2-digit' }).format(new Date(v)) : '')
</script>

<template>
  <div>
    <NewArrivalsBar @refresh="load" />
    <PageHeader title="المدفوعات" :subtitle="loading && !meta.total ? 'جارٍ التحميل…' : `${num(meta.all_count ?? meta.total ?? 0)} دفعة`">
      <template #actions>
        <button class="btn btn-outline" type="button" :disabled="exporting || !meta.total" @click="exportCsv">
          <BaseIcon :name="exporting ? 'loader' : 'download'" :size="16" :class="exporting ? 'animate-spin' : ''" />
          تصدير
        </button>
        <button v-if="auth.can('payments.record')" class="btn btn-primary" type="button" @click="formOpen = true">
          <BaseIcon name="plus" :size="16" />
          دفعة يدوية
        </button>
      </template>
    </PageHeader>

    <!--
      ملخّص الفترة في سطرٍ واحد من الأرقام لا بطاقات: ما حُصّل وما فشل وما
      استُرد ونسبة النجاح، تحت الفلاتر نفسها. و«لم تُستدرك» رابطٌ لما يحتاج اتصالاً.
    -->
    <section class="card mb-4 grid grid-cols-2 lg:grid-cols-4 divide-sand-100 lg:divide-x lg:divide-x-reverse" aria-label="ملخّص الفترة">
      <div class="p-4">
        <p class="text-xs text-sand-500">المحصّل</p>
        <p class="num text-xl font-bold text-sand-900 mt-0.5">{{ money(meta.settled_amount ?? 0) }}</p>
        <p class="text-xs text-sand-500"><span class="num">{{ num(meta.settled_count ?? 0) }}</span> دفعة مكتملة</p>
      </div>
      <div class="p-4">
        <p class="text-xs text-sand-500">فاشلة</p>
        <p class="num text-xl font-bold mt-0.5" :class="meta.failed_count ? 'text-rose-700' : 'text-sand-900'">{{ num(meta.failed_count ?? 0) }}</p>
        <button
          v-if="meta.unrecovered_count" type="button" class="block text-xs text-rose-700 hover:underline"
          @click="filters.unrecovered = true; filters.status = ''"
        ><span class="num">{{ num(meta.unrecovered_count) }}</span> لم تُستدرك بعد</button>
        <p v-else class="text-xs text-sand-500">كلها استُدركت</p>
      </div>
      <div class="p-4">
        <p class="text-xs text-sand-500">مُستردّة</p>
        <p class="num text-xl font-bold text-sand-900 mt-0.5">{{ money(meta.refunded_amount ?? 0) }}</p>
        <p class="text-xs text-sand-500"><span class="num">{{ num(meta.refunded_count ?? 0) }}</span> دفعة</p>
      </div>
      <div class="p-4">
        <p class="text-xs text-sand-500">نسبة نجاح الدفع</p>
        <p class="num text-xl font-bold text-sand-900 mt-0.5">{{ meta.success_rate === null || meta.success_rate === undefined ? EMPTY : `${meta.success_rate}%` }}</p>
        <p class="text-xs text-sand-500">من كل المحاولات</p>
      </div>
    </section>

    <StatusTabs
      v-model="filters.status" :options="STATUSES"
      :counts="meta.status_counts ?? {}" :total="meta.all_count ?? null" label="حالة الدفعة"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="مرجع، إيصال، رقم حوالة، اسم، أو رمز كافل…" :dirty="dirty" @reset="resetAll">
      <PeriodSelect ref="period" v-model:from="filters.from" v-model:to="filters.to" />
      <select v-model="filters.method" class="input !w-auto text-sm" aria-label="الوسيلة">
        <option value="">كل الوسائل</option>
        <option value="card">بطاقة</option>
        <option value="apple_pay">Apple Pay</option>
        <option value="bank_transfer">تحويل بنكي</option>
        <option value="cash">نقداً</option>
      </select>
      <button type="button" class="chip" :aria-pressed="filters.unrecovered" @click="filters.unrecovered = !filters.unrecovered">
        <BaseIcon name="alert" :size="14" /> فشلت ولم تُستدرك
        <span v-if="meta.unrecovered_count" class="chip__count">{{ num(meta.unrecovered_count) }}</span>
      </button>
    </FilterBar>

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading"
      :sort="filters.sort" :dir="filters.dir" @sort="onSort"
      :empty-text="dirty ? 'لا دفعات تطابق هذه الفلاتر.' : 'لا دفعات بعد.'"
      clickable @retry="load" @row-click="viewing = $event"
    >
      <template #amount="{ row }">
        <span class="num font-semibold text-sand-900" :class="row.status === 'failed' || row.status === 'refunded' ? 'line-through decoration-sand-300 text-sand-500 font-medium' : ''">
          {{ money(row.amount, row.currency) }}
        </span>
        <span class="block text-xs text-sand-500 num">{{ row.reference_code }}</span>
      </template>

      <template #parties="{ row }">
        <RouterLink v-if="row.sponsor" :to="`/sponsors/${row.sponsor.id}`" class="text-sand-900 hover:text-clay-700" @click.stop>
          {{ row.sponsor.name ?? row.sponsor.sponsor_code }}
        </RouterLink>
        <span class="block text-xs text-sand-500">
          كفالة <RouterLink v-if="row.sponsorship?.orphan" :to="`/orphans/${row.sponsorship.orphan.id}`" class="hover:text-clay-700" @click.stop>{{ row.sponsorship.orphan.name }}</RouterLink>
        </span>
      </template>

      <template #method="{ row }">
        {{ row.method_label }}
        <span v-if="row.card" class="block text-xs text-sand-500 num">{{ row.card }}</span>
      </template>

      <!-- الفاشلة لا تاريخ دفع لها، فتاريخ المحاولة -->
      <template #date="{ row }">
        <span class="text-sm">{{ date(row.paid_at ?? row.created_at) }}</span>
        <span class="block text-xs text-sand-500">{{ row.paid_at ? time(row.paid_at) : 'تاريخ المحاولة' }}</span>
      </template>

      <template #status="{ row }">
        <StatusBadge :label="row.status_label" :value="row.status" />
        <p v-if="row.failure_reason" class="text-xs text-sand-500 mt-0.5">{{ row.failure_reason }}</p>
      </template>

      <template #empty-action>
        <button v-if="dirty" class="btn btn-outline mt-4" type="button" @click="resetAll">مسح الفلاتر</button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <PaymentFormModal :open="formOpen" @close="formOpen = false" @saved="onSaved" />

    <!--
      تفاصيل الدفعة، ومنها الاسترداد. لا زرّ استرداد في الصفوف: إجراءٌ لا
      يُتراجع عنه بعد رؤية الدفعة كاملة.
    -->
    <FormModal
      :open="viewing !== null" wide
      :title="viewing ? `دفعة ${money(viewing.amount, viewing.currency)}` : 'الدفعة'"
      :subtitle="viewing ? `${viewing.reference_code}${viewing.receipt_number ? ` · إيصال ${viewing.receipt_number}` : ''}` : ''"
      @close="viewing = null"
    >
      <template v-if="viewing">
        <div class="flex flex-wrap items-center gap-2">
          <StatusBadge :label="viewing.status_label" :value="viewing.status" />
          <span v-if="viewing.failure_reason" class="text-sm text-sand-600">{{ viewing.failure_reason }}</span>
        </div>

        <dl class="grid sm:grid-cols-3 gap-x-5 gap-y-4 text-sm">
          <div><dt class="text-xs text-sand-500">الكافل</dt>
            <dd><RouterLink v-if="viewing.sponsor" :to="`/sponsors/${viewing.sponsor.id}`" class="text-clay-700 hover:underline">{{ viewing.sponsor.name ?? viewing.sponsor.sponsor_code }}</RouterLink><template v-else>{{ EMPTY }}</template></dd>
          </div>
          <div><dt class="text-xs text-sand-500">الطفل</dt>
            <dd><RouterLink v-if="viewing.sponsorship?.orphan" :to="`/orphans/${viewing.sponsorship.orphan.id}`" class="text-clay-700 hover:underline">{{ viewing.sponsorship.orphan.name }}</RouterLink><template v-else>{{ EMPTY }}</template></dd>
          </div>
          <div><dt class="text-xs text-sand-500">الكفالة</dt>
            <dd><RouterLink v-if="viewing.sponsorship" :to="`/sponsorships?open=${viewing.sponsorship.id}`" class="num text-clay-700 hover:underline">{{ viewing.sponsorship.reference_code }}</RouterLink><template v-else>{{ EMPTY }}</template></dd>
          </div>
          <div>
            <dt class="text-xs text-sand-500">المبلغ</dt>
            <dd class="num font-medium">{{ money(viewing.amount, viewing.currency) }}</dd>
            <dd v-if="viewing.currency !== 'USD'" class="text-xs text-sand-500 num">≈ {{ money(viewing.amount_base) }}</dd>
          </div>
          <div><dt class="text-xs text-sand-500">الوسيلة</dt><dd>{{ viewing.method_label }} <span v-if="viewing.card" class="num text-sand-500">{{ viewing.card }}</span></dd></div>
          <div><dt class="text-xs text-sand-500">رقم الحوالة أو مرجع البوابة</dt><dd class="num" dir="ltr">{{ viewing.gateway_reference ?? EMPTY }}</dd></div>
          <div><dt class="text-xs text-sand-500">{{ viewing.paid_at ? 'تاريخ الدفع' : 'تاريخ المحاولة' }}</dt><dd>{{ dateTime(viewing.paid_at ?? viewing.created_at) }}</dd></div>
          <div v-if="viewing.receipt_number"><dt class="text-xs text-sand-500">الإيصال</dt><dd class="num">{{ viewing.receipt_number }}</dd></div>
        </dl>

        <p v-if="viewing.refunded_at" class="text-sm bg-sand-50 rounded-lg px-3 py-2 text-sand-700">
          استُردّت في {{ dateTime(viewing.refunded_at) }}<template v-if="viewing.refund_reason">: {{ viewing.refund_reason }}</template>
        </p>
      </template>

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

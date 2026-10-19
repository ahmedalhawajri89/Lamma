<script setup>
/**
 * التحويلات الشهرية للجمعيات الشريكة.
 *
 * كل شهر: ما دفعه كفلاء أطفال كل جمعية، ناقص التشغيل، يُحوَّل لها. تُجهَّز
 * مسوّدةً من الدفعات (أول كل شهر تلقائياً، أو من هنا)، فيرسلها المحاسب بمرجع
 * الحوالة، ثم يؤكّد وصولها. والمُرسَل يراه الكافل في «أين ذهب مالك».
 */
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { confirmDialog } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, num, date } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const auth = useAuthStore()
const ui = useUiStore()
const canAct = computed(() => auth.can('disbursements.approve'))

const DEFAULTS = { status: '', period: '' }
const { items, meta, loading, filters, reset, load, error } = useResource(admin.transfers.list, DEFAULTS)
const dirty = computed(() => Boolean(filters.period))

const STATUSES = [
  { value: 'draft', label: 'مسوّدات' },
  { value: 'sent', label: 'أُرسلت' },
  { value: 'confirmed', label: 'وصلت' },
]

const columns = [
  { key: 'association', label: 'الجمعية والشهر' },
  { key: 'money', label: 'وصل − التشغيل (+ الصندوق) = المحوَّل' },
  { key: 'status', label: 'الحالة' },
  { key: 'actions', label: '' },
]

/** «2026-09» ← «سبتمبر 2026» */
const monthLabel = (p) => (p ? new Date(`${p}-01T00:00:00`).toLocaleDateString('ar-u-nu-latn', { month: 'long', year: 'numeric' }) : '')

/* ── التجهيز: شهرٌ مكتمل، والماضي افتراضياً ───────────────────────────── */
const lastMonth = (() => { const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - 1); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` })()
const prepPeriod = ref(lastMonth)
const preparing = ref(false)
async function prepare() {
  preparing.value = true
  try {
    const { data } = await admin.transfers.prepare(prepPeriod.value)
    ui.success(data.message)
    filters.period = prepPeriod.value
    load()
  } catch (e) {
    ui.error(e.fieldErrors?.period?.[0] ?? e.userMessage)
  } finally {
    preparing.value = false
  }
}

/* ── الإرسال والتأكيد ────────────────────────────────────────────────── */
async function send(row) {
  const answer = await confirmDialog({
    title: `إرسال تحويل ${row.association}`,
    message: `${money(row.amount)} عن ${monthLabel(row.period)}، لـ${num(row.children)} طفلاً.\nبعد الإرسال يراه كفلاؤهم في سجلّ أطفالهم، ولا يُعاد حسابه.`,
    reason: 'required', reasonLabel: 'مرجع الحوالة', minLength: 3, maxLength: 80,
    confirmLabel: 'سجّل الإرسال',
  })
  if (!answer) return
  try {
    const { data } = await admin.transfers.send(row.id, answer.reason)
    ui.success(data.message)
    load()
    if (openRow.value?.id === row.id) openTransfer(row)
  } catch (e) {
    ui.error(e.fieldErrors?.bank_reference?.[0] ?? e.userMessage)
  }
}

async function confirmArrival(row) {
  const ok = await confirmDialog({
    title: `تأكيد وصول تحويل ${row.association}`,
    message: `أكّدت الجمعية وصول ${money(row.amount)} بمرجع ${row.bank_reference}؟`,
    confirmLabel: 'تأكّد الوصول',
  })
  if (!ok) return
  try {
    const { data } = await admin.transfers.confirm(row.id)
    ui.success(data.message)
    load()
    if (openRow.value?.id === row.id) openTransfer(row)
  } catch (e) {
    ui.error(e.userMessage)
  }
}

/* ── تفصيل التحويل: حصة كل طفل ──────────────────────────────────────── */
const openRow = ref(null)
const detail = ref(null)
async function openTransfer(row) {
  openRow.value = row
  detail.value = null
  try {
    const { data } = await admin.transfers.show(row.id)
    detail.value = data.data
  } catch (e) {
    ui.error(e.userMessage)
    openRow.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader title="التحويلات للجمعيات" subtitle="ما دفعه كفلاء أطفال كل جمعية في الشهر، ناقص التشغيل، يُحوَّل لها">
      <template v-if="canAct" #actions>
        <span class="flex items-center gap-2">
          <input v-model="prepPeriod" type="month" class="input !w-auto num" dir="ltr" :max="lastMonth" aria-label="الشهر">
          <button class="btn btn-primary" type="button" :disabled="preparing || !prepPeriod" @click="prepare">
            <BaseIcon :name="preparing ? 'loader' : 'download'" :size="16" :class="{ 'animate-spin': preparing }" /> جهّز تحويلات الشهر
          </button>
        </span>
      </template>
    </PageHeader>

    <StatusTabs
      v-model="filters.status" :options="STATUSES" :counts="meta.status_counts ?? {}" :total="meta.all_count ?? null"
      all-label="الكل" label="الحالة"
    />

    <FilterBar :has-search="false" :dirty="dirty" @reset="reset">
      <select v-model="filters.period" class="input !w-auto" aria-label="الشهر">
        <option value="">كل الأشهر</option>
        <option v-for="p in meta.periods ?? []" :key="p" :value="p">{{ monthLabel(p) }}</option>
      </select>
      <p v-if="meta.status_totals?.draft" class="text-sm text-amber-800 whitespace-nowrap shrink-0">
        بانتظار الإرسال: <span class="num font-semibold">{{ money(meta.status_totals.draft) }}</span>
      </p>
    </FilterBar>

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading"
      clickable empty-text="لا تحويلات بهذه الفلاتر. جهّز تحويلات شهرٍ مكتمل من الزرّ في الأعلى."
      @retry="load" @row-click="openTransfer"
    >
      <template #association="{ row }">
        <span class="font-medium">{{ row.association }}</span>
        <span class="block text-xs text-sand-500">{{ monthLabel(row.period) }} · <span class="num">{{ num(row.children) }}</span> طفلاً · <span class="num" dir="ltr">{{ row.reference_code }}</span></span>
      </template>

      <template #money="{ row }">
        <span class="num text-sand-500">{{ money(row.received) }}</span>
        <span class="text-sand-400 mx-1.5">−</span>
        <span class="num text-sand-500">{{ money(row.fee) }}</span>
        <template v-if="row.fund">
          <span class="text-sand-400 mx-1.5">+</span>
          <span class="num text-amber-800" title="ما غطّاه صندوق الاستمرارية">{{ money(row.fund) }}</span>
        </template>
        <span class="text-sand-400 mx-1.5">=</span>
        <span class="num font-semibold text-sand-900">{{ money(row.amount) }}</span>
      </template>

      <template #status="{ row }">
        <StatusBadge :label="row.status_label" :value="row.status === 'sent' ? 'pending' : row.status === 'confirmed' ? 'completed' : 'draft'" />
        <span v-if="row.bank_reference" class="block text-xs text-sand-500 mt-1 num" dir="ltr">{{ row.bank_reference }}</span>
      </template>

      <template #actions="{ row }">
        <span v-if="canAct" class="flex justify-end" @click.stop>
          <button v-if="row.status === 'draft'" type="button" class="btn btn-primary !py-1.5" :disabled="!row.amount" @click="send(row)">أرسل</button>
          <button v-else-if="row.status === 'sent'" type="button" class="btn btn-outline !py-1.5" @click="confirmArrival(row)">
            <BaseIcon name="check" :size="15" /> تأكّد الوصول
          </button>
        </span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <!-- تفصيل التحويل: حصة كل طفل -->
    <Teleport to="body">
      <div v-if="openRow" class="fixed inset-0 z-30 bg-sand-900/30" aria-hidden="true" @click="openRow = null" />
      <section
        v-if="openRow" role="dialog" aria-modal="true" aria-labelledby="trTitle"
        class="fixed inset-y-0 end-0 z-40 w-full sm:w-[34rem] bg-sand-0 border-s border-sand-200 flex flex-col"
        @keydown.esc="openRow = null"
      >
        <header class="flex items-start gap-3 p-5 border-b border-sand-200">
          <div class="flex-1 min-w-0">
            <p class="text-xs text-sand-500">{{ monthLabel(openRow.period) }} · <span class="num" dir="ltr">{{ openRow.reference_code }}</span></p>
            <h2 id="trTitle" class="text-xl font-bold text-sand-900">{{ openRow.association }}</h2>
            <p class="text-sm mt-1"><span class="num font-semibold">{{ money(openRow.amount) }}</span> <span class="text-sand-500">بعد تشغيل <span class="num">{{ money(openRow.fee) }}</span> من <span class="num">{{ money(openRow.received) }}</span><template v-if="openRow.fund">، ومنه <span class="num">{{ money(openRow.fund) }}</span> من صندوق الاستمرارية</template></span></p>
          </div>
          <button class="btn btn-ghost !p-2" type="button" aria-label="إغلاق" @click="openRow = null"><BaseIcon name="close" :size="18" /></button>
        </header>

        <div class="flex-1 overflow-y-auto p-5">
          <div v-if="!detail" class="space-y-2"><div v-for="n in 4" :key="n" class="h-10 rounded bg-sand-100 animate-pulse" /></div>
          <template v-else>
            <p v-if="detail.status === 'draft'" class="rounded-xl bg-amber-50 text-amber-900 text-sm px-4 py-3 mb-4">مسوّدة: تُحدَّث بما يستجدّ من دفعات الشهر حتى تُرسل، ولا يراها الكفلاء بعد.</p>
            <p v-else class="rounded-xl bg-olive-50 text-olive-800 text-sm px-4 py-3 mb-4">
              أُرسل{{ detail.sent_by ? ` بيد ${detail.sent_by}` : '' }} في {{ date(detail.sent_at) }} بمرجع <span class="num" dir="ltr">{{ detail.bank_reference }}</span><template v-if="detail.status === 'confirmed'">، ووصل في {{ date(detail.confirmed_at) }}</template>. يراه الكفلاء في سجلّ أطفالهم.
            </p>

            <h3 class="text-sm font-semibold mb-2">حصة كل طفل</h3>
            <ul class="divide-y divide-sand-100 border border-sand-100 rounded-xl">
              <li v-for="l in detail.lines" :key="l.orphan_id" class="flex items-center gap-3 px-4 py-2.5 text-sm">
                <RouterLink :to="`/orphans/${l.orphan_id}`" class="flex-1 min-w-0 truncate hover:underline">{{ l.orphan }}</RouterLink>
                <span class="text-xs text-sand-500"><span class="num">{{ num(l.payments_count) }}</span> دفعة<template v-if="l.fund"> · والصندوق <span class="num">{{ money(l.fund) }}</span></template></span>
                <span class="num font-semibold w-24 text-end">{{ money(l.amount) }}</span>
              </li>
            </ul>
          </template>
        </div>

        <footer v-if="canAct && detail && detail.status !== 'confirmed'" class="p-4 border-t border-sand-200 flex justify-end">
          <button v-if="detail.status === 'draft'" class="btn btn-primary" type="button" :disabled="!detail.amount" @click="send(openRow)">أرسل التحويل</button>
          <button v-else class="btn btn-outline" type="button" @click="confirmArrival({ ...openRow, bank_reference: detail.bank_reference })"><BaseIcon name="check" :size="15" /> تأكّد الوصول</button>
        </footer>
      </section>
    </Teleport>
  </div>
</template>

<script setup>
/**
 * صندوق الاستمرارية.
 *
 * يدخله نصيبٌ من نسبة التشغيل كل شهر وتبرّعاتٌ له، ويغطّي شهر الطفل حين
 * يتعثّر قسط كافله ولا يُحلّ، حتى أشهرٍ محدودة ثم تنتهي الكفالة. هنا رصيده
 * وقيوده، والكفالات المتعثّرة الآن ليتابعها المحاسب قبل أن يصلها الصندوق.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useForm } from '@/composables/useForm'
import { useAuthStore } from '@/stores/auth'
import { money, num, date } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import StatCard from '@/components/StatCard.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import FormModal from '@/components/FormModal.vue'
import FormField from '@/components/FormField.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const auth = useAuthStore()
const canAct = computed(() => auth.can('disbursements.approve'))

/* ── القيود ─────────────────────────────────────────────────────────── */
const { items, meta, loading, filters, load, error } = useResource(admin.fund.list, { kind: '' })
const summary = computed(() => meta.value.summary ?? {})

const KINDS = [
  { value: 'grant', label: 'تغطيات' },
  { value: 'share', label: 'من التشغيل' },
  { value: 'donation', label: 'تبرّعات' },
]
const columns = [
  { key: 'what', label: 'القيد' },
  { key: 'for', label: 'لمن' },
  { key: 'amount', label: 'المبلغ' },
]
const monthLabel = (p) => (p ? new Date(`${p}-01T00:00:00`).toLocaleDateString('ar-u-nu-latn', { month: 'long', year: 'numeric' }) : '')

/* ── المتعثّر الآن ───────────────────────────────────────────────────── */
const risk = ref([])
const riskLoading = ref(true)
const riskError = ref(null)
async function loadRisk() {
  riskLoading.value = true
  riskError.value = null
  try {
    const { data } = await admin.fund.atRisk()
    risk.value = data.data
  } catch (e) {
    riskError.value = e
  } finally {
    riskLoading.value = false
  }
}
onMounted(loadRisk)

const riskColumns = [
  { key: 'child', label: 'الطفل والكفالة' },
  { key: 'sponsor', label: 'الكافل' },
  { key: 'state', label: 'أين وصل' },
  { key: 'amount', label: 'القسط' },
]
const failingCount = computed(() => risk.value.filter((r) => r.state === 'failing').length)

/* ── التبرّع ─────────────────────────────────────────────────────────── */
const donating = ref(false)
const today = () => new Date().toISOString().slice(0, 10)
const BLANK = { amount: '', received_on: today(), note: '' }
const form = useForm(BLANK, (values) => admin.fund.donate(values))
watch(donating, (open) => { if (open) form.setValues({ ...BLANK, received_on: today() }) })
async function saveDonation() {
  if (await form.submit()) {
    donating.value = false
    load()
  }
}
</script>

<template>
  <div>
    <PageHeader title="صندوق الاستمرارية" subtitle="حين يتعثّر قسط كافلٍ ولا يُحلّ، يغطّي الصندوق شهر الطفل فلا ينقطع ما يصله">
      <template v-if="canAct" #actions>
        <button class="btn btn-primary" type="button" @click="donating = true">
          <BaseIcon name="plus" :size="16" /> سجّل تبرّعاً للصندوق
        </button>
      </template>
    </PageHeader>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <StatCard label="رصيد الصندوق" :value="summary.balance ?? null" format="money" icon="shield" tone="teal" />
      <StatCard label="دخل هذا العام" :value="summary.in_year ?? null" format="money" icon="trend" tone="olive" hint="من التشغيل والتبرّعات" />
      <StatCard label="غطّى هذا العام" :value="summary.out_year ?? null" format="money" icon="handshake" tone="amber" />
      <StatCard label="أطفال لم ينقطع عنهم شيء" :value="summary.children_helped ?? null" icon="children" tone="clay" />
    </div>
    <p v-if="summary.percent !== undefined" class="text-sm text-sand-600 mt-3">
      يدخله <span class="num font-semibold text-sand-900">{{ summary.percent }}%</span> من كل ما يصل (داخل نسبة التشغيل)،
      ويغطّي شهر الطفل بعد <span class="num font-semibold text-sand-900">{{ summary.recovery_days }}</span> يوماً من استحقاق قسطٍ استنفد محاولاته،
      حتى <span class="num font-semibold text-sand-900">{{ summary.max_months }}</span> أشهر بلا دفعة من الكافل، ثم تنتهي الكفالة ويعود الطفل إلى الموقع.
      <RouterLink v-if="auth.can('settings.manage')" to="/settings" class="underline underline-offset-2 hover:text-sand-900">النسبة من الإعدادات</RouterLink>
    </p>

    <!-- المتعثّر الآن -->
    <section class="mt-8" aria-labelledby="riskTitle">
      <header class="flex items-baseline gap-3 mb-3">
        <h2 id="riskTitle" class="text-lg font-bold">المتعثّر الآن</h2>
        <p class="text-sm text-sand-500">
          <template v-if="risk.length">
            <template v-if="failingCount"><span class="num">{{ num(failingCount) }}</span> تُعاد محاولاتها<template v-if="risk.length - failingCount">، و</template></template>
            <template v-if="risk.length - failingCount"><span class="num">{{ num(risk.length - failingCount) }}</span> يغطّيها الصندوق</template>.
            تواصلٌ مع الكافل الآن يغني عن التغطية.
          </template>
        </p>
      </header>
      <DataTable
        :error="riskError" :columns="riskColumns" :rows="risk" :loading="riskLoading"
        empty-text="لا كفالة متعثّرة الآن. كل الأقساط تصل." @retry="loadRisk"
      >
        <template #child="{ row }">
          <RouterLink v-if="row.orphan" :to="`/orphans/${row.orphan.id}`" class="font-medium hover:underline">{{ row.orphan.name }}</RouterLink>
          <span class="block text-xs text-sand-500 num" dir="ltr">{{ row.reference_code }}</span>
        </template>
        <template #sponsor="{ row }">
          <RouterLink :to="`/sponsors/${row.sponsor.id}`" class="font-medium hover:underline">{{ row.sponsor.name }}</RouterLink>
          <span class="block text-xs text-sand-500"><span class="num" dir="ltr">{{ row.sponsor.phone || row.sponsor.email }}</span></span>
        </template>
        <template #state="{ row }">
          <template v-if="row.state === 'failing'">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-600/8 text-rose-700 text-xs font-medium px-2.5 py-1">
              <BaseIcon name="alert" :size="13" /> محاولة <span class="num">{{ num(row.failures) }}</span> من <span class="num">{{ num(row.max_attempts) }}</span> رُفضت
            </span>
            <span class="block text-xs text-sand-500 mt-1">
              <template v-if="row.next_retry_at">المحاولة التالية {{ date(row.next_retry_at) }}</template>
              <template v-else>يغطّيه الصندوق {{ date(row.covers_on) }} إن لم تُحدَّث البطاقة</template>
              <template v-if="row.reason"> · {{ row.reason }}</template>
            </span>
          </template>
          <template v-else>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 text-amber-800 text-xs font-medium px-2.5 py-1">
              <BaseIcon name="shield" :size="13" /> غطّى الصندوق <span class="num">{{ num(row.months_covered) }}</span> من <span class="num">{{ num(summary.max_months ?? 3) }}</span>
            </span>
            <span class="block text-xs text-sand-500 mt-1">القسط القادم {{ date(row.next_due_at) }}</span>
          </template>
        </template>
        <template #amount="{ row }">
          <span class="num font-semibold">{{ money(row.amount) }}</span>
          <span v-if="row.card_last4" class="block text-xs text-sand-500 num" dir="ltr">•••• {{ row.card_last4 }}</span>
        </template>
      </DataTable>
    </section>

    <!-- القيود -->
    <section class="mt-8" aria-labelledby="entriesTitle">
      <h2 id="entriesTitle" class="text-lg font-bold mb-3">القيود</h2>
      <StatusTabs
        v-model="filters.kind" :options="KINDS" :counts="meta.kind_counts ?? {}" :total="meta.all_count ?? null"
        all-label="الكل" label="نوع القيد"
      />
      <DataTable
        :error="error" :columns="columns" :rows="items" :loading="loading"
        empty-text="لا قيود بعد. تُقيَّد حصة الصندوق مع تجهيز تحويلات كل شهر." @retry="load"
      >
        <template #what="{ row }">
          <span class="font-medium">{{ row.kind_label }}</span>
          <span class="block text-xs text-sand-500">{{ monthLabel(row.period) }}<template v-if="row.note"> · {{ row.note }}</template></span>
        </template>
        <template #for="{ row }">
          <template v-if="row.orphan">
            <RouterLink :to="`/orphans/${row.orphan.id}`" class="hover:underline">{{ row.orphan.name }}</RouterLink>
            <span class="block text-xs text-sand-500">{{ row.association }}</span>
          </template>
          <span v-else class="text-sm text-sand-500">{{ row.recorded_by ? `سجّله ${row.recorded_by}` : 'الصندوق' }}</span>
        </template>
        <template #amount="{ row }">
          <span class="num font-semibold" :class="row.is_in ? 'text-olive-700' : 'text-sand-900'" dir="ltr">{{ row.is_in ? '+' : '−' }}{{ money(row.amount) }}</span>
        </template>
      </DataTable>
      <PaginationBar v-model="filters.page" :meta="meta" />
    </section>

    <FormModal
      :open="donating" title="تبرّعٌ لصندوق الاستمرارية" subtitle="مالٌ للصندوق نفسه لا لطفلٍ بعينه: يدخل رصيده ويظهر في قيوده."
      :busy="form.submitting.value" :dirty="form.dirty.value"
      @close="donating = false" @submit="saveDonation"
    >
      <div class="grid sm:grid-cols-2 gap-4">
        <FormField
          v-model="form.values.amount" label="المبلغ بالدولار" type="number" min="1" step="0.01" required autofocus
          :error="form.errors.value.amount" @update:model-value="form.touch('amount')"
        />
        <FormField
          v-model="form.values.received_on" label="تاريخ الوصول" type="date" :max="today()" required
          :error="form.errors.value.received_on" @update:model-value="form.touch('received_on')"
        />
        <div class="sm:col-span-2">
          <FormField
            v-model="form.values.note" label="من أين جاء" hint="اسم المتبرّع أو مرجع الحوالة: به يُطابَق مع كشف البنك." required
            :error="form.errors.value.note" @update:model-value="form.touch('note')"
          />
        </div>
      </div>
    </FormModal>
  </div>
</template>

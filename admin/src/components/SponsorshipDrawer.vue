<script setup>
/**
 * تفاصيل الكفالة في درجٍ جانبي فوق القائمة.
 *
 * كان في كل صف زرّ «إلغاء» أحمر، على بُعد نقرة خاطئة من إنهاء كفالة طفل،
 * ولا مكان تُرى فيه الكفالة كاملة. هنا تفاصيلها ودفعاتها، والإجراءات
 * اليومية (إيقاف، استئناف، تعديل) في الأعلى، والإنهاء في قسمٍ منفصل أسفل.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { confirmDialog, confirmState } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, num, relative, EMPTY } from '@/utils/format'
import StatusBadge from './StatusBadge.vue'
import BaseIcon from './BaseIcon.vue'

const props = defineProps({
  id: { type: Number, default: null },
})
const emit = defineEmits(['close', 'changed', 'edit'])

const auth = useAuthStore()
const ui = useUiStore()

const s = ref(null)
const loading = ref(false)
const busy = ref(false)

async function load(id) {
  loading.value = true
  s.value = null
  try {
    const { data } = await admin.sponsorships.show(id)
    if (props.id === id) s.value = data.data
  } catch (e) {
    ui.error(e.userMessage)
    emit('close')
  } finally {
    loading.value = false
  }
}

watch(() => props.id, (id) => { if (id) load(id) }, { immediate: true })
defineExpose({ reload: () => props.id && load(props.id) })

const LIVE = ['active', 'paused', 'pending']
const CYCLE = { 1: 'شهرياً', 3: 'كل 3 أشهر', 12: 'سنوياً' }
const PAY_TONE = { succeeded: 'text-olive-700', failed: 'text-rose-700', refunded: 'text-sand-500', pending: 'text-amber-700' }
const canManage = computed(() => auth.can('sponsorships.manage') && LIVE.includes(s.value?.status))
const money_ = computed(() => s.value?.monthly_amount !== undefined)

async function setStatus(status, message) {
  busy.value = true
  try {
    await admin.sponsorships.update(s.value.id, { status })
    ui.success(message)
    emit('changed')
    await load(s.value.id)
  } catch (e) {
    ui.error(e.fieldErrors?.status?.[0] ?? e.userMessage)
  } finally {
    busy.value = false
  }
}

async function cancel() {
  const answer = await confirmDialog({
    title: 'إنهاء الكفالة',
    message: `يتوقف الخصم من الكافل نهائياً، ويعود ${s.value.orphan?.name ?? 'الطفل'} إلى قائمة من ينتظرون كافلاً. لا يُتراجع عنه.`,
    reason: 'required',
    reasonLabel: 'سبب الإنهاء',
    confirmLabel: 'أنهِ الكفالة',
    danger: true,
  })
  if (!answer) return

  busy.value = true
  try {
    await admin.sponsorships.cancel(s.value.id, answer.reason)
    ui.success('أُنهيت الكفالة.')
    emit('changed')
    await load(s.value.id)
  } catch (e) {
    ui.error(e.fieldErrors?.reason?.[0] ?? e.userMessage)
  } finally {
    busy.value = false
  }
}

/* ── التركيز ولوحة المفاتيح ─────────────────────────────────────────── */

const panel = ref(null)
let opener = null
function onKey(e) {
  if (confirmState.open) return
  if (e.key === 'Escape' && !busy.value) { e.preventDefault(); emit('close') }
}
watch(() => Boolean(props.id), async (open) => {
  if (open) {
    opener = document.activeElement
    document.addEventListener('keydown', onKey)
    await nextTick()
    panel.value?.focus()
  } else {
    document.removeEventListener('keydown', onKey)
    opener?.focus?.()
  }
}, { immediate: true })
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <div v-if="id" class="fixed inset-0 z-30 bg-sand-900/30" aria-hidden="true" @click="!busy && emit('close')" />
    <section
      v-if="id" ref="panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="shTitle"
      class="fixed inset-y-0 end-0 z-40 w-full sm:w-[34rem] bg-sand-0 border-s border-sand-200 flex flex-col outline-none"
    >
      <header class="flex items-start gap-3 p-5 border-b border-sand-200">
        <div class="flex-1 min-w-0">
          <p class="text-xs text-sand-500 num">{{ s?.reference_code ?? '…' }}</p>
          <h2 id="shTitle" class="text-lg font-semibold text-sand-900">
            كفالة {{ s?.orphan?.name ?? '' }}
          </h2>
          <div v-if="s" class="flex flex-wrap items-center gap-2 mt-1.5">
            <StatusBadge :label="s.status_label" :value="s.status" />
            <span v-if="s.is_zakat" class="badge bg-teal-600/10 text-teal-700">من الزكاة</span>
          </div>
        </div>
        <button class="btn btn-ghost !p-2" type="button" aria-label="إغلاق" @click="emit('close')">
          <BaseIcon name="close" :size="18" />
        </button>
      </header>

      <div v-if="loading && !s" class="p-5 space-y-3">
        <div v-for="n in 4" :key="n" class="h-12 rounded bg-sand-100 animate-pulse" />
      </div>

      <div v-else-if="s" class="flex-1 overflow-y-auto">
        <!-- الإجراءات اليومية -->
        <div v-if="canManage" class="flex flex-wrap gap-2 px-5 pt-4">
          <button
            v-if="s.status === 'active'" class="btn btn-outline !py-1.5 text-sm" type="button" :disabled="busy"
            @click="setStatus('paused', 'أُوقفت الكفالة مؤقتاً.')"
          ><BaseIcon name="clock" :size="14" /> إيقاف مؤقت</button>
          <button
            v-if="s.status === 'paused'" class="btn btn-primary !py-1.5 text-sm" type="button" :disabled="busy"
            @click="setStatus('active', 'استُؤنفت الكفالة.')"
          >استئناف</button>
          <button
            v-if="s.status !== 'pending'" class="btn btn-outline !py-1.5 text-sm" type="button" :disabled="busy"
            @click="emit('edit', s)"
          ><BaseIcon name="edit" :size="14" /> تعديل المبلغ أو يوم الخصم</button>
          <p v-if="s.status === 'pending'" class="text-xs text-sand-500">
            بانتظار أول دفعة. تتفعّل وحدها حين تُسجَّل.
            <RouterLink to="/payments" class="text-clay-700 hover:underline">سجّل دفعة</RouterLink>
          </p>
        </div>

        <!-- الطرفان -->
        <dl class="grid grid-cols-2 gap-x-5 gap-y-4 px-5 py-4 text-sm">
          <div>
            <dt class="text-xs text-sand-500 mb-0.5">الطفل</dt>
            <dd><RouterLink :to="`/orphans/${s.orphan?.id}`" class="text-clay-700 hover:underline">{{ s.orphan?.name }}</RouterLink>
              <span class="block text-xs text-sand-500"><span class="num">{{ s.orphan?.reference_code }}</span></span></dd>
          </div>
          <div v-if="s.sponsor">
            <dt class="text-xs text-sand-500 mb-0.5">الكافل</dt>
            <dd>
              <RouterLink v-if="auth.can('sponsors.view')" :to="`/sponsors/${s.sponsor.id}`" class="text-clay-700 hover:underline">{{ s.sponsor.name ?? s.sponsor.sponsor_code }}</RouterLink>
              <span v-else>{{ s.sponsor.sponsor_code }}</span>
              <span class="block text-xs text-sand-500"><span class="num">{{ s.sponsor.sponsor_code }}</span></span>
            </dd>
          </div>

          <template v-if="money_">
            <div>
              <dt class="text-xs text-sand-500 mb-0.5">المبلغ</dt>
              <dd><span class="num font-semibold">{{ money(s.charge_amount ?? s.monthly_amount, s.charge_currency ?? s.currency) }}</span> شهرياً
                <span v-if="s.charge_currency && s.charge_currency !== s.currency" class="block text-xs text-sand-500">بعملة الكافل، تعادل اليوم <span class="num">{{ money(s.monthly_amount, s.currency) }}</span></span>
                <span v-if="s.interval_months > 1" class="block text-xs text-sand-500">يدفع {{ CYCLE[s.interval_months] }} <span class="num">{{ money(s.charge_cycle_amount ?? s.cycle_amount, s.charge_currency ?? s.currency) }}</span></span>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-sand-500 mb-0.5">حصته من حاجة الطفل</dt>
              <dd class="num">{{ s.share_percent ?? 0 }}%</dd>
            </div>
            <div>
              <dt class="text-xs text-sand-500 mb-0.5">القسط القادم</dt>
              <dd>
                <template v-if="s.status === 'active'">{{ date(s.next_due_at) }} <span class="text-xs text-sand-500">· يوم <span class="num">{{ s.billing_day }}</span> من الشهر</span></template>
                <template v-else-if="s.status === 'paused'">لا خصم أثناء الإيقاف<span v-if="s.paused_at" class="block text-xs text-sand-500">موقوفة منذ {{ date(s.paused_at) }}</span></template>
                <template v-else-if="s.status === 'pending'">عند أول دفعة<span v-if="s.hold_expires_at" class="block text-xs text-amber-700">الحجز حتى {{ date(s.hold_expires_at) }}</span></template>
                <template v-else>{{ EMPTY }}</template>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-sand-500 mb-0.5">وسيلة الدفع</dt>
              <dd>{{ s.payment_method === 'bank_transfer' ? 'تحويل بنكي' : s.payment_method === 'card' ? 'بطاقة' : EMPTY }}</dd>
            </div>
          </template>

          <div>
            <dt class="text-xs text-sand-500 mb-0.5">بدأت</dt>
            <dd>{{ date(s.started_at) }}</dd>
          </div>
          <div v-if="s.ended_at">
            <dt class="text-xs text-sand-500 mb-0.5">انتهت</dt>
            <dd>{{ date(s.ended_at) }}</dd>
          </div>
          <div v-if="s.cancellation_reason" class="col-span-2">
            <dt class="text-xs text-sand-500 mb-0.5">سبب الإنهاء</dt>
            <dd class="text-sand-800">{{ s.cancellation_reason }}</dd>
          </div>
        </dl>

        <!-- الدفعات -->
        <section v-if="s.payments" class="border-t border-sand-100">
          <h3 class="text-sm font-semibold px-5 pt-4 pb-2">
            الدفعات <span class="text-sand-500 font-normal num">({{ num(s.payments.length) }})</span>
          </h3>
          <ol v-if="s.payments.length" class="px-5 pb-4 space-y-2">
            <li v-for="p in s.payments" :key="p.id" class="flex items-center gap-3 text-sm">
              <span class="size-2 rounded-full shrink-0" :class="{ 'bg-olive-600': p.status === 'succeeded', 'bg-rose-500': p.status === 'failed', 'bg-sand-400': p.status === 'refunded', 'bg-amber-400': p.status === 'pending' }" />
              <span class="w-28 text-sand-600" :title="relative(p.paid_at ?? p.created_at)">{{ date(p.paid_at ?? p.created_at) }}</span>
              <span class="num font-medium flex-1">{{ money(p.amount, p.currency) }}</span>
              <span class="hidden sm:inline text-xs text-sand-500">{{ p.card ?? p.method_label }}</span>
              <span class="text-xs font-medium w-20 text-end" :class="PAY_TONE[p.status] ?? 'text-sand-600'">{{ p.status_label }}</span>
            </li>
          </ol>
          <p v-else class="px-5 pb-4 text-sm text-sand-500">لا دفعات بعد.</p>
        </section>

        <!-- الإنهاء: بعيداً عن الإجراءات اليومية، وبسبب يبقى في السجل -->
        <section v-if="canManage" class="mx-5 my-5 rounded-xl border border-rose-200 p-4">
          <h3 class="text-sm font-semibold text-rose-800">إنهاء الكفالة</h3>
          <p class="text-xs text-sand-600 mt-1 mb-3">نهائي: يتوقف الخصم ويعود الطفل لقائمة الانتظار. للاستراحة استعمل «إيقاف مؤقت».</p>
          <button class="btn btn-outline !py-1.5 text-sm text-rose-700 border-rose-200 hover:!border-rose-400" type="button" :disabled="busy" @click="cancel">
            أنهِ الكفالة
          </button>
        </section>
      </div>
    </section>
  </Teleport>
</template>

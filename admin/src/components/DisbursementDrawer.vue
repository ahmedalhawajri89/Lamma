<script setup>
/**
 * تفاصيل الصرف واعتماده.
 *
 * كان زرّ «اعتماد» في الصف نفسه: يعتمد المحاسب رقماً يدخل صفحة الشفافية
 * المنشورة دون أن يرى إيصاله أو بيانه. هنا يرى الصرف كاملاً، ويفتح الإيصال
 * حين يطلبه، ثم يعتمد فينتقل الدرج وحده إلى ما بعده في الطابور.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { confirmState } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, dateTime, relative, EMPTY } from '@/utils/format'
import BaseIcon from './BaseIcon.vue'

const props = defineProps({
  item: { type: Object, default: null },
  /** ترتيب القائمة، للانتقال إلى التالي بعد الاعتماد */
  order: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'open', 'approved'])

const auth = useAuthStore()
const ui = useUiStore()

const d = computed(() => props.item)
const busy = ref(false)

/**
 * يعتمده غير من سجّله وغير من عدّله: مبدأ العينين، والحارس الوحيد بين خطأ
 * في الإدخال ورقمٍ منشور. (`DisbursementPolicy` تفرضه على الخادم.)
 */
const canApprove = computed(() => d.value && !d.value.is_approved && auth.can('disbursements.approve')
  && d.value.recorded_by !== auth.user?.id && d.value.updated_by !== auth.user?.id)

const nextPending = computed(() => {
  const i = props.order.findIndex((x) => x.id === d.value?.id)
  const rest = [...props.order.slice(i + 1), ...props.order.slice(0, Math.max(0, i))]
  return rest.find((x) => !x.is_approved && x.id !== d.value?.id)?.id ?? null
})

async function approve() {
  if (!canApprove.value || busy.value) return
  busy.value = true
  const next = nextPending.value
  try {
    await admin.disbursements.approve(d.value.id)
    ui.success('اعتُمد الصرف، وصار جزءاً من أرقام الشفافية المنشورة.')
    emit('approved', { id: d.value.id, next })
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    busy.value = false
  }
}

/* ── الإيصال: يُعرض بطلب، بجلسة المستخدم ─────────────────────────────── */

const receiptUrl = ref(null)
const receiptType = ref('')
const receiptLoading = ref(false)

function dropReceipt() {
  if (receiptUrl.value) URL.revokeObjectURL(receiptUrl.value)
  receiptUrl.value = null
}

async function showReceipt() {
  receiptLoading.value = true
  try {
    const { data } = await admin.disbursements.receipt(d.value.id)
    dropReceipt()
    receiptType.value = data.type
    receiptUrl.value = URL.createObjectURL(data)
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر فتح الإيصال.')
  } finally {
    receiptLoading.value = false
  }
}

watch(() => d.value?.id, dropReceipt)
onBeforeUnmount(dropReceipt)

/* ── لوحة المفاتيح والتركيز ──────────────────────────────────────────── */

const panel = ref(null)
let opener = null
function onKey(e) {
  if (confirmState.open) return
  if (e.key === 'Escape' && !busy.value) { e.preventDefault(); emit('close') }
}
watch(() => Boolean(props.item), async (open) => {
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
    <div v-if="d" class="fixed inset-0 z-30 bg-sand-900/30" aria-hidden="true" @click="!busy && emit('close')" />
    <section
      v-if="d" ref="panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="disTitle"
      class="fixed inset-y-0 end-0 z-40 w-full sm:w-[34rem] bg-sand-0 border-s border-sand-200 flex flex-col outline-none"
    >
      <header class="flex items-start gap-3 p-5 border-b border-sand-200">
        <span class="size-10 rounded-lg shrink-0" :style="{ background: d.category?.color }" aria-hidden="true" />
        <div class="flex-1 min-w-0">
          <p class="text-xs text-sand-500">{{ d.category?.name }}<template v-if="d.category?.is_overhead"> · تشغيل</template></p>
          <h2 id="disTitle" class="text-xl font-bold text-sand-900 num">{{ money(d.amount, d.currency) }}</h2>
          <p v-if="d.currency && d.currency !== 'USD'" class="text-xs text-sand-500"><span class="num">≈ {{ money(d.amount_base) }}</span> بسعر <span class="num">{{ d.fx_rate }}</span></p>
        </div>
        <button class="btn btn-ghost !p-2" type="button" aria-label="إغلاق" @click="emit('close')">
          <BaseIcon name="close" :size="18" />
        </button>
      </header>

      <div class="flex-1 overflow-y-auto">
        <!-- حالة الاعتماد وما يترتّب عليها -->
        <div class="px-5 pt-4">
          <p v-if="d.is_approved" class="rounded-xl bg-olive-50 text-olive-800 text-sm px-4 py-3">
            <BaseIcon name="check" :size="14" class="inline" />
            معتمَد{{ d.approver ? ` من ${d.approver}` : '' }} {{ relative(d.approved_at) }}. يدخل أرقام الشفافية المنشورة.
          </p>
          <p v-else class="rounded-xl bg-amber-50 text-amber-900 text-sm px-4 py-3">
            بانتظار الاعتماد، سُجّل {{ relative(d.created_at) }}. لا يدخل أرقام الشفافية حتى يُعتمد.
          </p>
        </div>

        <dl class="grid grid-cols-2 gap-x-5 gap-y-4 px-5 py-4 text-sm">
          <div>
            <dt class="text-xs text-sand-500 mb-0.5">لمن</dt>
            <dd>
              <RouterLink v-if="d.orphan" :to="`/orphans/${d.orphan.id}`" class="text-clay-700 hover:underline">{{ d.orphan.name }}</RouterLink>
              <span v-else>مصروف عام</span>
              <span v-if="d.orphan" class="block text-xs text-sand-500"><span class="num">{{ d.orphan.reference_code }}</span></span>
            </dd>
          </div>
          <div><dt class="text-xs text-sand-500 mb-0.5">تاريخ الصرف</dt><dd>{{ date(d.spent_on) }}</dd></div>
          <div class="col-span-2"><dt class="text-xs text-sand-500 mb-0.5">البيان</dt><dd class="text-sand-800 whitespace-pre-line">{{ d.description || EMPTY }}</dd></div>
          <div><dt class="text-xs text-sand-500 mb-0.5">سجّله</dt><dd>{{ d.recorder ?? EMPTY }}<span class="block text-xs text-sand-500">{{ dateTime(d.created_at) }}</span></dd></div>
          <div v-if="d.is_approved"><dt class="text-xs text-sand-500 mb-0.5">اعتمده</dt><dd>{{ d.approver ?? EMPTY }}<span class="block text-xs text-sand-500">{{ dateTime(d.approved_at) }}</span></dd></div>
        </dl>

        <!-- الإيصال: يُفتح بطلب -->
        <section class="border-t border-sand-100 px-5 py-4">
          <h3 class="text-sm font-semibold mb-2">الإيصال</h3>
          <template v-if="d.has_receipt">
            <button v-if="!receiptUrl" class="btn btn-outline" type="button" :disabled="receiptLoading" @click="showReceipt">
              <BaseIcon :name="receiptLoading ? 'loader' : 'eye'" :size="15" :class="{ 'animate-spin': receiptLoading }" /> عرض الإيصال
            </button>
            <div v-else class="rounded-xl overflow-hidden border border-sand-200 bg-sand-100">
              <img v-if="receiptType.startsWith('image/')" :src="receiptUrl" alt="الإيصال" class="w-full max-h-[28rem] object-contain bg-white" />
              <iframe v-else :src="`${receiptUrl}#navpanes=0&view=FitH`" title="الإيصال" class="w-full h-[28rem] bg-white" />
            </div>
          </template>
          <p v-else class="text-sm text-amber-800">لا إيصال مرفق. {{ d.is_approved ? '' : 'من يعتمده يعتمد ما لم يرَ دليله.' }}</p>
        </section>
      </div>

      <footer v-if="!d.is_approved" class="border-t border-sand-200 px-5 py-3 flex flex-wrap items-center gap-2">
        <button v-if="canApprove" class="btn btn-primary" type="button" :disabled="busy" @click="approve">
          <BaseIcon :name="busy ? 'loader' : 'check'" :size="15" :class="{ 'animate-spin': busy }" /> اعتمد الصرف
        </button>
        <p v-else-if="d.recorded_by === auth.user?.id || d.updated_by === auth.user?.id" class="text-sm text-sand-500">
          {{ d.recorded_by === auth.user?.id ? 'سجّلته أنت، فيعتمده غيرك.' : 'عدّلته أنت، فيعتمده غيرك.' }}
        </p>
        <span v-if="nextPending" class="ms-auto">
          <button class="btn btn-ghost" type="button" :disabled="busy" @click="emit('open', nextPending)">
            التالي بانتظار الاعتماد <BaseIcon name="chevron" :size="15" style="transform: scaleX(-1)" />
          </button>
        </span>
      </footer>
    </section>
  </Teleport>
</template>

<script setup>
/**
 * تعديل كفالة قائمة.
 *
 * ثلاثة أشياء يطلبها الكفلاء فعلاً ولم تكن ممكنة من اللوحة: إيقافٌ مؤقت حين
 * تضيق الحال، ورفعُ المبلغ حين تتحسّن، وتغييرُ يوم الخصم ليوافق راتباً.
 *
 * وكان البديل الوحيد هو الإلغاء — فيفقد الطفلُ كافله لأن الكافل أراد أن
 * يؤجّل شهرين.
 */
import { computed, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import { money } from '@/utils/format'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  sponsorship: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const BLANK = { status: '', monthly_amount: null, billing_day: null }

// ما تغيّر وحده: كانت الحالة تُرسل مع كل حفظ، فتتفعّل كفالةٌ بانتظار
// الدفع لمجرد تعديل يوم الخصم
const form = useForm(BLANK, () =>
  admin.sponsorships.update(props.sponsorship.id, form.changed()),
)

/**
 * الحالتان المسموحتان وحدهما.
 *
 * `pending` تعني «بانتظار أول دفعة» ويضبطها الخادم؛ و`cancelled` لها فعلٌ
 * خاص (`CancelSponsorship`) يُحرّر الطفل ويسجّل السبب. عرضهما هنا يَعِد
 * بانتقالٍ يرفضه الخادم.
 */
const STATUSES = [
  { value: 'active', label: 'نشطة' },
  { value: 'paused', label: 'موقوفة مؤقتاً، لا تُخصم' },
]

const wasPaused = computed(() => props.sponsorship?.status === 'paused')
const isPending = computed(() => props.sponsorship?.status === 'pending')

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen || !props.sponsorship) return

    const s = props.sponsorship
    form.setValues({
      status: s.status,
      monthly_amount: s.monthly_amount ?? null,
      billing_day: s.billing_day ?? null,
    })
  },
  { immediate: true },
)

async function save() {
  const saved = await form.submit()
  if (saved) emit('saved', saved)
}
</script>

<template>
  <FormModal
    v-if="sponsorship"
    :open="open" title="تعديل الكفالة"
    :subtitle="`${sponsorship.reference_code} · ${sponsorship.orphan?.name ?? ''}`"
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <p v-if="isPending" class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
      بانتظار أول دفعة. تتفعّل الكفالة تلقائياً حين تصل، ولا تُفعَّل من هنا.
    </p>

    <FormField
      v-else
      v-model="form.values.status" label="الحالة" required autofocus
      :options="STATUSES"
      :hint="wasPaused
        ? 'إعادتها نشطةً تستأنف الخصم من القسط القادم.'
        : 'الإيقاف المؤقت لا يُنهي الكفالة ولا يُحرّر الطفل لكافل آخر.'"
      :error="form.errors.value.status" @update:model-value="form.touch('status')"
    />

    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.monthly_amount" label="المبلغ الشهري ($)" type="number"
        :min="5" :step="0.01"
        :hint="`الحالي ${money(sponsorship.monthly_amount)}`"
        :error="form.errors.value.monthly_amount"
        @update:model-value="form.touch('monthly_amount')"
      />
      <FormField
        v-model="form.values.billing_day" label="يوم الخصم من الشهر" type="number"
        :min="1" :max="28" :step="1"
        hint="حتى 28، فبعض الشهور لا يوجد فيها ما بعده."
        :error="form.errors.value.billing_day"
        @update:model-value="form.touch('billing_day')"
      />
    </div>
  </FormModal>
</template>

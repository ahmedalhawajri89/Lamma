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

const form = useForm(BLANK, (values) =>
  admin.sponsorships.update(props.sponsorship.id, values),
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
  { value: 'paused', label: 'موقوفة مؤقتاً — لا تُخصم' },
]

const wasPaused = computed(() => props.sponsorship?.status === 'paused')

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen || !props.sponsorship) return

    const s = props.sponsorship
    form.setValues({
      // «بانتظار أول دفعة» تُعرض نشطةً: هي الحالة التي ستؤول إليها
      status: s.status === 'paused' ? 'paused' : 'active',
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
    <FormField
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
        :min="5" :step="1"
        :hint="`الحالي ${money(sponsorship.monthly_amount)}`"
        :error="form.errors.value.monthly_amount"
        @update:model-value="form.touch('monthly_amount')"
      />
      <FormField
        v-model="form.values.billing_day" label="يوم الخصم من الشهر" type="number"
        :min="1" :max="28" :step="1"
        hint="حتى ٢٨ — فبعض الشهور لا يوجد فيها ما بعده."
        :error="form.errors.value.billing_day"
        @update:model-value="form.touch('billing_day')"
      />
    </div>
  </FormModal>
</template>

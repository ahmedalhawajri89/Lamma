<script setup>
/**
 * تسجيل دفعة يدوية.
 *
 * لا كل كافل يدفع ببطاقة: تصل حوالات بنكية ونقدٌ باليد، وبلا هذه النافذة
 * تبقى تلك الدفعات خارج النظام — فيبدو الكافل متأخّراً وهو قد دفع، وتُغلق
 * كفالته آلياً بعد مهلة السماح.
 *
 * ورقم الحوالة ليس حقلاً وصفياً: منه يُشتقّ `idempotency_key`، فتسجيل
 * الحوالة نفسها مرتين يُعيد الدفعة الأولى ولا يخصم ثانيةً. ولهذا هو مطلوب.
 */
import { computed, ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import { money } from '@/utils/format'
import BaseIcon from './BaseIcon.vue'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },

  /** الكفالات الحيّة — `[{ id, sponsor, orphan, monthly_amount }]`. */
  sponsorships: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const BLANK = {
  sponsorship_id: '',
  amount: null,
  method: 'bank_transfer',
  reference: '',
}

const form = useForm(BLANK, (values) => admin.payments.record(values))

const sponsorshipOptions = computed(() =>
  props.sponsorships.map((s) => ({
    value: s.id,
    label: `${s.sponsor?.name ?? s.sponsor?.sponsor_code ?? '—'} ← ${s.orphan?.first_name ?? s.orphan?.reference_code ?? '—'}`,
  })),
)

const chosen = computed(() =>
  props.sponsorships.find((s) => s.id === Number(form.values.sponsorship_id)),
)

const methods = [
  { value: 'bank_transfer', label: 'تحويل بنكي' },
  { value: 'cash', label: 'نقد' },
]

const searchRef = ref(null)

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) form.setValues(BLANK)
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
    :open="open" title="تسجيل دفعة يدوية"
    subtitle="للتحويل البنكي والنقد — دفعات البطاقات تصل من البوابة وحدها."
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <FormField
      ref="searchRef"
      v-model="form.values.sponsorship_id" label="الكفالة" required autofocus
      :options="sponsorshipOptions" placeholder="اختر الكفالة"
      :error="form.errors.value.sponsorship_id" @update:model-value="form.touch('sponsorship_id')"
    />

    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.method" label="الوسيلة" required
        :options="methods"
        :error="form.errors.value.method" @update:model-value="form.touch('method')"
      />
      <FormField
        v-model="form.values.amount" label="المبلغ" type="number"
        :min="0.01" :step="0.01"
        :hint="chosen ? `اتركه فارغاً ليُسجَّل مبلغ الكفالة ${money(chosen.monthly_amount)}` : 'اتركه فارغاً ليُسجَّل مبلغ الكفالة.'"
        :error="form.errors.value.amount" @update:model-value="form.touch('amount')"
      />
    </div>

    <FormField
      v-model="form.values.reference" label="رقم الحوالة أو الإيصال" required
      placeholder="TRX-88213"
      hint="منه يُشتقّ مفتاح التفرّد: تسجيل الحوالة نفسها مرتين لا يخصم مرتين."
      :error="form.errors.value.reference" @update:model-value="form.touch('reference')"
    />

    <template #actions>
      <button class="btn btn-outline" type="button" :disabled="form.submitting.value" @click="emit('close')">
        إلغاء
      </button>
      <button class="btn btn-primary" type="submit" :disabled="form.submitting.value">
        <BaseIcon v-if="form.submitting.value" name="loader" :size="15" class="animate-spin" />
        {{ form.submitting.value ? 'جارٍ التسجيل…' : 'سجّل الدفعة' }}
      </button>
    </template>
  </FormModal>
</template>

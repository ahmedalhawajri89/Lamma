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
import { ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import { money } from '@/utils/format'
import BaseIcon from './BaseIcon.vue'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'
import SearchSelect from './SearchSelect.vue'
import { searchLiveSponsorships } from '@/composables/lookups'

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
  paid_at: '',
}

const form = useForm(BLANK, (values) => admin.payments.record(values))


// الكفالة المختارة كما وصلت من البحث: منها مبلغ القسط في التلميح
const chosen = ref(null)

const methods = [
  { value: 'bank_transfer', label: 'تحويل بنكي' },
  { value: 'cash', label: 'نقد' },
]


// اليوم بالتاريخ المحلي لا UTC: بعد منتصف الليل محلياً كان يظهر أمس
const now = new Date()
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      form.setValues(BLANK)
      chosen.value = null
    }
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
    subtitle="للتحويل البنكي والنقد، أما دفعات البطاقات فتصل من البوابة وحدها."
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <SearchSelect
      v-model="form.values.sponsorship_id" label="الكفالة" required autofocus
      :search="searchLiveSponsorships" placeholder="مرجع الكفالة، أو اسم الطفل أو الكافل…"
      hint="الحيّة كلها، ومنها ما ينتظر أول دفعة."
      :error="form.errors.value.sponsorship_id"
      @update:model-value="form.touch('sponsorship_id')" @picked="chosen = $event.sponsorship"
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

    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.reference" label="رقم الحوالة أو الإيصال" required
        placeholder="TRX-88213"
        hint="يُحفظ للمطابقة مع كشف البنك، ويمنع تسجيل الحوالة نفسها مرتين."
        :error="form.errors.value.reference" @update:model-value="form.touch('reference')"
      />
      <FormField
        v-model="form.values.paid_at" label="تاريخ وصول المال" type="date" :max="today"
        hint="اتركه فارغاً إن وصل اليوم."
        :error="form.errors.value.paid_at" @update:model-value="form.touch('paid_at')"
      />
    </div>

    <template #actions="{ close }">
      <button class="btn btn-outline" type="button" :disabled="form.submitting.value" @click="close">
        إلغاء
      </button>
      <button class="btn btn-primary" type="submit" :disabled="form.submitting.value">
        <BaseIcon v-if="form.submitting.value" name="loader" :size="15" class="animate-spin" />
        {{ form.submitting.value ? 'جارٍ التسجيل…' : 'سجّل الدفعة' }}
      </button>
    </template>
  </FormModal>
</template>

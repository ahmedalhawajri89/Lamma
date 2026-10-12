<script setup>
/**
 * تسجيل صرف.
 *
 * كل رقم على صفحة الشفافية العامة يبدأ من هنا: النسب الخمس، و«٩٣٪ تصل
 * الطفل»، وحلقة التوزيع كلها مجاميعُ صفوفٍ تُكتب في هذه النافذة. وكانت
 * الشاشة تعرض الصرف ولا تسجّله — فالأرقام المنشورة لا سبيل لتحديثها من
 * اللوحة أصلاً.
 *
 * والصرف يُسجَّل غير معتمَد، ويعتمده شخص آخر: `DisbursementPolicy` تمنع
 * المسجّل من الاعتماد. لا نُظهر ذلك تحذيراً بل نكتبه في العنوان الفرعي،
 * فيعرف المحاسب أن عمله لم ينتهِ بالحفظ.
 */
import { computed, ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import BaseIcon from './BaseIcon.vue'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },

  /** بنود الصرف — `[{ id, name, is_overhead }]`. */
  categories: { type: Array, default: () => [] },

  /** الأيتام للربط الاختياري — `[{ id, reference_code, first_name }]`. */
  orphans: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const receipt = ref(null)
const receiptInput = ref(null)

const today = new Date().toISOString().slice(0, 10)

const BLANK = {
  orphan_id: '',
  category_id: '',
  amount: null,
  currency: 'USD',
  fx_rate: null,
  spent_on: today,
  description: '',
}

const form = useForm(BLANK, (values) => admin.disbursements.create(values, receipt.value))

const categoryOptions = computed(() =>
  props.categories.map((c) => ({
    value: c.id,
    label: c.is_overhead ? `${c.name} (تشغيلي)` : c.name,
  })),
)

const orphanOptions = computed(() =>
  props.orphans.map((o) => ({
    value: o.id,
    label: `${o.reference_code} · ${o.first_name}`,
  })),
)

const selected = computed(() =>
  props.categories.find((c) => c.id === Number(form.values.category_id)),
)

/**
 * سعر الصرف يظهر للعملات غير الدولار وحدها.
 *
 * الدولار هو عملة الأساس، وحقلٌ يطلب سعر صرف الدولار إلى الدولار سؤالٌ بلا
 * معنى — ويُغري بكتابة رقم خاطئ فيه.
 */
const needsRate = computed(() => (form.values.currency || '').toUpperCase() !== 'USD')

function onReceipt(event) {
  receipt.value = event.target.files?.[0] ?? null
  form.touch('receipt')
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    form.setValues({ ...BLANK, spent_on: new Date().toISOString().slice(0, 10) })
    receipt.value = null
    if (receiptInput.value) receiptInput.value.value = ''
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
    :open="open" wide title="تسجيل صرف"
    subtitle="يُسجَّل غير معتمَد، والاعتماد لشخص آخر: من سجّل لا يعتمد."
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.category_id" label="بند الصرف" required autofocus
        :options="categoryOptions" placeholder="اختر البند"
        :error="form.errors.value.category_id" @update:model-value="form.touch('category_id')"
      />
      <FormField
        v-model="form.values.spent_on" label="تاريخ الصرف" type="date" required
        :max="today"
        hint="المحاسبة تسجّل ما وقع لا ما سيقع."
        :error="form.errors.value.spent_on" @update:model-value="form.touch('spent_on')"
      />

      <FormField
        v-model="form.values.amount" label="المبلغ" type="number" required
        :min="0.01" :step="0.01"
        :error="form.errors.value.amount" @update:model-value="form.touch('amount')"
      />
      <FormField
        v-model="form.values.currency" label="العملة" required
        :options="['USD', 'ILS', 'JOD', 'EGP', 'TRY', 'EUR']"
        :error="form.errors.value.currency" @update:model-value="form.touch('currency')"
      />
    </div>

    <FormField
      v-if="needsRate"
      v-model="form.values.fx_rate" label="سعر الصرف إلى الدولار" type="number"
      :min="0.000001" :step="0.0001"
      hint="اتركه فارغاً ليستعمل الخادم سعر اليوم المسجَّل."
      :error="form.errors.value.fx_rate" @update:model-value="form.touch('fx_rate')"
    />

    <!--
      الطفل اختياري: الصرف الإداري العام لا يخصّ طفلاً بعينه، وإجباره على
      واحد يفسد نسبة «ما وصل الطفل» في تقرير الشفافية.
    -->
    <FormField
      v-model="form.values.orphan_id" label="الطفل"
      :options="orphanOptions"
      :placeholder="selected?.is_overhead ? 'بند تشغيلي لا يخصّ طفلاً' : 'اختياري، اتركه فارغاً للمصروف العام'"
      :disabled="selected?.is_overhead"
      :error="form.errors.value.orphan_id" @update:model-value="form.touch('orphan_id')"
    />

    <FormField
      v-model="form.values.description" label="البيان"
      placeholder="رسوم فصل دراسي في مدرسة النهضة"
      :error="form.errors.value.description" @update:model-value="form.touch('description')"
    />

    <div>
      <label class="block text-sm font-medium mb-1.5" for="receiptFile">الإيصال</label>
      <input
        id="receiptFile" ref="receiptInput" class="input" type="file"
        accept="application/pdf,image/jpeg,image/png"
        :aria-invalid="form.errors.value.receipt ? 'true' : undefined"
        aria-describedby="receiptHelp" @change="onReceipt"
      >
      <p id="receiptHelp" class="text-xs text-sand-500 mt-1">
        PDF أو صورة. صرفٌ بلا إيصال يُسجَّل، لكن التدقيق لا يستطيع تأكيده.
      </p>
      <p v-if="form.errors.value.receipt" class="text-xs text-rose-700 mt-1" role="alert">
        {{ form.errors.value.receipt[0] }}
      </p>
    </div>

    <template #actions>
      <button class="btn btn-outline" type="button" :disabled="form.submitting.value" @click="emit('close')">
        إلغاء
      </button>
      <button class="btn btn-primary" type="submit" :disabled="form.submitting.value">
        <BaseIcon v-if="form.submitting.value" name="loader" :size="15" class="animate-spin" />
        {{ form.submitting.value ? 'جارٍ التسجيل…' : 'سجّل الصرف' }}
      </button>
    </template>
  </FormModal>
</template>

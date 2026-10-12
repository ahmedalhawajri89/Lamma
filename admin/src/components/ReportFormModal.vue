<script setup>
/**
 * كتابة تقرير ربعي.
 *
 * هذا ما يصل الكافلَ فعلاً — لا الأرقام على لوحة القيادة. وكانت الشاشة
 * تعرض التقارير وتعتمدها وتنشرها، ولا تكتب واحداً: دورة حياة بلا بداية.
 *
 * والتقرير يُكتب مسوّدةً ثم يُرفع ثم يُعتمد ثم يُنشر — أربع خطوات بثلاثة
 * أشخاص. لا نختصرها هنا: من يكتب عن طفلٍ زاره ليس من يقرّر أن ما كتبه
 * صالحٌ لأن يقرأه كافلٌ في بلد آخر.
 */
import { computed, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import BaseIcon from './BaseIcon.vue'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  orphans: { type: Array, default: () => [] },

  /** تقرير قائم يُعدَّل (مسوّدة، أو مُرجَعة بملاحظة)، و`null` لتقرير جديد */
  report: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const today = new Date().toISOString().slice(0, 10)
const now = new Date()

/**
 * الربع الافتراضي هو المنقضي لا الجاري.
 *
 * التقرير الربعي يُكتب عن فترة انتهت. واقتراح الربع الجاري يدفع المشرف إلى
 * كتابة تقرير عن شهرٍ لم يمضِ بعد — أو إلى تغيير الرقم في كل مرة.
 */
function previousQuarter() {
  const quarter = Math.floor(now.getMonth() / 3) + 1
  return quarter === 1
    ? { year: now.getFullYear() - 1, quarter: 4 }
    : { year: now.getFullYear(), quarter: quarter - 1 }
}

const previous = previousQuarter()

const BLANK = {
  orphan_id: '',
  period_year: previous.year,
  period_quarter: previous.quarter,
  title: '',
  summary: '',
  academic_note: '',
  health_note: '',
  psychosocial_note: '',
  visit_date: '',
}

const form = useForm(BLANK, (values) => (props.report
  ? admin.reports.update(props.report.id, values)
  : admin.reports.create(values)))

const editing = computed(() => props.report !== null)

const orphanOptions = computed(() =>
  props.orphans.map((o) => ({ value: o.id, label: `${o.reference_code} · ${o.first_name}` })),
)

const quarters = [
  { value: 1, label: 'الربع الأول (من يناير إلى مارس)' },
  { value: 2, label: 'الربع الثاني (من أبريل إلى يونيو)' },
  { value: 3, label: 'الربع الثالث (من يوليو إلى سبتمبر)' },
  { value: 4, label: 'الربع الرابع (من أكتوبر إلى ديسمبر)' },
]

const years = Array.from({ length: 4 }, (_, i) => now.getFullYear() - i)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return

    form.setValues(props.report
      ? Object.fromEntries(Object.keys(BLANK).map((k) => [
        k, k === 'orphan_id' ? props.report.orphan?.id : (props.report[k] ?? ''),
      ]))
      : BLANK)
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
    :open="open" wide :title="editing ? 'تعديل التقرير' : 'تقرير ربعي جديد'"
    :subtitle="editing ? `${report.orphan?.name ?? ''} · ${report.period_label ?? ''}` : 'يُحفظ مسوّدةً ثم يُرفع للاعتماد، ولا يصل الكافل قبل النشر.'"
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <!-- ما طلبه المراجع، أمام الكاتب وهو يعدّل -->
    <div v-if="report?.review_note" class="border-s-4 border-amber-500 bg-amber-50 rounded-e-lg px-4 py-3" role="note">
      <p class="text-xs font-semibold text-amber-800">ملاحظة المراجع</p>
      <p class="text-sm text-sand-800 mt-1 leading-relaxed whitespace-pre-line">{{ report.review_note }}</p>
    </div>

    <FormField
      v-if="!editing"
      v-model="form.values.orphan_id" label="الطفل" required autofocus
      :options="orphanOptions" placeholder="اختر الطفل"
      :error="form.errors.value.orphan_id" @update:model-value="form.touch('orphan_id')"
    />

    <div v-if="!editing" class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.period_year" label="السنة" type="number" required
        :options="years"
        :error="form.errors.value.period_year" @update:model-value="form.touch('period_year')"
      />
      <FormField
        v-model="form.values.period_quarter" label="الربع" required
        :options="quarters"
        :error="form.errors.value.period_quarter" @update:model-value="form.touch('period_quarter')"
      />
    </div>

    <FormField
      v-model="form.values.title" label="العنوان" required
      placeholder="تقرير الربع الثاني: تحسّن ملحوظ في الرياضيات"
      :error="form.errors.value.title" @update:model-value="form.touch('title')"
    />

    <FormField
      v-model="form.values.visit_date" label="تاريخ الزيارة التي بُني عليها" type="date"
      :max="today"
      hint="تقريرٌ بلا زيارة تسنده يبقى ممكناً، لكن الكافل يقرأ التاريخ."
      :error="form.errors.value.visit_date" @update:model-value="form.touch('visit_date')"
    />

    <FormField
      v-model="form.values.summary" label="الخلاصة" type="textarea" :rows="4"
      hint="ما الذي تغيّر هذا الربع. بلغة تحفظ كرامته: ما أنجزه، لا ما يُبكي القارئ."
      :error="form.errors.value.summary" @update:model-value="form.touch('summary')"
    />

    <div class="grid sm:grid-cols-3 gap-4">
      <FormField
        v-model="form.values.academic_note" label="الدراسة" type="textarea" :rows="3"
        :error="form.errors.value.academic_note" @update:model-value="form.touch('academic_note')"
      />
      <FormField
        v-model="form.values.health_note" label="الصحة" type="textarea" :rows="3"
        :error="form.errors.value.health_note" @update:model-value="form.touch('health_note')"
      />
      <FormField
        v-model="form.values.psychosocial_note" label="الحالة النفسية" type="textarea" :rows="3"
        :error="form.errors.value.psychosocial_note"
        @update:model-value="form.touch('psychosocial_note')"
      />
    </div>

    <template #actions>
      <button class="btn btn-outline" type="button" :disabled="form.submitting.value" @click="emit('close')">
        إلغاء
      </button>
      <button class="btn btn-primary" type="submit" :disabled="form.submitting.value">
        <BaseIcon v-if="form.submitting.value" name="loader" :size="15" class="animate-spin" />
        {{ form.submitting.value ? 'جارٍ الحفظ…' : (editing ? 'احفظ التعديلات' : 'احفظ مسوّدة') }}
      </button>
    </template>
  </FormModal>
</template>

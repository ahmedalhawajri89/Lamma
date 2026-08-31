<script setup>
/**
 * تسجيل زيارة ميدانية.
 *
 * الزيارة أصلُ كل ما نقوله عن الطفل: التقرير الربعي يُبنى عليها، وإعادة
 * التحقق السنوية تعتمدها. وكانت الشاشة تعرض الزيارات وتُنبّه إلى المتأخر
 * منها، ولا تسجّل واحدة.
 */
import { computed, ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import BaseIcon from './BaseIcon.vue'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  orphans: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

/** أعلام الاحتياج الشائعة — نصٌّ حرّ يجعل التجميع لاحقاً مستحيلاً. */
const NEEDS = [
  'تعثّر دراسي',
  'حاجة صحية',
  'دعم نفسي',
  'ملابس شتوية',
  'مواصلات',
  'ترميم سكن',
]

const flags = ref([])

/**
 * `datetime-local` يحتاج `YYYY-MM-DDTHH:mm` بالتوقيت المحلي لا بـUTC.
 *
 * ودقيقة إلى الوراء: الحقل بدقّة الدقيقة، فقيمة «الآن» تُقرَّب إلى الأعلى
 * أحياناً فتسبق ساعة الخادم بثوانٍ — ويُردّ الطلب لسببٍ لا يفهمه أحد.
 */
function localNow(offsetMinutes = 1) {
  const d = new Date()
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset() - offsetMinutes)
  return d.toISOString().slice(0, 16)
}

const BLANK = {
  orphan_id: '',
  visited_at: '',
  findings: '',
  next_visit_due: '',
}

const form = useForm(BLANK, ({ visited_at: visitedAt, ...rest }) =>
  admin.visits.create({
    ...rest,

    /**
     * الوقت يُرسَل لحظةً مطلقة لا نصّاً محلياً.
     *
     * `datetime-local` يُخرج «2026-08-31T11:20» بلا منطقة زمنية، والخادم
     * يقرؤه بتوقيته (‏UTC هنا). فمشرفٌ في غزّة (‏UTC+3) يفتح النافذة فيجد
     * الحقل معبّأً بالوقت الآن، ويضغط «سجّل» فيُردّ بـ«لا تُسجَّل زيارة
     * بتاريخ مستقبلي» — عن وقتٍ مضى فعلاً. عيبٌ يظهر لكل من هو شرق
     * غرينتش، ولا يظهر أبداً لمن يطوّر عليه.
     */
    visited_at: visitedAt ? new Date(visitedAt).toISOString() : null,
    needs_flagged: flags.value,
  }),
)

const orphanOptions = computed(() =>
  props.orphans.map((o) => ({ value: o.id, label: `${o.reference_code} — ${o.first_name}` })),
)

const tomorrow = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  return d.toISOString().slice(0, 10)
})

function toggle(need) {
  flags.value = flags.value.includes(need)
    ? flags.value.filter((n) => n !== need)
    : [...flags.value, need]
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    form.setValues({ ...BLANK, visited_at: localNow() })
    flags.value = []
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
    :open="open" wide title="تسجيل زيارة ميدانية"
    subtitle="عليها يُبنى التقرير الربعي وإعادة التحقق السنوية."
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <FormField
      v-model="form.values.orphan_id" label="الطفل" required autofocus
      :options="orphanOptions" placeholder="اختر الطفل"
      :error="form.errors.value.orphan_id" @update:model-value="form.touch('orphan_id')"
    />

    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.visited_at" label="وقت الزيارة" type="datetime-local" required
        :max="localNow()"
        hint="لا تُسجَّل زيارة بتاريخ مستقبلي."
        :error="form.errors.value.visited_at" @update:model-value="form.touch('visited_at')"
      />
      <FormField
        v-model="form.values.next_visit_due" label="موعد الزيارة القادمة" type="date"
        :min="tomorrow"
        hint="منه تُحسب الزيارات المتأخرة في هذه الشاشة."
        :error="form.errors.value.next_visit_due" @update:model-value="form.touch('next_visit_due')"
      />
    </div>

    <FormField
      v-model="form.values.findings" label="الملاحظات" type="textarea" :rows="6" required
      hint="ما رأيتَه وما سمعتَه. سطرٌ واحد لا يوثّق شيئاً — والخادم يرفضه."
      :error="form.errors.value.findings" @update:model-value="form.touch('findings')"
    />

    <!--
      أعلام محدَّدة لا نصّ حرّ.
      «تعثّر دراسي» و«تعثر دراسى» و«ضعف في الدراسة» ثلاثة نصوص لشيء واحد،
      ولا يجمعها تقرير. القائمة المغلقة تجعل السؤال «كم طفلاً يحتاج دعماً
      نفسياً؟» قابلاً للإجابة أصلاً.
    -->
    <fieldset>
      <legend class="block text-sm font-medium mb-2">احتياجات لُوحظت</legend>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="need in NEEDS" :key="need"
          class="badge border transition-colors" type="button"
          :class="flags.includes(need)
            ? 'bg-clay-600 text-white border-clay-600'
            : 'bg-sand-0 text-sand-700 border-sand-200 hover:border-clay-300'"
          :aria-pressed="flags.includes(need)"
          @click="toggle(need)"
        >
          {{ need }}
        </button>
      </div>
      <p v-if="form.errors.value.needs_flagged" class="text-xs text-clay-700 mt-1" role="alert">
        {{ form.errors.value.needs_flagged[0] }}
      </p>
    </fieldset>

    <template #actions>
      <button class="btn btn-outline" type="button" :disabled="form.submitting.value" @click="emit('close')">
        إلغاء
      </button>
      <button class="btn btn-primary" type="submit" :disabled="form.submitting.value">
        <BaseIcon v-if="form.submitting.value" name="loader" :size="15" class="animate-spin" />
        {{ form.submitting.value ? 'جارٍ التسجيل…' : 'سجّل الزيارة' }}
      </button>
    </template>
  </FormModal>
</template>

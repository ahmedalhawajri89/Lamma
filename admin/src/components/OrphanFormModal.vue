<script setup>
/**
 * إنشاء ملف يتيم وتحريره.
 *
 * نموذج واحد للحالتين لا اثنان: الحقول نفسها والقواعد نفسها، ونسختان
 * تفترقان مع أول تعديل — فيُضاف حقل إلى شاشة الإنشاء وحدها ويُنسى في
 * التحرير.
 *
 * ولا حقل «العمر»: العمر يتغيّر كل عام وتاريخ الميلاد لا. حفظ العمر يعني
 * بياناتٍ تكذب صامتةً بعد أول عيد ميلاد.
 */
import { computed, ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },

  /** الملف عند التحرير، و`null` عند الإنشاء. */
  orphan: { type: Object, default: null },

  /** ‏`/admin/references` — البرامج والتعدادات. */
  references: { type: Object, default: () => ({}) },

  /** الأسر للاختيار منها — `[{ id, family_code, guardian_name }]`. */
  families: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const editing = computed(() => Boolean(props.orphan?.id))

const BLANK = {
  first_name: '',
  last_name: '',
  gender: '',
  date_of_birth: '',
  family_id: '',
  program_id: '',
  monthly_need: null,
  is_urgent: false,
  grade_level: '',
  health_status: '',
  dream: '',
  story: '',
}

const photo = ref(null)
const photoInput = ref(null)

const form = useForm(BLANK, (values) => (editing.value
  ? admin.orphans.update(props.orphan.id, values, photo.value)
  : admin.orphans.create(values, photo.value)))

function onPhoto(event) {
  photo.value = event.target.files?.[0] ?? null
  form.touch('photo')
}

/**
 * حدّا تاريخ الميلاد يُشتقّان من قاعدة الخادم (`before:today` و
 * `after:-18 years`) لا يُكتبان رقمين.
 *
 * فرقٌ عملي: `max` و`min` على الحقل يجعلان منتقي التاريخ في المتصفح يمنع
 * الخطأ أصلاً بدل أن يردّه الخادم بعد ملء النموذج كله.
 */
const dobRange = computed(() => {
  const today = new Date()
  const floor = new Date(today)
  floor.setFullYear(floor.getFullYear() - 18)
  floor.setDate(floor.getDate() + 1)

  const iso = (d) => d.toISOString().slice(0, 10)
  return { min: iso(floor), max: iso(new Date(today.getTime() - 86400000)) }
})

const familyOptions = computed(() =>
  props.families.map((f) => ({
    value: f.id,
    // `guardian.full_name` هو ما يرسله الخادم؛ كانت تُقرأ أسماء حقول غير
    // موجودة فتظهر كل الأسر «بلا وصيّ» ولا يُفرَّق بينها
    label: `${f.family_code} · ${f.guardian?.full_name ?? 'بلا وصيّ'}`,
  })),
)

const programOptions = computed(() =>
  (props.references.programs ?? []).map((p) => ({ value: p.id, label: `${p.name} · $${p.price}` })),
)

/**
 * اختيار البرنامج يقترح سعره في «الحاجة الشهرية».
 *
 * اقتراحٌ لا فرض: المبلغ يبقى قابلاً للتعديل، لأن حالات بعينها تحتاج أكثر
 * من سعر البرنامج. لكن الاقتراح يوفّر على المدخل كتابة الرقم في كل مرة —
 * وهو نفسه في أغلب الحالات.
 */
/**
 * يُستدعى حين يختار المستخدم البرنامج بيده، لا حين تتغيّر القيمة: مراقبٌ على
 * القيمة كان يعمل أيضاً عند تعبئة النموذج للتعديل، فيستبدل حاجةً شهريةً
 * مخصّصة بسعر البرنامج إن صادف أنها تساوي سعر أي برنامج.
 */
function onProgramPicked(id) {
  form.touch('program_id')

  const program = (props.references.programs ?? []).find((p) => p.id === Number(id))
  if (!program) return

  // لا نطمس رقماً كتبه المستخدم عمداً
  const suggested = (props.references.programs ?? []).some((p) => p.price === form.values.monthly_need)
  if (form.values.monthly_need === null || form.values.monthly_need === '' || suggested) {
    form.values.monthly_need = program.price
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return

    photo.value = null
    if (photoInput.value) photoInput.value.value = ''

    if (editing.value) {
      const o = props.orphan
      form.setValues({
        first_name: o.first_name ?? '',
        last_name: o.last_name ?? '',
        gender: o.gender ?? '',
        date_of_birth: o.date_of_birth ?? '',
        family_id: o.family?.id ?? '',

        /**
         * `db_id` لا `id`.
         *
         * `ProgramResource` تُرجع الـslug في حقل `id` عمداً — الموقع العام
         * يعرف البرامج بأسمائها اللاتينية كما في `data.js`. فالنافذة كانت
         * تُسند `"comprehensive"` إلى `program_id`: لا يُطابق أي خيار فيبقى
         * الحقل فارغاً، ويردّ الخادم «يجب أن يكون عدداً صحيحاً» عند الحفظ.
         */
        program_id: o.program?.db_id ?? '',
        monthly_need: o.monthly_need ?? null,
        is_urgent: Boolean(o.is_urgent),
        grade_level: o.grade_level ?? '',
        health_status: o.health_status ?? '',
        dream: o.dream ?? '',
        story: o.story ?? '',
      })
    } else {
      form.setValues(BLANK)
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
    :open="open" wide
    :title="editing ? 'تحرير الملف' : 'ملف يتيم جديد'"
    :subtitle="editing
      ? orphan.reference_code
      : 'يُنشأ الملف مسوّدةً، والنشر يمرّ بالتوثيق أولاً.'"
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.first_name" label="الاسم الأول" required autofocus
        :error="form.errors.value.first_name" @update:model-value="form.touch('first_name')"
      />
      <FormField
        v-model="form.values.last_name" label="اسم العائلة" required
        :error="form.errors.value.last_name" @update:model-value="form.touch('last_name')"
      />

      <FormField
        v-model="form.values.gender" label="الجنس" required
        :options="references.genders ?? []" placeholder="اختر"
        :error="form.errors.value.gender" @update:model-value="form.touch('gender')"
      />
      <FormField
        v-model="form.values.date_of_birth" label="تاريخ الميلاد" type="date" required
        :min="dobRange.min" :max="dobRange.max"
        hint="حتى 18 سنة، وعندها يتخرّج من البرنامج."
        :error="form.errors.value.date_of_birth" @update:model-value="form.touch('date_of_birth')"
      />

      <FormField
        v-model="form.values.family_id" label="الأسرة" required
        :options="familyOptions" placeholder="اختر الأسرة"
        hint="لا يُنشأ يتيم بلا أسرة، فالوصيّ والعنوان يأتيان منها."
        :error="form.errors.value.family_id" @update:model-value="form.touch('family_id')"
      />
      <FormField
        v-model="form.values.program_id" label="البرنامج" required
        :options="programOptions" placeholder="اختر البرنامج"
        :error="form.errors.value.program_id" @update:model-value="onProgramPicked"
      />

      <FormField
        v-model="form.values.monthly_need" label="الحاجة الشهرية ($)" type="number" required
        :min="5" :max="1000" :step="1"
        hint="يُقترح من سعر البرنامج، ويبقى قابلاً للتعديل."
        :error="form.errors.value.monthly_need" @update:model-value="form.touch('monthly_need')"
      />
      <FormField
        v-model="form.values.grade_level" label="الصف الدراسي"
        placeholder="الصف السادس"
        :error="form.errors.value.grade_level" @update:model-value="form.touch('grade_level')"
      />
    </div>

    <FormField
      v-model="form.values.health_status" label="الحالة الصحية"
      hint="ما يؤثر في الكفالة فقط. لا تفاصيل تشخيصية، فالبطاقة العامة لا تعرضها."
      :error="form.errors.value.health_status" @update:model-value="form.touch('health_status')"
    />

    <FormField
      v-model="form.values.dream" label="ماذا يريد أن يصير؟"
      placeholder="طبيبة"
      :error="form.errors.value.dream" @update:model-value="form.touch('dream')"
    />

    <FormField
      v-model="form.values.story" label="القصة" type="textarea" :rows="5"
      hint="بصيغة تحفظ كرامته: ما يحتاجه، لا ما يُبكي القارئ."
      :error="form.errors.value.story" @update:model-value="form.touch('story')"
    />

    <div>
      <label class="block text-sm font-medium mb-1.5" for="orphanPhoto">
        {{ editing ? 'استبدال الصورة' : 'الصورة' }}
      </label>

      <div class="flex items-center gap-3">
        <img
          v-if="editing && orphan.photo_url" :src="orphan.photo_url" alt=""
          class="size-12 rounded-lg object-cover shrink-0" width="48" height="48"
        >
        <input
          id="orphanPhoto" ref="photoInput" class="input" type="file"
          accept="image/jpeg,image/png,image/webp"
          aria-describedby="orphanPhotoHelp" @change="onPhoto"
        >
      </div>

      <!--
        سياسة الصور مكتوبة على الموقع العام، وهذا مكان تطبيقها: لا بكاء
        ولا جوع ولا جسد مكشوف. والبطاقة بلا صورة أفضل من صورة تسيء لصاحبها.
      -->
      <p id="orphanPhotoHelp" class="text-xs text-sand-500 mt-1">
        بموافقة الوصيّ المكتوبة. لا بكاء ولا جوع، والبطاقة بلا صورة أفضل من
        صورة تسيء إلى صاحبها.
      </p>
      <p v-if="form.errors.value.photo" class="text-xs text-rose-700 mt-1" role="alert">
        {{ form.errors.value.photo[0] }}
      </p>
    </div>
    <FormField
      v-model="form.values.is_urgent" label="حالة عاجلة تُعرض في مقدمة الموقع"
      type="checkbox"
      :error="form.errors.value.is_urgent" @update:model-value="form.touch('is_urgent')"
    />
  </FormModal>
</template>

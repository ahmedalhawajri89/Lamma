<script setup>
/**
 * إنشاء أسرة وتحريرها.
 *
 * الأسرة أسبق من اليتيم: منها يُقرأ الوصيّ والدولة والمدينة، ولا يُنشأ ملف
 * بلا واحدة. فكان غياب هذا النموذج يعني أن المنصة لا تستقبل حالةً جديدة
 * أصلاً، مهما كثرت شاشات العرض فيها.
 *
 * ورقم هوية الوصيّ يُرسَل ولا يُعرض عند التحرير: الخادم يخزّن بصمةً مُملَّحة
 * منه لا الرقم نفسه، فلا شيء يُعاد ليُملأ به الحقل. وإظهار حقل فارغ عند
 * التحرير يوحي بأن الرقم ضاع — فنخفيه هناك ونكتفي به عند الإنشاء.
 */
import { computed, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  family: { type: Object, default: null },
  references: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'saved'])

const editing = computed(() => Boolean(props.family?.id))

const BLANK = {
  guardian: {
    full_name: '',
    relation: '',
    phone: '',
    national_id: '',
    occupation: '',
    monthly_income: null,
  },
  country_id: '',
  city_id: '',
  address: '',
  members_count: null,
  monthly_income: null,
  housing_status: '',
  // المرحلة 2: الجمعية التي تعرف الأسرة، ومع من يعيش أطفالها، وإقرار ذلك الآن
  association_id: '',
  care_arrangement: '',
  care_attest: false,
}

const form = useForm(BLANK, ({ guardian, ...family }) => {
  /**
   * لا `structuredClone` هنا: `values` كائن Vue تفاعلي (‏Proxy)، و
   * `structuredClone` يرميه بـ`DataCloneError` — فيسقط الإرسال قبل أن يُغادر
   * المتصفح، ويرى المستخدم «تعذّر الحفظ» بلا أي طلب في الشبكة.
   * والنشر (`...`) يكفي: axios يُسلسل الباقي بـ`JSON.stringify` وهي تعبر
   * الوكيل بلا مشكلة.
   */
  const { national_id: nationalId, ...guardianWithoutId } = guardian

  // الرقم يُرسَل عند الإنشاء وحده: الخادم يخزّن بصمته لا يعدّلها
  const payload = {
    ...family,
    // الفارغ «لا شيء» لا نصٌّ فارغ: الخادم يتحقق من وجود الجمعية
    association_id: family.association_id || null,
    care_arrangement: family.care_arrangement || null,
    guardian: editing.value ? guardianWithoutId : { ...guardian },
  }

  return editing.value
    ? admin.families.update(props.family.id, payload)
    : admin.families.create(payload)
})

const countryOptions = computed(() =>
  (props.references.countries ?? []).map((c) => ({ value: c.id, label: c.name })),
)

/**
 * المدن تتبع الدولة المختارة.
 *
 * قائمةٌ بكل مدن كل الدول تجعل المستخدم يبحث في مئة اسم عن ثلاثة تعنيه،
 * وتسمح باختيار مدينة في غير دولتها — تناقضٌ لا يلتقطه أي تحقق.
 */
const cityOptions = computed(() => {
  const country = (props.references.countries ?? []).find((c) => c.id === Number(form.values.country_id))
  return (country?.cities ?? []).map((c) => ({ value: c.id, label: c.name }))
})

/**
 * المدينة تُفرَّغ حين يغيّر المستخدم الدولة بيده، لا حين تتغيّر القيمة.
 *
 * مراقبٌ على القيمة كان يرى تعبئة النموذج عند فتحه للتعديل (من فارغ إلى
 * دولة الأسرة) تغييراً، فيمسح المدينة، ثم يحفظها الخادم فارغة بصمت.
 */
function onCountryPicked() {
  form.touch('country_id')
  form.values.city_id = ''
  form.values.association_id = ''
}

/** جمعيات بلد الأسرة وحدها؛ غير الموثّقة تقول ذلك في اسمها. */
const associationOptions = computed(() =>
  (props.references.associations ?? [])
    .filter((a) => a.country_id === Number(form.values.country_id))
    .map((a) => ({ value: a.id, label: a.status === 'verified' ? a.name : `${a.name} (${a.status_label})` })),
)

/** حال الإقرار القائم، ليُرى قبل التأكيد من جديد */
const attestation = computed(() => {
  const f = props.family
  if (!editing.value || !f?.care_attested_at) return { tone: 'text-amber-800', text: 'لا إقرار بعد: لن يُنشر أطفالها حتى يُؤكَّد مع من يعيشون.' }
  const due = f.care_review_due_at ? new Date(f.care_review_due_at).toLocaleDateString('ar-u-nu-latn', { year: 'numeric', month: 'long', day: 'numeric' }) : ''
  return f.care_current
    ? { tone: 'text-olive-700', text: `مُقَرّ${f.care_attested_by ? ` بيد ${f.care_attested_by}` : ''}، ويُراجَع في ${due}.` }
    : { tone: 'text-rose-700', text: 'الإقرار أقدم من اثني عشر شهراً: أكّده بزيارة.' }
})

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return

    if (editing.value) {
      const f = props.family
      form.setValues({
        guardian: {
          full_name: f.guardian?.full_name ?? '',
          relation: f.guardian?.relation ?? '',
          phone: f.guardian?.phone ?? '',
          national_id: '',
          occupation: f.guardian?.occupation ?? '',
          monthly_income: f.guardian?.monthly_income ?? null,
        },
        country_id: f.country?.id ?? '',
        city_id: f.city?.id ?? '',
        address: f.address ?? '',
        members_count: f.members_count ?? null,
        monthly_income: f.monthly_income ?? null,
        housing_status: f.housing_status ?? '',
        association_id: f.association_id ?? '',
        care_arrangement: f.care_arrangement ?? '',
        care_attest: false,
      })
    } else {
      form.setValues(BLANK)
    }
  },
  { immediate: true },
)

/** الخادم يُعشّش أخطاء الوصيّ تحت `guardian.full_name` لا `full_name`. */
const guardianError = (field) => form.errors.value[`guardian.${field}`]

async function save() {
  const saved = await form.submit()
  if (saved) emit('saved', saved)
}
</script>

<template>
  <FormModal
    :open="open" wide
    :title="editing ? 'تحرير الأسرة' : 'أسرة جديدة'"
    :subtitle="editing ? family.family_code : 'الوصيّ والموقع يُقرآن من هنا لكل يتيم في الأسرة.'"
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <fieldset class="space-y-4">
      <legend class="text-sm font-semibold mb-2">الوصيّ</legend>

      <div class="grid sm:grid-cols-2 gap-4">
        <FormField
          v-model="form.values.guardian.full_name" label="الاسم الكامل" required autofocus
          :error="guardianError('full_name')" @update:model-value="form.touch('guardian.full_name')"
        />
        <FormField
          v-model="form.values.guardian.relation" label="صلته بالأطفال" required
          :options="references.relations ?? []" placeholder="اختر الصلة"
          :error="guardianError('relation')" @update:model-value="form.touch('guardian.relation')"
        />

        <FormField
          v-model="form.values.guardian.phone" label="الهاتف" type="tel"
          placeholder="+970 59 000 0000"
          :error="guardianError('phone')" @update:model-value="form.touch('guardian.phone')"
        />
        <FormField
          v-model="form.values.guardian.occupation" label="المهنة"
          :error="guardianError('occupation')" @update:model-value="form.touch('guardian.occupation')"
        />

        <FormField
          v-if="!editing"
          v-model="form.values.guardian.national_id" label="رقم الهوية"
          hint="يُخزَّن بصمةً مُملَّحة لا رقماً: تكفي لكشف التكرار ولا تكفي لإعادة بنائه."
          :error="guardianError('national_id')" @update:model-value="form.touch('guardian.national_id')"
        />
        <FormField
          v-model="form.values.guardian.monthly_income" label="دخل الوصيّ الشهري ($)"
          type="number" :min="0" :step="1"
          :error="guardianError('monthly_income')"
          @update:model-value="form.touch('guardian.monthly_income')"
        />
      </div>
    </fieldset>

    <fieldset class="space-y-4 pt-2 border-t border-sand-100">
      <legend class="text-sm font-semibold mb-2 mt-3">الأسرة</legend>

      <div class="grid sm:grid-cols-2 gap-4">
        <FormField
          v-model="form.values.country_id" label="الدولة" required
          :options="countryOptions" placeholder="اختر الدولة"
          :error="form.errors.value.country_id" @update:model-value="onCountryPicked"
        />
        <FormField
          v-model="form.values.city_id" label="المدينة"
          :options="cityOptions" :disabled="!form.values.country_id"
          :placeholder="form.values.country_id ? 'اختر المدينة' : 'اختر الدولة أولاً'"
          :error="form.errors.value.city_id" @update:model-value="form.touch('city_id')"
        />

        <FormField
          v-model="form.values.members_count" label="عدد الأفراد" type="number" required
          :min="1" :max="30" :step="1"
          :error="form.errors.value.members_count" @update:model-value="form.touch('members_count')"
        />
        <FormField
          v-model="form.values.monthly_income" label="دخل الأسرة الشهري ($)"
          type="number" :min="0" :step="1"
          hint="اتركه فارغاً إن كان مجهولاً، فالفراغ غياب والصفر قيمة."
          :error="form.errors.value.monthly_income" @update:model-value="form.touch('monthly_income')"
        />
      </div>

      <FormField
        v-model="form.values.housing_status" label="وضع السكن" required
        :options="references.housing ?? []" placeholder="اختر"
        :error="form.errors.value.housing_status" @update:model-value="form.touch('housing_status')"
      />

      <FormField
        v-model="form.values.address" label="العنوان"
        hint="للاستعمال الداخلي، ولا يظهر على الموقع العام."
        :error="form.errors.value.address" @update:model-value="form.touch('address')"
      />
    </fieldset>

    <fieldset class="space-y-4 pt-2 border-t border-sand-100">
      <legend class="text-sm font-semibold mb-2 mt-3">الرعاية والجمعية</legend>

      <div class="grid sm:grid-cols-2 gap-4">
        <FormField
          v-model="form.values.care_arrangement" label="مع من يعيش الأطفال"
          :options="references.care_arrangements ?? []" placeholder="اختر"
          hint="دار الرعاية تُسجَّل ولا تُنشر للكفالة: «لمّة» تكفل الأطفال في أسرهم."
          :error="form.errors.value.care_arrangement" @update:model-value="form.touch('care_arrangement')"
        />
        <FormField
          v-model="form.values.association_id" label="الجمعية الشريكة"
          :options="associationOptions" :disabled="!form.values.country_id"
          :placeholder="form.values.country_id ? (associationOptions.length ? 'اختر الجمعية' : 'لا جمعية في هذا البلد بعد') : 'اختر الدولة أولاً'"
          hint="التي تعرف الأسرة وتزورها. لا يُنشر طفل إلا عبر جمعية موثّقة."
          :error="form.errors.value.association_id" @update:model-value="form.touch('association_id')"
        />
      </div>

      <label class="flex items-start gap-3 rounded-xl border border-sand-200 p-3.5 cursor-pointer">
        <input v-model="form.values.care_attest" type="checkbox" class="mt-1 size-4 accent-teal-700" :disabled="!form.values.care_arrangement">
        <span class="text-sm">
          <span class="font-medium">أؤكّد الآن أن الأطفال يعيشون هكذا</span>
          <span class="block text-xs text-sand-500 mt-0.5">رأيت ذلك بنفسك أو في زيارة حديثة. يُسجَّل باسمك، ويُراجَع بعد اثني عشر شهراً.</span>
          <span class="block text-xs mt-1.5" :class="attestation.tone">{{ attestation.text }}</span>
        </span>
      </label>
    </fieldset>
  </FormModal>
</template>

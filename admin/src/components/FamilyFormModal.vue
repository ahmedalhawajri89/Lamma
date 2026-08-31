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
  const payload = { ...family, guardian: editing.value ? guardianWithoutId : { ...guardian } }

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

watch(
  () => form.values.country_id,
  (id, previous) => {
    if (previous !== undefined && id !== previous) form.values.city_id = ''
  },
)

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
          hint="يُخزَّن بصمةً مُملَّحة لا رقماً — تكفي لكشف التكرار ولا تكفي لإعادة بنائه."
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
          :error="form.errors.value.country_id" @update:model-value="form.touch('country_id')"
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
          hint="اتركه فارغاً إن كان مجهولاً — الفراغ غياب، والصفر قيمة."
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
        hint="للاستعمال الداخلي — لا يظهر على الموقع العام."
        :error="form.errors.value.address" @update:model-value="form.touch('address')"
      />
    </fieldset>
  </FormModal>
</template>

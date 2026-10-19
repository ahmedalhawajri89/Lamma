<script setup>
/**
 * تسجيل جمعية شريكة وتعديلها.
 *
 * الجديدة تُسجَّل «قيد المراجعة»؛ التوثيق والإيقاف إجراءان في الشاشة لا حقلان
 * هنا. وبيانات التحويل تُكتب ولا تُقرأ: الخادم لا يعيدها، فالحقل فارغ دائماً
 * ويقول إن كانت مسجّلة، وكتابته تستبدلها.
 */
import { computed, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  association: { type: Object, default: null },
  countries: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'saved'])

const editing = computed(() => Boolean(props.association?.id))

const BLANK = {
  name_ar: '', name_en: '', country_id: '', city_id: '',
  registration_number: '', registration_authority: '',
  email: '', phone: '', website: '',
  about_ar: '', about_en: '',
  transfer_details: '', transfer_currency: '',
}

const form = useForm(BLANK, (values) => {
  // بيانات التحويل الفارغة لا تُرسل: لا تمسح المسجّلة
  const payload = Object.fromEntries(Object.entries(values).map(([k, v]) => [k, v === '' ? null : v]))
  if (!values.transfer_details) delete payload.transfer_details
  return editing.value ? admin.associations.update(props.association.id, payload) : admin.associations.create(payload)
})

const countryOptions = computed(() => props.countries.map((c) => ({ value: c.id, label: c.name })))
const cityOptions = computed(() =>
  (props.countries.find((c) => String(c.id) === String(form.values.country_id))?.cities ?? [])
    .map((c) => ({ value: c.id, label: c.name })),
)

watch(() => form.values.country_id, (v, old) => { if (old !== undefined && v !== old) form.values.city_id = '' })

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    const a = props.association
    form.setValues(a
      ? Object.fromEntries(Object.keys(BLANK).map((k) => [k, k === 'transfer_details' ? '' : (a[k] ?? '')]))
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
    :open="open"
    :title="editing ? 'تعديل الجمعية' : 'جمعية شريكة جديدة'"
    :subtitle="editing ? association.name_ar : 'تُسجَّل قيد المراجعة، وتُوثَّق حين تكتمل أوراقها.'"
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.name_ar" label="الاسم بالعربية" required autofocus
        :error="form.errors.value.name_ar" @update:model-value="form.touch('name_ar')"
      />
      <FormField
        v-model="form.values.name_en" label="الاسم بالإنجليزية"
        hint="منه رابط صفحتها على الموقع."
        :error="form.errors.value.name_en" @update:model-value="form.touch('name_en')"
      />
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.country_id" label="البلد" required :options="countryOptions" placeholder="اختر البلد"
        :error="form.errors.value.country_id" @update:model-value="form.touch('country_id')"
      />
      <FormField
        v-model="form.values.city_id" label="المدينة" :options="cityOptions" placeholder="اختيارية"
        :disabled="!form.values.country_id"
        :error="form.errors.value.city_id" @update:model-value="form.touch('city_id')"
      />
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.registration_number" label="رقم التسجيل"
        :error="form.errors.value.registration_number" @update:model-value="form.touch('registration_number')"
      />
      <FormField
        v-model="form.values.registration_authority" label="جهة التسجيل"
        placeholder="سجل الجمعيات الأهلية"
        :error="form.errors.value.registration_authority" @update:model-value="form.touch('registration_authority')"
      />
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
      <FormField v-model="form.values.email" label="البريد" type="email" :error="form.errors.value.email" @update:model-value="form.touch('email')" />
      <FormField v-model="form.values.phone" label="الهاتف" type="tel" :error="form.errors.value.phone" @update:model-value="form.touch('phone')" />
      <FormField v-model="form.values.website" label="الموقع" type="url" placeholder="https://" :error="form.errors.value.website" @update:model-value="form.touch('website')" />
    </div>

    <FormField
      v-model="form.values.about_ar" label="نبذة بالعربية" type="textarea" :rows="3"
      hint="تظهر في صفحة الجمعية على الموقع."
      :error="form.errors.value.about_ar" @update:model-value="form.touch('about_ar')"
    />
    <FormField
      v-model="form.values.about_en" label="نبذة بالإنجليزية" type="textarea" :rows="3"
      :error="form.errors.value.about_en" @update:model-value="form.touch('about_en')"
    />

    <div class="grid sm:grid-cols-[1fr_8rem] gap-4">
      <FormField
        v-model="form.values.transfer_details" label="بيانات التحويل البنكي" type="textarea" :rows="2"
        :hint="association?.has_transfer_details ? 'مسجّلة ومشفّرة. اكتب جديدة لتستبدلها، أو اتركه فارغاً لتبقى.' : 'تُشفَّر ولا تُعرض بعد الحفظ.'"
        :error="form.errors.value.transfer_details" @update:model-value="form.touch('transfer_details')"
      />
      <FormField
        v-model="form.values.transfer_currency" label="العملة" placeholder="USD"
        :error="form.errors.value.transfer_currency" @update:model-value="form.touch('transfer_currency')"
      />
    </div>
  </FormModal>
</template>

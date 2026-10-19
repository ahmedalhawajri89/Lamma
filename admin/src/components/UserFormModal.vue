<script setup>
/**
 * إنشاء موظف وتعديله.
 *
 * لا حقل كلمة مرور هنا، وهذا مقصود: الخادم يولّد كلمةً عشوائية لا تُعرض
 * لأحد، والموظف يضبط كلمته عبر رابط الاستعادة. كلمةٌ مؤقتة يكتبها المدير
 * تمرّ في بريد أو محادثة وتبقى هناك إلى الأبد.
 *
 * والكفلاء لا يُنشَؤون من هنا — يسجّلون أنفسهم من الموقع. فالأدوار
 * المعروضة أدوار موظفين وحدها.
 */
import { computed, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  user: { type: Object, default: null },
  countries: { type: Array, default: () => [] },
  /** الجمعيات الشريكة: لموظف الجمعية نطاقه أسرها (StaffScope في الخادم) */
  associations: { type: Array, default: () => [] },

  /** المستخدم الحالي — لا يوقف حسابه بنفسه. */
  currentUserId: { type: [Number, String], default: null },
})

const emit = defineEmits(['close', 'saved'])

const editing = computed(() => Boolean(props.user?.id))
const isSelf = computed(() => editing.value && String(props.user.id) === String(props.currentUserId))

const BLANK = {
  name: '',
  email: '',
  phone: '',
  role: '',
  title: '',
  assigned_country_id: '',
  association_id: '',
  status: 'active',
}

const form = useForm(BLANK, ({ email, status: _, ...values }) => {
  // موظف الجمعية: بلده بلدها، يحسبه الخادم؛ والفارغ «لا شيء»
  const rest = { ...values, association_id: values.association_id || null, assigned_country_id: values.assigned_country_id || null }
  return editing.value
    // البريد لا يُعدَّل: هو هوية الحساب، وتغييره يقطع رابط الاستعادة المُرسَل.
    // والحالة لا تُرسَل من هنا: الإيقاف وإعادة التفعيل في درج الموظف بتأكيد
    ? admin.users.update(props.user.id, rest)
    : admin.users.create({ ...rest, email })
})

const ROLES = [
  { value: 'admin', label: 'مدير النظام: كل شيء' },
  { value: 'accountant', label: 'محاسب: المال والتقارير، بلا ملفات' },
  { value: 'field_supervisor', label: 'مشرف ميداني: الملفات والزيارات، بلا مال' },
]

const countryOptions = computed(() =>
  props.countries.map((c) => ({ value: c.id, label: c.name })),
)
const associationOptions = computed(() =>
  props.associations.map((a) => ({ value: a.id, label: a.status === 'verified' ? a.name : `${a.name} (${a.status_label})` })),
)
/** موظف الجمعية لا يُختار له بلد: بلده بلد جمعيته */
const byAssociation = computed(() => Boolean(form.values.association_id))

/**
 * البلد المُسنَد يعني شيئاً للمشرف الميداني وحده.
 *
 * `OrphanPolicy` تحصر المشرف في بلده؛ أما المدير والمحاسب فبلا نطاق
 * جغرافي أصلاً، وإسناد بلدٍ لهما يوحي بحدٍّ لا وجود له.
 */
const scoped = computed(() => form.values.role === 'field_supervisor')

watch(
  () => form.values.role,
  (role, previous) => {
    if (previous !== undefined && role !== 'field_supervisor') {
      form.values.assigned_country_id = ''
      form.values.association_id = ''
    }
  },
)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return

    if (editing.value) {
      const u = props.user
      form.setValues({
        name: u.name ?? '',
        email: u.email ?? '',
        phone: u.phone ?? '',
        role: u.roles?.[0] ?? '',
        // من ملف الموظف: كان يُقرأ من حقل لا يرسله الخادم، فيُرسَل فارغاً
        // مع كل تعديل ويُمسح بلد المشرف
        assigned_country_id: u.staff_profile?.assigned_country_id ?? '',
        association_id: u.staff_profile?.association_id ?? '',
        title: u.staff_profile?.title ?? '',
        status: u.status ?? 'active',
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
    :open="open"
    :title="editing ? 'تعديل الموظف' : 'موظف جديد'"
    :subtitle="editing
      ? user.email
      : 'يُنشأ الحساب معلَّقاً، والموظف يضبط كلمة مروره بنفسه عبر رابط الاستعادة.'"
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <FormField
      v-model="form.values.name" label="الاسم" required autofocus
      :error="form.errors.value.name" @update:model-value="form.touch('name')"
    />

    <FormField
      v-if="!editing"
      v-model="form.values.email" label="البريد الإلكتروني" type="email" required
      hint="إليه يُرسَل رابط تعيين كلمة المرور."
      :error="form.errors.value.email" @update:model-value="form.touch('email')"
    />

    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.phone" label="الهاتف" type="tel"
        :error="form.errors.value.phone" @update:model-value="form.touch('phone')"
      />
      <FormField
        v-model="form.values.title" label="المسمّى الوظيفي"
        placeholder="مشرف ميداني في غزة"
        :error="form.errors.value.title" @update:model-value="form.touch('title')"
      />
    </div>

    <FormField
      v-model="form.values.role" label="الدور" required
      :options="ROLES" placeholder="اختر الدور" :disabled="isSelf"
      :hint="isSelf ? 'لا يمكنك تغيير دورك أنت. يغيّره مدير آخر.' : ''"
      :error="form.errors.value.role" @update:model-value="form.touch('role')"
    />

    <FormField
      v-if="scoped"
      v-model="form.values.association_id" label="يعمل لجمعية شريكة"
      :options="associationOptions" placeholder="لا، موظف المنصة"
      hint="موظف الجمعية يرى أسر جمعيته وحدها، ولو عملت معها جمعيةٌ أخرى في البلد نفسه."
      :error="form.errors.value.association_id"
      @update:model-value="form.touch('association_id')"
    />

    <FormField
      v-if="scoped && !byAssociation"
      v-model="form.values.assigned_country_id" label="البلد المُسنَد" required
      :options="countryOptions" placeholder="اختر البلد"
      hint="المشرف يرى أيتام بلده وحده، وبلا بلد لا يرى شيئاً."
      :error="form.errors.value.assigned_country_id"
      @update:model-value="form.touch('assigned_country_id')"
    />
  </FormModal>
</template>

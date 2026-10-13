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
  status: 'active',
}

const form = useForm(BLANK, ({ email, title, status, ...rest }) =>
  editing.value
    // البريد والمسمّى لا يُعدَّلان من هنا: البريد هوية الحساب، وتغييره
    // يقطع رابط الاستعادة المُرسَل؛ والمسمّى في ملف الموظف لا في الحساب
    ? admin.users.update(props.user.id, { ...rest, status })
    : admin.users.create({ ...rest, email, title }),
)

const ROLES = [
  { value: 'admin', label: 'مدير النظام: كل شيء' },
  { value: 'accountant', label: 'محاسب: المال والتقارير، بلا ملفات' },
  { value: 'field_supervisor', label: 'مشرف ميداني: الملفات والزيارات، بلا مال' },
]

const STATUSES = [
  { value: 'active', label: 'نشط' },
  { value: 'suspended', label: 'موقوف' },
]

/** «بانتظار التفعيل» حالة يصلها الحساب وحده، فتُعرض لمن هو فيها ولا تُختار */
const statusOptions = computed(() =>
  props.user?.status === 'pending'
    ? [{ value: 'pending', label: 'بانتظار التفعيل' }, ...STATUSES]
    : STATUSES,
)

const countryOptions = computed(() =>
  props.countries.map((c) => ({ value: c.id, label: c.name })),
)

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
    if (previous !== undefined && role !== 'field_supervisor') form.values.assigned_country_id = ''
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
        title: u.title ?? '',
        // من ملف الموظف: كان يُقرأ من حقل لا يرسله الخادم، فيُرسَل فارغاً
        // مع كل تعديل ويُمسح بلد المشرف
        assigned_country_id: u.staff_profile?.assigned_country_id ?? '',
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
        v-if="!editing"
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
      v-model="form.values.assigned_country_id" label="البلد المُسنَد" required
      :options="countryOptions" placeholder="اختر البلد"
      hint="المشرف يرى أيتام بلده وحده، وبلا بلد لا يرى شيئاً."
      :error="form.errors.value.assigned_country_id"
      @update:model-value="form.touch('assigned_country_id')"
    />

    <template v-if="editing">
      <FormField
        v-model="form.values.status" label="الحالة" required
        :options="statusOptions" :disabled="isSelf"
        :hint="isSelf ? 'لا يمكنك إيقاف حسابك أنت.' : ''"
        :error="form.errors.value.status" @update:model-value="form.touch('status')"
      />
    </template>
  </FormModal>
</template>

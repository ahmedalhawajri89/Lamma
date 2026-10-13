<script setup>
/**
 * الإعدادات.
 *
 * كانت النقطتان جاهزتين (`GET` و`PUT`) ولا شاشة لهما إطلاقاً: يُغيَّر اسم
 * المنصة أو نسبة التشغيل المستهدَفة بتحرير قاعدة البيانات يدوياً.
 *
 * وليست قيماً زخرفية: `site.*` تظهر في تذييل الموقع العام وصفحة التواصل،
 * و`finance.overhead_target_percent` هو الرقم الذي تُقاس عليه نسبة التشغيل
 * المنشورة على صفحة الشفافية.
 */
import { computed, onMounted, ref } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import { confirmDialog } from '@/composables/useConfirm'
import PageHeader from '@/components/PageHeader.vue'
import FormField from '@/components/FormField.vue'
import BaseIcon from '@/components/BaseIcon.vue'

/**
 * مخطّط الشاشة: ما يُعرض، وبأي نوع، وتحت أي عنوان.
 *
 * الخادم يردّ خريطةً مسطّحة `"group.key" => value` بلا أي وصف — ولا يعرف
 * أن `overhead_target_percent` نسبة مئوية لا نصّ. فالمعنى يعيش هنا، وهو
 * المكان الوحيد الذي يُقرأ فيه للعرض.
 */
const SCHEMA = [
  {
    title: 'المنصة',
    hint: 'تظهر في تذييل الموقع العام وصفحة التواصل.',
    fields: [
      { group: 'site', key: 'name', label: 'اسم المنصة', required: true },
      { group: 'site', key: 'tagline', label: 'الوصف المختصر' },
      { group: 'site', key: 'email', label: 'بريد التواصل', type: 'email' },
      { group: 'site', key: 'phone', label: 'الهاتف', type: 'tel' },
      { group: 'site', key: 'address', label: 'العنوان' },
      {
        group: 'site', key: 'start_year', label: 'سنة التأسيس', type: 'number',
        min: 1900, max: new Date().getFullYear(),
        hint: 'منها يُحسب «نعمل منذ كذا عاماً» على الموقع.',
      },
    ],
  },
  {
    title: 'المال',
    hint: 'يُقاس عليها ما يُنشر على صفحة الشفافية.',
    fields: [
      {
        group: 'finance', key: 'base_currency', label: 'عملة الأساس',
        options: ['USD'], locked: true,
        hint: 'ثابتة في إعداد الخادم: كل المبالغ المخزّنة محسوبة بها، فتغييرها هنا يخلط عملتين تحت اسم واحد.',
      },
      {
        group: 'finance', key: 'overhead_target_percent', label: 'نسبة التشغيل المستهدَفة (٪)',
        type: 'number', min: 0, max: 30, step: 1,
        hint: 'هدفٌ نُقاس عليه، لا حدٌّ يفرضه النظام. تجاوزُه يظهر في تقارير الشفافية كما هو.',
      },
    ],
  },
  {
    title: 'السياسات',
    fields: [
      {
        group: 'policy', key: 'moderate_messages', label: 'مراجعة رسائل الكفلاء قبل وصولها',
        type: 'checkbox',
        hint: 'إيقافها يعني أن رسالةً تصل طفلاً بلا أن يقرأها أحد منّا.',
      },
      {
        group: 'policy', key: 'report_cadence_months', label: 'كل كم شهر يُنتظر تقرير', type: 'number',
        min: 1, max: 12, step: 1,
        hint: 'منه تُحسب التقارير المتأخرة في لوحة القيادة.',
      },
    ],
  },
]

const FIELDS = SCHEMA.flatMap((section) => section.fields)

const loading = ref(true)
const failed = ref(false)
const blank = Object.fromEntries(FIELDS.map((f) => [`${f.group}.${f.key}`, '']))

/**
 * يُرسَل ما تغيّر وحده. كانت كل المفاتيح تُرسَل، فإن فشل التحميل وبقي
 * النموذج فارغاً ثم حُفظ تعديل واحد، مُسح اسم الموقع وبريده وهاتفه.
 */
const form = useForm(blank, (values) =>
  admin.updateSettings(
    FIELDS.filter((f) => `${f.group}.${f.key}` in form.changed()).map((f) => ({
      group: f.group,
      key: f.key,

      // خانة الاختيار تصل `true`/`false` منطقيةً، والخادم يخزّن نصّاً
      value: f.type === 'checkbox' ? String(Boolean(values[`${f.group}.${f.key}`])) : values[`${f.group}.${f.key}`],
    })),
  ),
)

const sections = computed(() => SCHEMA)

/** تغيير فعلي لا مجرّد لمس: كتابة القيمة نفسها لا تفعّل الحفظ */
const hasChanges = computed(() => Object.keys(form.changed()).length > 0)

/** القيم المحفوظة تصير ما يُقارَن به: بعد الحفظ تُقاس التغييرات منها لا مما قبلها */
function apply(stored) {
  form.setValues(
    Object.fromEntries(
      FIELDS.map((f) => {
        const name = `${f.group}.${f.key}`
        const value = stored[name]
        return [name, f.type === 'checkbox' ? Boolean(value) : (value ?? '')]
      }),
    ),
  )
}

async function save() {
  // إيقاف مراجعة الرسائل قرارُ حماية طفل، فيُؤكَّد بما يعنيه
  if (form.changed()['policy.moderate_messages'] === false) {
    const ok = await confirmDialog({
      title: 'إيقاف مراجعة رسائل الكفلاء',
      message: 'ستصل رسائل الكفلاء إلى الأطفال فوراً دون أن يقرأها أحد قبلهم. سياسة حماية الطفل المعلنة على الموقع تَعِد بالمراجعة.',
      confirmLabel: 'أوقف المراجعة',
      danger: true,
    })
    if (!ok) return
  }

  const saved = await form.submit()
  if (saved && typeof saved === 'object') apply(saved)
}

async function load() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await admin.settings()
    apply(data.data ?? {})
  } catch {
    // لا نموذج بلا قيمه: حفظٌ من نموذج فارغ يمسح الإعدادات
    failed.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="الإعدادات"
      subtitle="ما يظهر منها على الموقع العام يتغيّر فور الحفظ."
    >
      <template #actions>
        <button
          class="btn btn-primary" type="button"
          :disabled="failed || loading || form.submitting.value || !hasChanges" @click="save"
        >
          <BaseIcon v-if="form.submitting.value" name="loader" :size="16" class="animate-spin" />
          {{ form.submitting.value ? 'جارٍ الحفظ…' : 'حفظ' }}
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="space-y-4">
      <div v-for="i in 3" :key="i" class="h-40 bg-sand-100 rounded-2xl animate-pulse" />
    </div>

    <div v-else-if="failed" class="card p-8 text-center" role="alert">
      <p class="font-medium text-sand-900">تعذّر تحميل الإعدادات</p>
      <p class="text-sm text-sand-500 mt-1">لا يُعرض النموذج قبل أن تُحمَّل القيم الحالية، كي لا يُحفظ فوقها فراغ.</p>
      <button class="btn btn-outline mt-4" type="button" @click="load">إعادة المحاولة</button>
    </div>

    <form v-else novalidate @submit.prevent="save">
      <!-- عمودان على الشاشات العريضة بدل عمود ضيّق وفراغ بجانبه -->
      <div class="grid xl:grid-cols-2 gap-4 items-start">
      <section v-for="section in sections" :key="section.title" class="card p-5">
        <h2 class="font-semibold mb-1">{{ section.title }}</h2>
        <p v-if="section.hint" class="text-sm text-sand-500 mb-4">{{ section.hint }}</p>

        <div class="space-y-4" :class="{ 'mt-4': !section.hint }">
          <FormField
            v-for="f in section.fields" :key="`${f.group}.${f.key}`"
            v-model="form.values[`${f.group}.${f.key}`]"
            :label="f.label" :type="f.type ?? 'text'" :options="f.options ?? null"
            :hint="f.hint ?? ''" :required="f.required ?? false"
            :min="f.min ?? null" :max="f.max ?? null" :step="f.step ?? null"
            :error="form.errors.value[`${f.group}__${f.key}`]"
            :disabled="f.locked ?? false"
            @update:model-value="form.touch(`${f.group}.${f.key}`)"
          />
        </div>
      </section>
      </div>

      <!-- زرّ ثانٍ في الأسفل: الصفحة أطول من الشاشة، والزرّ الأعلى يغيب -->
      <div class="flex justify-end mt-4">
        <button class="btn btn-primary" type="submit" :disabled="form.submitting.value || !hasChanges">
          <BaseIcon v-if="form.submitting.value" name="loader" :size="16" class="animate-spin" />
          {{ form.submitting.value ? 'جارٍ الحفظ…' : 'حفظ الإعدادات' }}
        </button>
      </div>
    </form>
  </div>
</template>

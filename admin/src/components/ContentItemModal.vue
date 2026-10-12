<script setup>
/**
 * الأسئلة الشائعة والشهادات والشركاء — نافذة واحدة لثلاثتها.
 *
 * ثلاثة جداول بثلاث قواعد، وبنيةٌ واحدة: عنصرٌ له ترتيب وحالةُ نشر. وثلاث
 * نوافذ متطابقة تفترق مع أول تعديل — فيُضاف حقل «الترتيب» في واحدة ويُنسى
 * في اثنتين.
 *
 * والمخطّط هنا مطابق لـ`ContentController::rulesFor` عمداً: حقلٌ زائد هنا
 * يُرفَض بـ٤٢٢، وحقلٌ ناقص يعني بياناتٍ لا سبيل لإدخالها.
 */
import { computed, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  type: { type: String, required: true },
  item: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const SCHEMA = {
  faqs: {
    title: 'سؤال شائع',
    publishKey: 'is_published',
    fields: [
      { key: 'question', label: 'السؤال', required: true },
      { key: 'answer', label: 'الإجابة', type: 'textarea', rows: 5, required: true },
      { key: 'category', label: 'التصنيف', placeholder: 'الكفالة' },
    ],
  },
  testimonials: {
    title: 'شهادة كافل',
    publishKey: 'is_published',
    fields: [
      { key: 'author_name', label: 'الاسم', required: true },
      { key: 'role', label: 'الصفة', placeholder: 'كافل منذ ٣ سنوات' },
      {
        key: 'quote', label: 'الشهادة', type: 'textarea', rows: 5, required: true,
        hint: 'بصيغتها كما قالها، فلا تُعاد صياغتها لتبدو أجمل.',
      },
    ],
  },
  partners: {
    title: 'شريك',
    publishKey: 'is_active',
    fields: [
      { key: 'name', label: 'الاسم', required: true },
      { key: 'icon', label: 'أيقونة Font Awesome', placeholder: 'fa-solid fa-building-columns' },
      { key: 'url', label: 'الرابط', type: 'url', placeholder: 'https://example.org' },
    ],
  },
}

const schema = computed(() => SCHEMA[props.type] ?? SCHEMA.faqs)
const editing = computed(() => Boolean(props.item?.id))

const blank = computed(() => ({
  ...Object.fromEntries(schema.value.fields.map((f) => [f.key, ''])),
  sort_order: 0,
  [schema.value.publishKey]: true,
}))

const form = useForm(blank.value, (values) =>
  editing.value
    ? admin.content.update(props.type, props.item.id, values)
    : admin.content.create(props.type, values),
)

watch(
  () => [props.open, props.type],
  ([isOpen]) => {
    if (!isOpen) return

    const base = blank.value

    form.setValues(
      editing.value
        ? Object.fromEntries(
          Object.keys(base).map((key) => [
            key,
            props.item[key] ?? (typeof base[key] === 'boolean' ? base[key] : ''),
          ]),
        )
        : base,
    )
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
    :title="editing ? `تحرير ${schema.title}` : `${schema.title} جديد`"
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <FormField
      v-for="(f, index) in schema.fields" :key="f.key"
      v-model="form.values[f.key]"
      :label="f.label" :type="f.type ?? 'text'" :rows="f.rows ?? 4"
      :required="f.required ?? false" :placeholder="f.placeholder ?? ''"
      :hint="f.hint ?? ''" :autofocus="index === 0"
      :error="form.errors.value[f.key]" @update:model-value="form.touch(f.key)"
    />

    <div class="grid sm:grid-cols-2 gap-4 items-start">
      <FormField
        v-model="form.values.sort_order" label="الترتيب" type="number"
        :min="0" :step="1"
        hint="الأصغر أولاً."
        :error="form.errors.value.sort_order" @update:model-value="form.touch('sort_order')"
      />

      <div class="sm:pt-8">
        <FormField
          v-model="form.values[schema.publishKey]"
          :label="type === 'partners' ? 'ظاهر على الموقع' : 'منشور'"
          type="checkbox"
          :error="form.errors.value[schema.publishKey]"
          @update:model-value="form.touch(schema.publishKey)"
        />
      </div>
    </div>
  </FormModal>
</template>

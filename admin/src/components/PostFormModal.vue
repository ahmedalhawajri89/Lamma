<script setup>
/**
 * كتابة مقال وتحريره.
 *
 * المدونة كانت تُنشر من البذرة وحدها: ستة مقالات كتبها من كتب السكربت، ولا
 * سبيل لكتابة سابعٍ إلا بتحرير قاعدة البيانات.
 *
 * والغلاف يُرفع عند الإنشاء وحده — نقطة التعديل في الخادم لا تقبل ملفاً.
 * فلا نعرض حقلاً عند التحرير يوحي بأن الغلاف يتغيّر من هنا.
 */
import { computed, ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import BaseIcon from './BaseIcon.vue'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  post: { type: Object, default: null },
  categories: { type: Array, default: () => [] },

  /** حالات المقال من الـ enum في الخادم: `[{ value, label }]` */
  statuses: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const editing = computed(() => Boolean(props.post?.id))

const cover = ref(null)
const coverInput = ref(null)

const BLANK = {
  title: '',
  excerpt: '',
  body: '',
  category_id: '',
  read_minutes: null,
  status: 'draft',
  published_at: '',
  is_featured: false,
}

const form = useForm(BLANK, (values) =>
  editing.value
    ? admin.posts.update(props.post.id, values)
    : admin.posts.create(values, cover.value),
)

/**
 * الحالات من الخادم لا من قائمة هنا: كانت القائمة تعرض «مؤرشف»، والخادم لا
 * يعرفها، فيُرفض كل حفظ يختارها. والمجدول يحتاج تاريخ نشر لاحقاً، فلا يُعرض.
 */
const STATUSES = computed(() =>
  (props.statuses.length ? props.statuses : [{ value: 'draft', label: 'مسوّدة' }, { value: 'published', label: 'منشور' }])
    .filter((st) => st.value !== 'scheduled'),
)

const categoryOptions = computed(() =>
  props.categories.map((c) => ({ value: c.id, label: c.name })),
)

/**
 * زمن القراءة يُقترح من طول النصّ ويبقى قابلاً للتعديل.
 *
 * رقمٌ يُكتب باليد يكذب بصمت أول تحرير للمقال — والقارئ يقرأ «٦ دقائق»
 * فوق مقال من فقرتين. ‏٢٠٠ كلمة في الدقيقة، وهو ما تحسبه البذرة أيضاً.
 */
const suggestedMinutes = computed(() => {
  const words = String(form.values.body ?? '').trim().split(/\s+/).filter(Boolean).length
  return words ? Math.max(1, Math.ceil(words / 200)) : null
})

watch(suggestedMinutes, (minutes) => {
  // لا نطمس رقماً كتبه المحرّر عمداً — نملأ الفارغ وحده
  if (minutes && (form.values.read_minutes === null || form.values.read_minutes === '')) {
    form.values.read_minutes = minutes
  }
})

function onCover(event) {
  cover.value = event.target.files?.[0] ?? null
  form.touch('cover')
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return

    cover.value = null
    if (coverInput.value) coverInput.value.value = ''

    if (editing.value) {
      const p = props.post
      form.setValues({
        title: p.title ?? '',
        excerpt: p.excerpt ?? '',
        body: p.body ?? '',
        category_id: p.category_id ?? '',
        read_minutes: p.readMin ?? null,
        status: p.status ?? 'draft',
        published_at: p.date ?? '',
        is_featured: Boolean(p.featured),
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
    :title="editing ? 'تحرير المقال' : 'مقال جديد'"
    :subtitle="editing ? post.slug : 'يُحفظ مسوّدةً ما لم تختر «منشور».'"
    :busy="form.submitting.value" :dirty="form.dirty.value"
    @close="emit('close')" @submit="save"
  >
    <FormField
      v-model="form.values.title" label="العنوان" required autofocus
      :error="form.errors.value.title" @update:model-value="form.touch('title')"
    />

    <FormField
      v-model="form.values.excerpt" label="المقتطف" type="textarea" :rows="2"
      hint="يظهر على البطاقة في المدونة وفي نتائج البحث."
      :error="form.errors.value.excerpt" @update:model-value="form.touch('excerpt')"
    />

    <FormField
      v-model="form.values.body" label="النصّ" type="textarea" :rows="10"
      hint="افصل الفقرات بسطر فارغ، فهكذا تُعرض على صفحة المقال."
      :error="form.errors.value.body" @update:model-value="form.touch('body')"
    />

    <div class="grid sm:grid-cols-2 gap-4">
      <FormField
        v-model="form.values.category_id" label="التصنيف"
        :options="categoryOptions" placeholder="بلا تصنيف"
        :error="form.errors.value.category_id" @update:model-value="form.touch('category_id')"
      />
      <FormField
        v-model="form.values.read_minutes" label="زمن القراءة (دقائق)" type="number"
        :min="1" :max="60" :step="1"
        :hint="suggestedMinutes ? `النصّ الحالي ≈ ${suggestedMinutes} دقيقة` : 'يُحسب من طول النصّ.'"
        :error="form.errors.value.read_minutes" @update:model-value="form.touch('read_minutes')"
      />

      <FormField
        v-model="form.values.status" label="الحالة" required
        :options="STATUSES"
        :error="form.errors.value.status" @update:model-value="form.touch('status')"
      />
      <FormField
        v-model="form.values.published_at" label="تاريخ النشر" type="date"
        hint="اتركه فارغاً ليكون تاريخ اليوم."
        :error="form.errors.value.published_at" @update:model-value="form.touch('published_at')"
      />
    </div>

    <!-- الغلاف عند الإنشاء وحده: نقطة التعديل في الخادم لا تقبل ملفاً -->
    <div v-if="!editing">
      <label class="block text-sm font-medium mb-1.5" for="coverFile">صورة الغلاف</label>
      <input
        id="coverFile" ref="coverInput" class="input" type="file"
        accept="image/jpeg,image/png,image/webp"
        aria-describedby="coverHelp" @change="onCover"
      >
      <p id="coverHelp" class="text-xs text-sand-500 mt-1">
        بنسبة ١٦:٩ تقريباً. مقالٌ بلا غلاف يُنشر، وتبقى بطاقته بلا صورة.
      </p>
      <p v-if="form.errors.value.cover" class="text-xs text-rose-700 mt-1" role="alert">
        {{ form.errors.value.cover[0] }}
      </p>
    </div>

    <FormField
      v-model="form.values.is_featured" label="مميّز يتصدّر المدونة" type="checkbox"
      :error="form.errors.value.is_featured" @update:model-value="form.touch('is_featured')"
    />

    <template #actions>
      <button class="btn btn-outline" type="button" :disabled="form.submitting.value" @click="emit('close')">
        إلغاء
      </button>
      <button class="btn btn-primary" type="submit" :disabled="form.submitting.value">
        <BaseIcon v-if="form.submitting.value" name="loader" :size="15" class="animate-spin" />
        {{ form.submitting.value ? 'جارٍ الحفظ…' : 'حفظ' }}
      </button>
    </template>
  </FormModal>
</template>

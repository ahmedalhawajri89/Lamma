<script setup>
import { onMounted, ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { dateTime, relative } from '@/utils/format'

/**
 * سجلّ سجلٍّ واحد: من فعل ماذا ومتى، بالقيمة قبل التعديل وبعده.
 * يقرأ سجل النشاط نفسه مفلتراً بنوع السجل ورقمه، فلا مصدر ثانٍ يختلف عنه.
 */
const props = defineProps({
  subjectType: { type: String, required: true },
  subjectId: { type: Number, required: true },
  emptyText: { type: String, default: 'لا تغييرات مسجّلة بعد.' },
})

const items = ref(null)
const loading = ref(false)
const failed = ref(false)

async function load() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await admin.activity({ subject_type: props.subjectType, subject_id: props.subjectId, per_page: 50 })
    items.value = data.data ?? []
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => props.subjectId, load)
defineExpose({ reload: load })
</script>

<template>
  <div v-if="loading && !items" class="space-y-3">
    <div v-for="n in 3" :key="n" class="h-10 rounded bg-sand-100 animate-pulse" />
  </div>

  <div v-else-if="failed" class="text-center py-6">
    <p class="text-sm text-sand-500 mb-3">تعذّر تحميل السجل.</p>
    <button class="btn btn-outline" type="button" @click="load">إعادة المحاولة</button>
  </div>

  <ol v-else-if="items?.length" data-history class="relative border-s border-sand-200 ms-1.5 space-y-5">
    <li v-for="a in items" :key="a.id" class="ps-4 relative">
      <span class="absolute -start-[5px] top-1.5 size-2.5 rounded-full bg-sand-400" />
      <p class="text-sm"><span class="font-medium">{{ a.actor }}</span> {{ a.action }}</p>
      <p class="text-xs text-sand-500" :title="dateTime(a.created_at)">{{ relative(a.created_at) }}</p>
      <ul v-if="a.changes?.length" class="mt-1.5 space-y-0.5 text-[0.8rem]">
        <li v-for="c in a.changes" :key="c.field" class="flex flex-wrap items-baseline gap-x-2">
          <span class="text-sand-500">{{ c.label }}:</span>
          <template v-if="c.before !== null && c.after !== null">
            <span class="text-sand-400 line-through">{{ c.before }}</span>
            <span class="text-sand-400">←</span>
            <span class="text-sand-900">{{ c.after }}</span>
          </template>
          <span v-else-if="c.after !== null" class="text-sand-900">{{ c.after }}</span>
          <span v-else class="text-sand-500">كان: {{ c.before }}</span>
        </li>
      </ul>
      <p v-if="a.note" class="mt-1.5 text-[0.8rem] text-sand-700 bg-sand-50 rounded-lg px-3 py-1.5">{{ a.note }}</p>
    </li>
  </ol>

  <p v-else class="text-sm text-sand-500 py-4">{{ emptyText }}</p>
</template>

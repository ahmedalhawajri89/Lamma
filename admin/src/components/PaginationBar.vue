<script setup>
import { computed } from 'vue'
import BaseIcon from './BaseIcon.vue'
import { num } from '@/utils/format'

const props = defineProps({
  meta: { type: Object, required: true },
})

const page = defineModel({ type: Number, required: true })

/** نافذة من خمس صفحات حول الحالية — عشرون زر صفحة تشوّش ولا تفيد. */
const pages = computed(() => {
  const last = props.meta.last_page ?? 1
  const current = props.meta.current_page ?? 1
  const start = Math.max(1, Math.min(current - 2, last - 4))
  const end = Math.min(last, start + 4)

  return Array.from({ length: Math.max(0, end - start + 1) }, (_, i) => start + i)
})
</script>

<template>
  <nav
    v-if="(meta.last_page ?? 1) > 1"
    class="flex items-center justify-between gap-3 flex-wrap pt-4"
    aria-label="تصفّح النتائج"
  >
    <p class="text-xs text-sand-600">
      صفحة <span class="num">{{ meta.current_page }}</span>
      من <span class="num">{{ meta.last_page }}</span>
      · <span class="num">{{ num(meta.total) }}</span> نتيجة
    </p>

    <div class="flex items-center gap-1">
      <button
        class="btn btn-outline !px-2" type="button"
        :disabled="meta.current_page <= 1"
        aria-label="الصفحة السابقة"
        @click="page = meta.current_page - 1"
      >
        <BaseIcon name="chevron" :size="16" />
      </button>

      <button
        v-for="p in pages" :key="p" type="button"
        class="btn !px-3"
        :class="p === meta.current_page ? 'btn-primary' : 'btn-ghost'"
        :aria-current="p === meta.current_page ? 'page' : undefined"
        @click="page = p"
      >
        <span class="num">{{ p }}</span>
      </button>

      <button
        class="btn btn-outline !px-2" type="button"
        :disabled="meta.current_page >= meta.last_page"
        aria-label="الصفحة التالية"
        @click="page = meta.current_page + 1"
      >
        <BaseIcon name="chevron" :size="16" style="transform: scaleX(-1)" />
      </button>
    </div>
  </nav>
</template>

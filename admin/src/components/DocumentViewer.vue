<script setup>
/**
 * عارض المستند داخل اللوحة.
 *
 * يجلب الملف بجلسة المستخدم (blob) ويعرضه: PDF في إطار، والصورة بتكبير
 * وتدوير، لأن أغلب الشهادات تُصوَّر بالجوال مائلةً أو صغيرة الخط. الردّ
 * المتأخر لمستندٍ سابق لا يُعرض فوق الحالي.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import BaseIcon from './BaseIcon.vue'

const props = defineProps({
  document: { type: Object, default: null },
})

const url = ref(null)
const loading = ref(false)
const failed = ref(false)
const zoom = ref(1)
const rotation = ref(0)

const isImage = computed(() => props.document?.mime?.startsWith('image/'))

function revoke() {
  if (url.value) URL.revokeObjectURL(url.value)
  url.value = null
}

async function load(doc) {
  revoke()
  failed.value = false
  zoom.value = 1
  rotation.value = 0
  if (!doc) return

  loading.value = true
  try {
    const { data } = await admin.documents.preview(doc.id)
    if (props.document?.id !== doc.id) return
    url.value = URL.createObjectURL(data)
  } catch {
    if (props.document?.id === doc.id) failed.value = true
  } finally {
    if (props.document?.id === doc.id) loading.value = false
  }
}

watch(() => props.document?.id, () => load(props.document), { immediate: true })
onBeforeUnmount(revoke)

const zoomBy = (step) => { zoom.value = Math.min(4, Math.max(0.5, +(zoom.value + step).toFixed(2))) }
const rotate = () => { rotation.value = (rotation.value + 90) % 360 }
const reset = () => { zoom.value = 1; rotation.value = 0 }

defineExpose({ zoomBy, rotate, reset })
</script>

<template>
  <div class="relative h-full w-full bg-sand-100 overflow-hidden">
    <div v-if="loading" class="absolute inset-0 grid place-items-center text-sm text-sand-500">
      <span class="flex items-center gap-2"><BaseIcon name="loader" :size="16" class="animate-spin" /> جارٍ فتح المستند…</span>
    </div>

    <div v-else-if="failed" class="absolute inset-0 grid place-items-center p-6 text-center">
      <div>
        <p class="text-sm text-sand-600 mb-3">تعذّر عرض المستند هنا.</p>
        <a class="btn btn-outline" :href="admin.documents.downloadUrl(document.id)">
          <BaseIcon name="download" :size="15" /> نزّله بدلاً من ذلك
        </a>
      </div>
    </div>

    <!-- الصورة: تكبير وتدوير، والتمرير لما يتجاوز الإطار -->
    <template v-else-if="url && isImage">
      <div class="absolute inset-0 overflow-auto grid place-items-center p-6">
        <img
          :src="url" :alt="document.type_label"
          class="max-w-full max-h-full object-contain shadow-sm transition-transform duration-150 bg-white"
          :style="{ transform: `scale(${zoom}) rotate(${rotation}deg)` }"
        />
      </div>
      <div class="absolute bottom-3 start-1/2 -translate-x-1/2 rtl:translate-x-1/2 flex items-center gap-0.5 rounded-full bg-sand-900/80 text-white px-1.5 py-1 text-xs" role="toolbar" aria-label="أدوات الصورة">
        <button type="button" class="size-7 grid place-items-center rounded-full hover:bg-white/15" aria-label="تصغير" @click="zoomBy(-0.25)">−</button>
        <button type="button" class="px-2 num rounded-full hover:bg-white/15 h-7" aria-label="الحجم الأصلي" @click="reset">{{ Math.round(zoom * 100) }}%</button>
        <button type="button" class="size-7 grid place-items-center rounded-full hover:bg-white/15" aria-label="تكبير" @click="zoomBy(0.25)">+</button>
        <span class="w-px h-4 bg-white/25 mx-1" />
        <button type="button" class="size-7 grid place-items-center rounded-full hover:bg-white/15" aria-label="تدوير" @click="rotate">
          <BaseIcon name="undo" :size="14" />
        </button>
      </div>
    </template>

    <iframe v-else-if="url" :src="url" :title="document.type_label" class="absolute inset-0 w-full h-full border-0 bg-white" />
  </div>
</template>

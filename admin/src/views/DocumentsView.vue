<script setup>
/**
 * طابور التحقق من المستندات.
 *
 * التوثيق يشترط مستندات مُتحقَّقاً منها، والتحقق كان يجري داخل ملف كل
 * يتيم على حدة: يفتح الموظف الملف، يبحث عن المستند، يتحقق، ثم يعود.
 * هنا كل ما ينتظر في قائمة واحدة، الأقدم أولاً، والقرار من الصف نفسه.
 * والملف الكامل على بُعد نقرة لمن أراد السياق.
 */
import { ref } from 'vue'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useUiStore } from '@/stores/ui'
import { useQueueStore } from '@/stores/queue'
import { useAuthStore } from '@/stores/auth'
import { date, relative } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import InlineNote from '@/components/InlineNote.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const ui = useUiStore()
const queue = useQueueStore()
const auth = useAuthStore()
const { items, meta, loading, filters, load } = useResource(admin.documents.queue, {})

const busyId = ref(null)

const size = (bytes) => (bytes >= 1048576
  ? `${(bytes / 1048576).toFixed(1)} م.ب`
  : `${Math.max(1, Math.round(bytes / 1024))} ك.ب`)

async function verify(doc) {
  busyId.value = doc.id

  try {
    await admin.documents.verify(doc.id)
    ui.success(`تُحقِّق من ${doc.type_label} لـ ${doc.orphan.name}.`)

    // يخرج من الطابور مكانه، بلا انتظار إعادة الجلب
    items.value = items.value.filter((d) => d.id !== doc.id)
    meta.value = { ...meta.value, total: Math.max(0, (meta.value.total ?? 1) - 1) }
    queue.refresh(true)
    if (!items.value.length) load()
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    busyId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="التحقق من المستندات"
      :subtitle="meta.total ? `${meta.total} مستند بانتظار التحقق` : 'لا شيء بانتظار التحقق'"
    />

    <InlineNote>
      افتح المستند وطابقه مع ملف الطفل قبل التحقق منه. المستند المُتحقَّق منه يُحتسب في توثيق الملف ولا يُحذف بعدها.
    </InlineNote>

    <div v-if="loading" class="card divide-y divide-sand-100">
      <div v-for="n in 4" :key="n" class="h-[4.5rem] animate-pulse bg-sand-50" />
    </div>

    <ul v-else-if="items.length" class="card divide-y divide-sand-100" aria-label="مستندات بانتظار التحقق">
      <li v-for="d in items" :key="d.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
        <span class="size-10 shrink-0 grid place-items-center rounded-lg bg-clay-50 text-clay-700">
          <BaseIcon name="file" :size="18" />
        </span>

        <div class="flex-1 min-w-[12rem]">
          <p class="font-medium text-sand-900">
            {{ d.type_label }}
            <span class="text-sand-400 font-normal"> · </span>
            <RouterLink :to="`/orphans/${d.orphan.id}`" class="text-clay-700 hover:underline font-normal">
              {{ d.orphan.name }}
            </RouterLink>
            <span class="ms-1.5 text-xs text-sand-500 font-normal num">{{ d.orphan.reference_code }}</span>
          </p>
          <p class="text-xs text-sand-500 mt-0.5">
            <span class="num" dir="ltr">{{ d.original_name }}</span>
            · <span class="num">{{ size(d.size_bytes) }}</span>
            · رفعه {{ d.uploader ?? 'غير محدد' }}
            · <time :datetime="d.created_at" :title="date(d.created_at)">{{ relative(d.created_at) }}</time>
          </p>
        </div>

        <StatusBadge :label="d.orphan.status_label" :value="d.orphan.status" />

        <div class="flex items-center gap-2 ms-auto">
          <a class="btn btn-outline !py-1.5 text-sm" :href="d.download_url" target="_blank" rel="noopener">
            <BaseIcon name="eye" :size="15" />
            فتح
          </a>
          <span v-if="d.uploaded_by === auth.user?.id" class="text-xs text-sand-500">رفعته أنت، فيتحقق منه غيرك</span>
          <button
            v-else
            class="btn btn-primary !py-1.5 text-sm" type="button"
            :disabled="busyId === d.id" @click="verify(d)"
          >
            <BaseIcon :name="busyId === d.id ? 'loader' : 'check'" :size="15" :class="{ 'animate-spin': busyId === d.id }" />
            تحقّقت منه
          </button>
        </div>
      </li>
    </ul>

    <div v-else class="card p-10 text-center">
      <span class="mx-auto mb-3 size-12 grid place-items-center rounded-full bg-clay-50 text-clay-700">
        <BaseIcon name="check" :size="22" />
      </span>
      <p class="font-medium text-sand-900">لا مستندات بانتظار التحقق</p>
      <p class="text-sm text-sand-500 mt-1">كل ما رُفع تُحقِّق منه. المستندات الجديدة تظهر هنا فور رفعها.</p>
    </div>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

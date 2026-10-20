<script setup>
/**
 * «وصل جديد»: شريطٌ عائم حين يصل إلى الشاشة المفتوحة ما لم يكن فيها.
 *
 * أعداد «بانتظارك» تتحدّث وحدها كل نصف دقيقة (stores/queue)، أما الجدول
 * المفتوح فلا: كانت الرسالة الجديدة ترفع رقم الشريط الجانبي وتبقى خارج
 * القائمة التي أمام المراجع حتى يغيّر فلتراً. هنا يقارن الشريط عدد مهام
 * هذه الشاشة بما كان حين فُتحت، فإن زاد عرض «وصل N جديد · اعرض»، والضغط
 * يعيد جلب القائمة في مكانها بفلاترها وصفحتها.
 *
 * والنقصان لا يُعدّ: الموظف أنجز شيئاً بنفسه، فيتبعه الأساس نزولاً.
 */
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useQueueStore } from '@/stores/queue'
import BaseIcon from './BaseIcon.vue'

const props = defineProps({
  /** مسار الشاشة كما في مهام «بانتظارك»؛ الافتراضي مسار الصفحة الحالية */
  path: { type: String, default: null },
})
const emit = defineEmits(['refresh'])

const route = useRoute()
const queue = useQueueStore()

const key = computed(() => props.path ?? route.path)
const count = computed(() => queue.countsByPath[key.value] ?? 0)

/** العدد حين رأى الموظف القائمة آخر مرة: لا يُضبط قبل أول جلب للمهام */
const baseline = ref(null)
watch([count, () => queue.loaded], ([n, loaded]) => {
  if (!loaded) return
  if (baseline.value === null || n < baseline.value) baseline.value = n
}, { immediate: true })

// ما زاد بعد إجراء الموظف نفسه (مسوّدة أنشأها) عملُه لا وارد
watch(() => queue.ownChange, () => { baseline.value = count.value })

// تغيير الفلاتر يعيد جلب القائمة أصلاً: ما وصل صار فيها
watch(() => route.fullPath, () => { if (queue.loaded) baseline.value = count.value })

const fresh = computed(() => (baseline.value === null ? 0 : Math.max(0, count.value - baseline.value)))

function show() {
  baseline.value = count.value
  emit('refresh')
}
function dismiss() { baseline.value = count.value }
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-from-class="opacity-0 -translate-y-2" leave-to-class="opacity-0 -translate-y-2"
      enter-active-class="transition duration-200" leave-active-class="transition duration-150"
    >
      <div
        v-if="fresh > 0" role="status"
        class="fixed top-4 left-1/2 -translate-x-1/2 z-30 flex items-center gap-1 rounded-full bg-[#064e3b] text-white ps-4 pe-1.5 py-1.5 shadow-lg"
      >
        <span class="size-2 rounded-full bg-[#D5A85C] me-1.5" aria-hidden="true" />
        <!-- بلا اسم معدود: «وصل 2 صرف» لا تستقيم عربيةً، والعدد في شارته -->
        <span class="text-sm">وصل جديد إلى هذه القائمة</span>
        <span class="num text-xs font-semibold rounded-full bg-white/15 px-2 py-0.5 ms-1.5">{{ fresh }}</span>
        <button type="button" class="ms-2 rounded-full bg-white text-[#064e3b] text-sm font-semibold px-3 py-1 hover:bg-white/90" @click="show">اعرض</button>
        <button type="button" class="rounded-full p-1.5 text-white/70 hover:text-white" aria-label="تجاهل" @click="dismiss">
          <BaseIcon name="close" :size="14" />
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

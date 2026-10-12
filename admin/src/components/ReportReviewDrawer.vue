<script setup>
/**
 * درج مراجعة التقرير.
 *
 * كان الاعتماد زرّاً في صف الجدول: يُعتمد التقرير ويُنشر للكافل دون أن
 * يقرأه أحد. هنا يُقرأ كاملاً أولاً، ثم يُتّخذ القرار من أسفله: اعتماد،
 * أو إرجاع لكاتبه بملاحظة، أو نشر. وبعد القرار «التالي» يفتح التقرير
 * التالي في القائمة، فيُراجع الطابور كله دون العودة إلى الجدول.
 *
 * درجٌ لا نافذة: الجدول يبقى ظاهراً خلفه، فلا يضيع مكان المراجع.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { confirmDialog, confirmState } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useQueueStore } from '@/stores/queue'
import { date, dateTime } from '@/utils/format'
import BaseIcon from './BaseIcon.vue'
import StatusBadge from './StatusBadge.vue'

const props = defineProps({
  /** التقرير المفتوح، و`null` يغلق الدرج */
  reportId: { type: [Number, String], default: null },

  /** معرّفات الصفوف الظاهرة بترتيبها، لزرّ «التالي» */
  order: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'open', 'changed', 'edit'])

const auth = useAuthStore()
const ui = useUiStore()
const queue = useQueueStore()

const report = ref(null)
const loading = ref(false)
const busy = ref(false)
const panel = ref(null)
let opener = null

const NOTES = [
  { key: 'academic_note', label: 'التعليم' },
  { key: 'health_note', label: 'الصحة' },
  { key: 'psychosocial_note', label: 'الحالة النفسية والاجتماعية' },
]

const SPENDING = {
  living: 'المعيشة',
  education: 'التعليم',
  health: 'الصحة',
  psychosocial: 'الدعم النفسي',
  overhead: 'التشغيل',
}

const open = computed(() => props.reportId !== null && props.reportId !== undefined)

/**
 * التالي في القائمة. والتقرير الذي قُرّر فيه يخرج من القائمة المفلترة بعد
 * إعادة جلبها، فنتذكّر موضعه لحظة الفتح ونكمل من هناك.
 */
const position = ref(-1)

watch(() => [props.reportId, props.order], () => {
  const i = props.order.indexOf(Number(props.reportId))
  if (i >= 0) position.value = i
}, { immediate: true })

const nextId = computed(() => {
  const current = Number(props.reportId)
  const i = props.order.indexOf(current)
  const from = i >= 0 ? i + 1 : position.value
  return props.order.slice(Math.max(0, from)).find((id) => id !== current) ?? null
})

const spending = computed(() => Object.entries(report.value?.spending_breakdown ?? {})
  .map(([key, value]) => ({ key, label: SPENDING[key] ?? key, value: Number(value) }))
  .filter((r) => r.value > 0))

const isAuthor = computed(() => report.value?.supervisor_id === auth.user?.id)

/** ما يملكه هذا الموظف على هذا التقرير في حالته الآن */
const actions = computed(() => {
  const r = report.value
  if (!r) return {}

  return {
    submit: r.status === 'draft' && isAuthor.value,
    edit: r.status === 'draft' && isAuthor.value,
    review: r.status === 'submitted' && auth.can('reports.approve') && !isAuthor.value,
    publish: r.status === 'approved' && auth.can('reports.publish'),
  }
})

const ownSubmitted = computed(() => report.value?.status === 'submitted' && isAuthor.value)

async function load(id) {
  loading.value = true
  report.value = null

  try {
    const { data } = await admin.reports.show(id)
    report.value = data.data
  } catch (e) {
    ui.error(e.userMessage)
    emit('close')
  } finally {
    loading.value = false
  }
}

async function run(fn, message) {
  busy.value = true

  try {
    const { data } = await fn()
    ui.success(data?.message ?? message)
    report.value = { ...report.value, ...data.data }
    emit('changed')
    queue.refresh?.(true)
  } catch (e) {
    ui.error(e.fieldErrors?.note?.[0] ?? e.fieldErrors?.status?.[0] ?? e.userMessage)
  } finally {
    busy.value = false
  }
}

const approve = () => run(() => admin.reports.approve(report.value.id), 'اعتُمد التقرير.')
const publish = () => run(() => admin.reports.publish(report.value.id), 'نُشر التقرير للكفلاء.')
const submit = () => run(() => admin.reports.submit(report.value.id), 'أُرسل التقرير للمراجعة.')

async function sendBack() {
  const r = await confirmDialog({
    title: 'إرجاع التقرير للتعديل',
    message: `يعود إلى ${report.value.supervisor ?? 'كاتبه'} مسوّدةً مع ملاحظتك، فيعدّله ثم يرسله من جديد.`,
    reason: 'required',
    reasonLabel: 'ما الذي يحتاج إلى تعديل؟',
    confirmLabel: 'إرجاع',
  })
  if (!r) return

  await run(() => admin.reports.sendBack(report.value.id, r.reason), 'أُرجع التقرير.')
}

function close() {
  if (!busy.value) emit('close')
}

function onKeydown(event) {
  // نافذة التأكيد فوق الدرج تملك Escape وTab ما دامت مفتوحة
  if (confirmState.open) return

  if (event.key === 'Escape') {
    event.preventDefault()
    close()
  }
}

watch(() => props.reportId, (id) => {
  if (id !== null && id !== undefined) load(id)
}, { immediate: true })

watch(open, async (isOpen) => {
  if (isOpen) {
    opener = document.activeElement
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    panel.value?.focus()
  } else {
    document.removeEventListener('keydown', onKeydown)
    opener?.focus?.()
    opener = null
  }
}, { immediate: true })

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="review-fade">
      <div v-if="open" class="fixed inset-0 z-40 bg-sand-900/30" @click="close" />
    </Transition>

    <Transition name="review-slide">
      <aside
        v-if="open"
        ref="panel"
        class="review fixed inset-y-0 end-0 z-40 w-full sm:w-[34rem] lg:w-[38rem] bg-sand-0 border-s border-sand-200 flex flex-col outline-none"
        role="dialog" aria-modal="true" aria-labelledby="review-title" tabindex="-1"
      >
        <!-- الرأس: من وأيّ فترة وأين وصل -->
        <header class="flex items-start gap-3 p-5 border-b border-sand-200">
          <img
            v-if="report?.orphan?.photo_url" :src="report.orphan.photo_url" alt=""
            class="size-12 rounded-xl object-cover shrink-0 bg-sand-100"
          />
          <span v-else class="size-12 rounded-xl shrink-0 grid place-items-center bg-clay-50 text-clay-700">
            <BaseIcon name="file" :size="20" />
          </span>

          <div class="flex-1 min-w-0">
            <p class="text-xs text-sand-500">
              <span v-if="report">{{ report.period_label }}</span>
              <span v-else>جارٍ التحميل…</span>
            </p>
            <h2 id="review-title" class="font-semibold text-sand-900 leading-snug mt-0.5">
              {{ report?.title ?? 'تقرير' }}
            </h2>
            <p v-if="report" class="text-sm text-sand-600 mt-1">
              <RouterLink :to="`/orphans/${report.orphan?.id}`" class="text-clay-700 hover:underline">
                {{ report.orphan?.name }}
              </RouterLink>
              <span class="text-sand-400"> · </span>
              <span class="num">{{ report.orphan?.reference_code }}</span>
            </p>
          </div>

          <button class="btn btn-ghost -m-1.5 p-1.5" type="button" aria-label="إغلاق" @click="close">
            <BaseIcon name="close" :size="18" />
          </button>
        </header>

        <div v-if="loading" class="flex-1 grid place-items-center text-sand-500">
          <BaseIcon name="loader" :size="22" class="animate-spin" />
        </div>

        <div v-else-if="report" class="flex-1 overflow-y-auto">
          <!-- سطر الحقائق -->
          <dl class="review__facts grid grid-cols-3 border-b border-sand-200 text-sm">
            <div class="p-4">
              <dt class="text-xs text-sand-500">الحالة</dt>
              <dd class="mt-1"><StatusBadge :label="report.status_label" :value="report.status" /></dd>
            </div>
            <div class="p-4 border-s border-sand-200">
              <dt class="text-xs text-sand-500">كتبه</dt>
              <dd class="mt-1 font-medium text-sand-900 truncate">{{ report.supervisor ?? 'غير محدد' }}</dd>
            </div>
            <div class="p-4 border-s border-sand-200">
              <dt class="text-xs text-sand-500">الزيارة</dt>
              <dd class="mt-1 font-medium text-sand-900">{{ date(report.visit_date) }}</dd>
            </div>
          </dl>

          <div class="p-5 space-y-6">
            <!-- ملاحظة المراجعة السابقة: ما طُلب، ليُتحقَّق هل عولج -->
            <div
              v-if="report.review_note"
              class="border-s-4 border-amber-500 bg-amber-50 rounded-e-lg px-4 py-3"
              role="note"
            >
              <p class="text-xs font-semibold text-amber-800">
                {{ report.returned_at ? 'أُرجع للتعديل' : 'ملاحظة المراجعة السابقة' }}
                <span v-if="report.returned_at" class="font-normal text-amber-700"> · {{ dateTime(report.returned_at) }}</span>
              </p>
              <p class="text-sm text-sand-800 mt-1 leading-relaxed whitespace-pre-line">{{ report.review_note }}</p>
            </div>

            <section>
              <h3 class="review__h">الملخص</h3>
              <p v-if="report.summary" class="text-sand-800 leading-loose whitespace-pre-line">{{ report.summary }}</p>
              <p v-else class="review__missing">لم يُكتب ملخص.</p>
            </section>

            <section v-for="n in NOTES" :key="n.key">
              <h3 class="review__h">{{ n.label }}</h3>
              <p v-if="report[n.key]" class="text-sand-800 leading-loose whitespace-pre-line">{{ report[n.key] }}</p>
              <p v-else class="review__missing">لا ملاحظة.</p>
            </section>

            <section v-if="report.media?.length">
              <h3 class="review__h">الصور المرفقة</h3>
              <ul class="grid grid-cols-3 gap-2">
                <li v-for="m in report.media" :key="m.id">
                  <a :href="m.url" target="_blank" rel="noopener" class="block">
                    <img :src="m.url" :alt="m.caption ?? ''" class="aspect-square w-full object-cover rounded-lg bg-sand-100" />
                  </a>
                </li>
              </ul>
            </section>

            <!-- الصرف: يُلتقط عند الاعتماد، فلا يظهر قبله -->
            <section>
              <h3 class="review__h">توزيع صرف الربع</h3>
              <ul v-if="spending.length" class="space-y-2">
                <li v-for="s in spending" :key="s.key" class="flex items-center gap-3 text-sm">
                  <span class="w-28 shrink-0 text-sand-700">{{ s.label }}</span>
                  <span class="flex-1 h-2 rounded-full bg-sand-100 overflow-hidden">
                    <span class="block h-full rounded-full bg-clay-600" :style="{ width: `${s.value}%` }" />
                  </span>
                  <span class="w-10 text-end num text-sand-900">{{ s.value }}%</span>
                </li>
              </ul>
              <p v-else class="review__missing">يُلتقط من سجلّ الصرف لحظة الاعتماد.</p>
            </section>
          </div>
        </div>

        <!-- القرار -->
        <footer v-if="report && !loading" class="border-t border-sand-200 p-4 flex flex-wrap items-center gap-2">
          <template v-if="actions.review">
            <button class="btn btn-primary" type="button" :disabled="busy" @click="approve">
              <BaseIcon name="check" :size="16" />
              اعتماد
            </button>
            <button class="btn btn-outline" type="button" :disabled="busy" @click="sendBack">
              <BaseIcon name="undo" :size="16" />
              إرجاع للتعديل
            </button>
          </template>

          <button v-else-if="actions.publish" class="btn btn-primary" type="button" :disabled="busy" @click="publish">
            <BaseIcon name="external" :size="16" />
            نشر للكافل
          </button>

          <template v-else-if="actions.submit">
            <button class="btn btn-primary" type="button" :disabled="busy" @click="submit">
              إرسال للمراجعة
            </button>
            <button class="btn btn-outline" type="button" :disabled="busy" @click="emit('edit', report)">
              <BaseIcon name="edit" :size="16" />
              تعديل
            </button>
          </template>

          <p v-else-if="ownSubmitted" class="text-sm text-sand-500">
            كتبتَ هذا التقرير، فيعتمده غيرك.
          </p>
          <p v-else-if="report.status === 'published'" class="text-sm text-sand-500">
            منشور<span v-if="report.published_at"> منذ {{ date(report.published_at) }}</span>، ويراه الكافل في لوحته.
          </p>

          <button
            v-if="nextId" class="btn btn-ghost ms-auto" type="button" :disabled="busy"
            @click="emit('open', nextId)"
          >
            التالي
            <BaseIcon name="chevron" :size="16" class="rotate-180" />
          </button>
        </footer>
      </aside>
    </Transition>
  </Teleport>
</template>

<style scoped>
.review__h {
  margin-bottom: 0.4rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-sand-500);
}

.review__missing {
  font-size: 0.875rem;
  color: var(--color-sand-400);
}

/* ينزلق الدرج من جهته مرة واحدة عند الفتح، ويخفت الغطاء خلفه */
.review-slide-enter-active,
.review-slide-leave-active {
  transition: transform 0.26s cubic-bezier(0.4, 0, 0.2, 1);
}

.review-slide-enter-from,
.review-slide-leave-to {
  transform: translateX(-100%);
}

.review-fade-enter-active,
.review-fade-leave-active {
  transition: opacity 0.2s ease;
}

.review-fade-enter-from,
.review-fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .review-slide-enter-active,
  .review-slide-leave-active,
  .review-fade-enter-active,
  .review-fade-leave-active {
    transition: none;
  }
}
</style>

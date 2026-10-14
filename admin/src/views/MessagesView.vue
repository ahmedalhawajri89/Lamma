<script setup>
/**
 * مراجعة الرسائل: مساحة عمل لا قائمة بطاقات.
 *
 * كل رسالة بين كافل وطفل تمرّ بمراجعة قبل وصولها، تطبيقاً لسياسة حماية
 * الطفل المعلنة. الطابور يميناً (الأقدم أولاً)، والرسالة يساراً في سياق
 * ما دار قبلها، مع ما فيها مما يستدعي النظر (رقم هاتف، طلب مال، سؤال عن
 * المسكن) مظلّلاً. القرار بالفأرة أو بلوحة المفاتيح، وينتقل وحده.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useQueueStore } from '@/stores/queue'
import { useUiStore } from '@/stores/ui'
import { dateTime, num, relative } from '@/utils/format'
import StatusTabs from '@/components/StatusTabs.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const route = useRoute()
const router = useRouter()
const queue = useQueueStore()
const ui = useUiStore()

const DEFAULTS = { status: 'pending_review', q: '', direction: '', flagged: false }
const { items, meta, loading, error, filters, load } = useResource(admin.messages.list, { ...DEFAULTS, per_page: 50 })

const STATUSES = [
  { value: 'pending_review', label: 'بانتظار المراجعة' },
  { value: 'approved', label: 'معتمدة' },
  { value: 'delivered', label: 'وصلت للطفل' },
  { value: 'rejected', label: 'مرفوضة' },
]

/** أسباب الرفض الشائعة بنقرة؛ يقرؤها المرسل ليعدّل رسالته */
const REASONS = ['تحتوي وسيلة تواصل خارج المنصة', 'طلب مال أو هدايا', 'سؤال عن المسكن أو المدرسة', 'لا تناسب عمر الطفل', 'لغة غير لائقة']

/* ── الطابور والمختارة ───────────────────────────────────────────────── */

const order = computed(() => items.value.map((m) => m.id))
const current = computed(() => items.value.find((m) => m.id === Number(route.query.m)) ?? null)
const index = computed(() => order.value.indexOf(current.value?.id))

function select(id) {
  rejecting.value = false
  router.replace({ query: { ...route.query, m: id ?? undefined } })
}

const wide = window.matchMedia?.('(min-width: 1024px)')
watch(items, (list) => {
  if (!current.value && list.length && wide?.matches) select(list[0].id)
})

const go = (step) => {
  const id = order.value[index.value + step]
  if (id) select(id)
}

/* ── السياق: ما دار قبلها ─────────────────────────────────────────────── */

const thread = ref([])
const threadLoading = ref(false)

watch(() => current.value?.id, async (id) => {
  thread.value = []
  if (!id) return
  threadLoading.value = true
  try {
    const { data } = await admin.messages.thread(id)
    if (current.value?.id === id) thread.value = data.data ?? []
  } catch {
    // السياق إضافة؛ الرسالة نفسها معروضة على أي حال
  } finally {
    if (current.value?.id === id) threadLoading.value = false
  }
}, { immediate: true })

/** النص مقسوماً إلى أجزاء، والمطابقات للتنبيهات مظلّلة */
function segments(m) {
  const matches = (m.flags ?? []).flatMap((f) => f.matches).filter(Boolean)
  if (!matches.length) return [{ text: m.body, hit: false }]
  const escaped = matches.sort((a, b) => b.length - a.length).map((x) => x.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
  return m.body.split(new RegExp(`(${escaped.join('|')})`, 'g')).filter((t) => t !== '')
    .map((text) => ({ text, hit: matches.includes(text) }))
}

/* ── القرار ──────────────────────────────────────────────────────────── */

const busy = ref(false)
const rejecting = ref(false)
const reason = ref('')
const reasonInput = ref(null)
const reasonError = ref('')
const pending = computed(() => current.value?.status === 'pending_review')

watch(rejecting, async (on) => {
  reason.value = ''
  reasonError.value = ''
  if (on) { await nextTick(); reasonInput.value?.focus() }
})

function removeCurrent(m) {
  const i = order.value.indexOf(m.id)
  const next = order.value[i + 1] ?? order.value[i - 1] ?? null
  items.value = items.value.filter((x) => x.id !== m.id)
  const counts = { ...(meta.value.status_counts ?? {}) }
  counts[m.status] = Math.max(0, (counts[m.status] ?? 1) - 1)
  meta.value = { ...meta.value, status_counts: counts }
  select(next)
  queue.refresh(true)
  if (!items.value.length) load()
}

async function decide(decision) {
  const m = current.value
  if (!m || busy.value || !pending.value) return
  if (decision === 'reject' && reason.value.trim().length < 3) {
    reasonError.value = 'اكتب سبب الرفض، يقرؤه المرسل ليعدّل رسالته.'
    reasonInput.value?.focus()
    return
  }
  busy.value = true
  try {
    await admin.messages.review(m.id, { decision, reason: decision === 'reject' ? reason.value.trim() : null })
    ui.success(decision === 'approve' ? 'اعتُمدت الرسالة.' : 'رُفضت الرسالة، وسببها يصل المرسل.')
    rejecting.value = false
    removeCurrent(m)
  } catch (e) {
    ui.error(e.fieldErrors?.reason?.[0] ?? e.userMessage)
    if (e.status === 409) load()
  } finally {
    busy.value = false
  }
}

async function deliver() {
  const m = current.value
  if (!m || busy.value) return
  busy.value = true
  try {
    await admin.messages.deliver(m.id)
    ui.success('سُجّل وصول الرسالة للطفل.')
    removeCurrent(m)
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    busy.value = false
  }
}

/* ── لوحة المفاتيح: بموضع المفتاح، فتعمل بالعربية والإنجليزية ────────── */

function onKey(e) {
  if (e.ctrlKey || e.metaKey || e.altKey) return
  const t = e.target
  const typing = t instanceof HTMLElement && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName))
  if (document.querySelector('[role="dialog"][aria-modal="true"]')) return
  if (e.key === 'Escape' && rejecting.value) { e.preventDefault(); rejecting.value = false; return }
  if (typing || !current.value) return

  const action = {
    KeyA: () => decide('approve'),
    KeyR: () => { if (pending.value) rejecting.value = true },
    KeyJ: () => go(1),
    KeyK: () => go(-1),
  }[e.code]
  if (action) { e.preventDefault(); action() }
}
onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))

const fromLabel = (m) => (m.sender_type === 'sponsor' ? 'من الكافل' : 'من الطفل')
</script>

<template>
  <div class="flex flex-col lg:h-[calc(100dvh-4rem)]">
    <header class="flex flex-wrap items-end justify-between gap-3 mb-3">
      <div>
        <h1 class="text-2xl font-bold text-sand-900">الرسائل</h1>
        <p class="text-sm text-sand-500 mt-0.5">
          كل رسالة بين كافل وطفل تُراجَع قبل وصولها، حمايةً للطفل.
          <template v-if="meta.flagged_pending"> · <span class="text-rose-700 font-medium"><span class="num">{{ num(meta.flagged_pending) }}</span> فيها ما يستدعي النظر</span></template>
        </p>
      </div>
      <span class="hidden lg:flex items-center gap-1.5 text-xs text-sand-500">
        <kbd class="kbd">A</kbd> اعتماد <kbd class="kbd">R</kbd> رفض <kbd class="kbd">J</kbd><kbd class="kbd">K</kbd> تنقّل
      </span>
    </header>

    <StatusTabs
      v-model="filters.status" :options="STATUSES" :counts="meta.status_counts ?? {}"
      :total="meta.all_count ?? null" label="حالة الرسالة"
    />

    <div class="card flex-1 min-h-0 grid lg:grid-cols-[22rem_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)] overflow-hidden">
      <!-- ══ الطابور ══ -->
      <aside class="flex flex-col min-h-0 lg:border-e border-sand-200" aria-label="الرسائل">
        <div class="p-3 space-y-2 border-b border-sand-200">
          <label class="relative block">
            <span class="sr-only">ابحث باسم الطفل أو الكافل</span>
            <BaseIcon name="search" :size="15" class="absolute top-1/2 -translate-y-1/2 text-sand-400 pointer-events-none" style="inset-inline-start: 0.7rem" />
            <input v-model="filters.q" type="search" class="input ps-8 !py-1.5" placeholder="الطفل، الكافل، أو مرجع الكفالة…" />
          </label>
          <div class="flex flex-wrap gap-1.5">
            <button type="button" class="chip !py-1 !text-xs" :aria-pressed="filters.direction === 'sponsor'" @click="filters.direction = filters.direction === 'sponsor' ? '' : 'sponsor'">من الكافل</button>
            <button type="button" class="chip !py-1 !text-xs" :aria-pressed="filters.direction === 'orphan'" @click="filters.direction = filters.direction === 'orphan' ? '' : 'orphan'">من الطفل</button>
            <button type="button" class="chip !py-1 !text-xs" :aria-pressed="filters.flagged" @click="filters.flagged = !filters.flagged">
              <BaseIcon name="alert" :size="12" /> فيها تنبيه
            </button>
          </div>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto">
          <div v-if="loading && !items.length" class="p-3 space-y-2">
            <div v-for="n in 6" :key="n" class="h-14 rounded-lg bg-sand-100 animate-pulse" />
          </div>
          <div v-else-if="error" class="p-6 text-center" role="alert">
            <p class="text-sm text-sand-600">{{ error }}</p>
            <button class="btn btn-outline mt-3" type="button" @click="load">إعادة المحاولة</button>
          </div>
          <p v-else-if="!items.length" class="p-6 text-center text-sm text-sand-500">
            {{ filters.status === 'pending_review' ? 'لا رسائل بانتظار المراجعة.' : 'لا رسائل في هذا التصنيف.' }}
          </p>

          <ul v-else class="divide-y divide-sand-100">
            <li v-for="m in items" :key="m.id">
              <button
                type="button" class="w-full text-start px-3 py-2.5 border-s-2 transition-colors"
                :class="current?.id === m.id ? 'border-clay-600 bg-clay-50/70' : 'border-transparent hover:bg-sand-50'"
                :aria-current="current?.id === m.id ? 'true' : undefined"
                @click="select(m.id)"
              >
                <span class="flex items-center gap-2">
                  <span class="font-medium text-sm text-sand-900 truncate">{{ m.sponsorship?.orphan_name }}</span>
                  <span class="text-[0.7rem] text-sand-500">{{ fromLabel(m) }}</span>
                  <BaseIcon v-if="m.flags?.length" name="alert" :size="13" class="text-rose-600 ms-auto shrink-0" role="img" aria-label="فيها تنبيه" />
                  <span class="text-[0.7rem] text-sand-400 shrink-0" :class="m.flags?.length ? '' : 'ms-auto'">{{ relative(m.created_at) }}</span>
                </span>
                <span class="block text-xs text-sand-600 truncate mt-0.5">{{ m.body }}</span>
              </button>
            </li>
          </ul>
        </div>
      </aside>

      <!-- ══ الرسالة في سياقها ══ -->
      <section v-if="current" class="flex flex-col min-h-0 fixed inset-0 z-40 bg-sand-0 lg:static lg:z-auto" aria-labelledby="msgTitle">
        <div class="flex items-center gap-3 px-4 py-3 border-b border-sand-200">
          <button class="btn btn-ghost !p-1.5 lg:hidden" type="button" aria-label="رجوع إلى الطابور" @click="select(null)">
            <BaseIcon name="chevron" :size="18" />
          </button>
          <img v-if="current.sponsorship?.orphan_photo" :src="current.sponsorship.orphan_photo" alt="" class="size-9 rounded-full object-cover" width="36" height="36" />
          <span v-else class="size-9 rounded-full bg-clay-50 text-clay-700 grid place-items-center text-sm font-semibold">{{ current.sponsorship?.orphan_name?.charAt(0) }}</span>
          <div class="flex-1 min-w-0">
            <h2 id="msgTitle" class="font-semibold text-sand-900 truncate">
              <RouterLink :to="`/orphans/${current.sponsorship?.orphan_id}`" class="hover:text-clay-700">{{ current.sponsorship?.orphan_name }}</RouterLink>
              <span class="text-sand-400 font-normal"> و </span>{{ current.sponsor_name ?? 'الكافل' }}
            </h2>
            <p class="text-xs text-sand-500">
              <RouterLink :to="`/sponsorships?open=${current.sponsorship?.id}`" class="num hover:text-clay-700">{{ current.sponsorship?.reference_code }}</RouterLink>
              · {{ fromLabel(current) }} · {{ dateTime(current.created_at) }}
            </p>
          </div>
          <StatusBadge :label="current.status_label" :value="current.status" />
        </div>

        <!-- ما يستدعي النظر -->
        <div v-if="current.flags?.length" class="px-4 py-2.5 bg-rose-50 border-b border-rose-200 text-sm text-rose-900 flex flex-wrap items-center gap-2" role="status">
          <BaseIcon name="alert" :size="15" />
          <span class="font-medium">انتبه:</span>
          <span v-for="f in current.flags" :key="f.type" class="badge bg-white text-rose-800 border border-rose-200">{{ f.label }}</span>
        </div>

        <!-- المحادثة: ما قبلها خافتاً، والمراجَعة بارزة -->
        <ol class="flex-1 min-h-0 overflow-y-auto px-4 py-5 space-y-3 bg-sand-25" aria-label="سياق المحادثة">
          <li v-if="threadLoading && !thread.length" class="text-center text-xs text-sand-400">جارٍ تحميل السياق…</li>
          <li v-else-if="thread.length <= 1" class="text-center text-xs text-sand-400">أول رسالة في هذه الكفالة.</li>
          <li
            v-for="t in (thread.length ? thread : [current])" :key="t.id"
            class="flex" :class="t.sender_type === 'sponsor' ? 'justify-start' : 'justify-end'"
          >
            <div
              class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed"
              :class="[
                t.id === current.id ? 'ring-2 ring-clay-400 bg-sand-0 shadow-sm' : 'opacity-75',
                t.id !== current.id && t.sender_type === 'sponsor' ? 'bg-sand-0 border border-sand-200' : '',
                t.id !== current.id && t.sender_type !== 'sponsor' ? 'bg-clay-50' : '',
              ]"
            >
              <p class="text-[0.7rem] text-sand-500 mb-1">{{ fromLabel(t) }} · {{ relative(t.created_at) }}
                <template v-if="t.id !== current.id"> · {{ t.status_label }}</template>
              </p>
              <p class="whitespace-pre-line text-sand-900">
                <template v-for="(seg, i) in (t.id === current.id ? segments(current) : [{ text: t.body, hit: false }])" :key="i">
                  <!-- dir=auto يعزل اتجاه المظلَّل: رقمٌ بمسافات كان يظهر مقلوباً «456 123 0599» -->
                  <mark v-if="seg.hit" dir="auto" class="bg-rose-100 text-rose-900 rounded px-0.5">{{ seg.text }}</mark>
                  <template v-else>{{ seg.text }}</template>
                </template>
              </p>
              <p v-if="t.rejection_reason" class="text-xs text-rose-700 mt-1">رُفضت: {{ t.rejection_reason }}</p>
            </div>
          </li>
        </ol>

        <!-- القرار -->
        <div class="border-t border-sand-200 px-4 py-3">
          <form v-if="pending && rejecting" class="space-y-2.5" @submit.prevent="decide('reject')">
            <div class="flex flex-wrap gap-1.5">
              <button v-for="r in REASONS" :key="r" type="button" class="chip !py-1 !text-xs" :aria-pressed="reason === r" @click="reason = r; reasonError = ''">{{ r }}</button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="sr-only" for="msgReason">سبب الرفض</label>
              <input id="msgReason" ref="reasonInput" v-model="reason" class="input flex-1 min-w-48" maxlength="255" placeholder="سبب الرفض، يقرؤه المرسل…" :aria-invalid="Boolean(reasonError)" @input="reasonError = ''" />
              <button class="btn bg-rose-600 text-white hover:bg-rose-700" type="submit" :disabled="busy">ارفض الرسالة</button>
              <button class="btn btn-ghost" type="button" :disabled="busy" @click="rejecting = false">إلغاء <kbd class="kbd">Esc</kbd></button>
            </div>
            <p v-if="reasonError" class="text-xs text-rose-700" role="alert">{{ reasonError }}</p>
          </form>

          <div v-else class="flex flex-wrap items-center gap-2">
            <template v-if="pending">
              <button class="js-approve btn btn-primary" type="button" :disabled="busy" @click="decide('approve')">
                <BaseIcon :name="busy ? 'loader' : 'check'" :size="15" :class="{ 'animate-spin': busy }" /> اعتماد <kbd class="kbd !bg-white/15 !border-white/25 !text-white">A</kbd>
              </button>
              <button class="btn btn-outline text-rose-700" type="button" :disabled="busy" @click="rejecting = true">رفض <kbd class="kbd">R</kbd></button>
            </template>
            <button
              v-else-if="current.status === 'approved' && current.sender_type === 'sponsor'"
              class="btn btn-primary" type="button" :disabled="busy" @click="deliver"
            >
              <BaseIcon name="check" :size="15" /> وصلت للطفل
            </button>
            <p v-else class="text-sm text-sand-500">
              <template v-if="current.reviewer">راجعها {{ current.reviewer }} {{ relative(current.reviewed_at) }}.</template>
            </p>

            <span class="ms-auto flex items-center gap-1">
              <span class="hidden sm:inline text-xs text-sand-400 num me-1">{{ index + 1 }} / {{ order.length }}</span>
              <button class="btn btn-ghost" type="button" :disabled="index <= 0" @click="go(-1)"><BaseIcon name="chevron" :size="15" /> السابقة</button>
              <button class="btn btn-ghost" type="button" :disabled="index >= order.length - 1" @click="go(1)">التالية <BaseIcon name="chevron" :size="15" style="transform: scaleX(-1)" /></button>
            </span>
          </div>
        </div>
      </section>

      <section v-else class="hidden lg:grid place-items-center p-10 text-center">
        <div>
          <span class="mx-auto mb-3 size-12 grid place-items-center rounded-full bg-clay-50 text-clay-700"><BaseIcon name="check" :size="22" /></span>
          <p class="font-medium text-sand-900">{{ items.length ? 'اختر رسالة من الطابور' : 'لا رسائل هنا' }}</p>
          <p class="text-sm text-sand-500 mt-1">{{ items.length ? 'تُعرض في سياق ما دار قبلها.' : 'الرسائل الجديدة تظهر فور إرسالها.' }}</p>
        </div>
      </section>
    </div>
  </div>
</template>

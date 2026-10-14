<script setup>
/**
 * استفسارات الزوار: صندوق «تواصل معنا».
 *
 * الوعد المنشور «نردّ خلال 72 ساعة». القائمة يميناً بما يقترب من إخلافه،
 * والرسالة يساراً بردودها، والرد يُكتب هنا ويُرسل من عنوان المنظمة ويُحفظ،
 * لا بزرٍّ يفتح بريد الموظف الشخصي فيخرج الرد بلا أثر.
 */
import { computed, nextTick, ref, watch } from 'vue'
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

const DEFAULTS = { status: 'open', q: '', topic: '', overdue: false }
const { items, meta, loading, error, filters, load } = useResource(admin.contacts, { ...DEFAULTS, per_page: 50 })

const STATUSES = [
  { value: 'open', label: 'مفتوحة' },
  { value: 'new', label: 'جديدة' },
  { value: 'in_progress', label: 'قيد المعالجة' },
  { value: 'closed', label: 'مغلقة' },
]
const statusCounts = computed(() => ({ ...(meta.value.status_counts ?? {}), open: meta.value.open_count ?? 0 }))

/** مواضيع نموذج «تواصل معنا» كما في الموقع */
const TOPICS = {
  general: 'استفسار عام',
  sponsorship: 'سؤال عن الكفالة',
  finance: 'استفسار مالي',
  report: 'مشكلة في تقرير',
  partner: 'شراكة أو تطوّع',
  complaint: 'شكوى',
}

/** بدايات ردٍّ لكل موضوع: تُعدَّل قبل الإرسال، ولا تُرسل كما هي */
const TEMPLATES = {
  sponsorship: 'شكراً لاهتمامك بالكفالة. تبدأ الكفالة باختيار طفل من صفحة الأطفال، والدفع شهرياً أو كل ثلاثة أشهر أو سنوياً، وتصلك تقارير دورية عنه. ',
  finance: 'شكراً لسؤالك. تُنشر أرقام الصرف كاملة في صفحة الشفافية، بنسبة ما يصل للطفل وما يُصرف على التشغيل. ',
  report: 'نعتذر عن المشكلة في التقرير. تابعناها مع المشرف الميداني، ',
  partner: 'شكراً لرغبتك في الشراكة أو التطوّع. ',
  complaint: 'نعتذر عمّا حدث، ونشكرك على إخبارنا. ',
  general: 'شكراً لتواصلك مع كفالة. ',
}

/* ── القائمة والمختارة ───────────────────────────────────────────────── */

const order = computed(() => items.value.map((m) => m.id))
const selectedId = computed(() => (route.query.c ? Number(route.query.c) : null))
const index = computed(() => order.value.indexOf(selectedId.value))

function select(id) {
  router.replace({ query: { ...route.query, c: id ?? undefined } })
}

const wide = window.matchMedia?.('(min-width: 1024px)')
watch(items, (list) => {
  if (!selectedId.value && list.length && wide?.matches) select(list[0].id)
})

const go = (step) => {
  const id = order.value[index.value + step]
  if (id) select(id)
}

const current = ref(null)
const detailLoading = ref(false)
// الرد المكتوب؛ يُفرَّغ عند الانتقال إلى رسالة أخرى (قبل المراقب الذي يستعمله)
const draft = ref('')

async function loadDetail(id) {
  if (!id) { current.value = null; return }
  detailLoading.value = true
  try {
    const { data } = await admin.contact(id)
    if (selectedId.value === id) current.value = data.data
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    if (selectedId.value === id) detailLoading.value = false
  }
}
watch(selectedId, (id) => { draft.value = ''; loadDetail(id) }, { immediate: true })

/** وعد الرد: كم بقي، أو كم تأخّرت */
function deadline(m) {
  if (!m || m.status === 'closed') return null
  const hours = meta.value.promise_hours ?? 72
  const left = Math.round(hours - (Date.now() - new Date(m.created_at)) / 3_600_000)
  if (left < 0) return { text: `متأخرة ${num(Math.abs(left))} ساعة`, tone: 'text-rose-700', late: true }
  return { text: `تبقّى ${num(left)} ساعة`, tone: left <= 12 ? 'text-amber-700' : 'text-sand-500' }
}

/* ── الرد ────────────────────────────────────────────────────────────── */

const composer = ref(null)
const sending = ref(false)

async function useTemplate() {
  draft.value = TEMPLATES[current.value?.subject] ?? TEMPLATES.general
  await nextTick()
  composer.value?.focus()
  composer.value?.setSelectionRange(draft.value.length, draft.value.length)
}

async function send(close) {
  const m = current.value
  if (!m || sending.value) return
  if (draft.value.trim().length < 10) {
    ui.error('الرد أقصر من أن يجيب. وضّحه أكثر.')
    composer.value?.focus()
    return
  }
  sending.value = true
  try {
    const { data } = await admin.replyContact(m.id, draft.value.trim(), close)
    ui.success(data.message ?? 'أُرسل الرد.')
    draft.value = ''
    queue.refresh(true)
    const next = order.value[index.value + 1] ?? order.value[index.value - 1] ?? null
    await load()
    // المغلقة تخرج من «المفتوحة»: ننتقل إلى التالية؛ وإلا نبقى ونرى الرد في السياق
    if (close && !items.value.some((x) => x.id === m.id)) select(next)
    else loadDetail(m.id)
  } catch (e) {
    ui.error(e.fieldErrors?.body?.[0] ?? e.userMessage)
  } finally {
    sending.value = false
  }
}

async function setStatus(status) {
  const m = current.value
  try {
    await admin.updateContact(m.id, status)
    ui.success(status === 'closed' ? 'أُغلقت الرسالة.' : status === 'new' ? 'أُعيد فتح الرسالة.' : 'بدأت معالجتها.')
    queue.refresh(true)
    await load()
    loadDetail(m.id)
  } catch (e) {
    ui.error(e.userMessage)
  }
}
</script>

<template>
  <div class="flex flex-col lg:h-[calc(100dvh-4rem)]">
    <header class="mb-3">
      <h1 class="text-2xl font-bold text-sand-900">استفسارات الزوار</h1>
      <p class="text-sm text-sand-500 mt-0.5">
        من نموذج «تواصل معنا» في الموقع، ووعد الرد خلال <span class="num">{{ meta.promise_hours ?? 72 }}</span> ساعة.
        <template v-if="meta.overdue_count"> · <button type="button" class="text-rose-700 font-medium hover:underline" @click="filters.overdue = true"><span class="num">{{ num(meta.overdue_count) }}</span> تجاوزت الوعد</button></template>
      </p>
    </header>

    <StatusTabs v-model="filters.status" :options="STATUSES" :counts="statusCounts" :total="meta.all_count ?? null" label="حالة الرسالة" />

    <div class="card flex-1 min-h-0 grid lg:grid-cols-[22rem_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)] overflow-hidden">
      <!-- ══ القائمة ══ -->
      <aside class="flex flex-col min-h-0 lg:border-e border-sand-200" aria-label="الاستفسارات">
        <div class="p-3 space-y-2 border-b border-sand-200">
          <label class="relative block">
            <span class="sr-only">ابحث بالاسم أو البريد أو النص</span>
            <BaseIcon name="search" :size="15" class="absolute top-1/2 -translate-y-1/2 text-sand-400 pointer-events-none" style="inset-inline-start: 0.7rem" />
            <input v-model="filters.q" type="search" class="input ps-8 !py-1.5" placeholder="الاسم، البريد، أو كلمة من الرسالة…" />
          </label>
          <div class="flex gap-2">
            <select v-model="filters.topic" class="input !py-1.5 text-sm flex-1" aria-label="الموضوع">
              <option value="">كل المواضيع</option>
              <option v-for="(label, key) in TOPICS" :key="key" :value="key">{{ label }}</option>
            </select>
            <button type="button" class="chip !py-1 !text-xs shrink-0" :aria-pressed="filters.overdue" @click="filters.overdue = !filters.overdue">
              <BaseIcon name="clock" :size="12" /> متأخرة
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
            {{ filters.status === 'open' ? 'لا استفسارات مفتوحة. كل الزوار أُجيبوا.' : 'لا رسائل في هذا التصنيف.' }}
          </p>

          <ul v-else class="divide-y divide-sand-100">
            <li v-for="m in items" :key="m.id">
              <button
                type="button" class="w-full text-start px-3 py-2.5 border-s-2 transition-colors"
                :class="selectedId === m.id ? 'border-clay-600 bg-clay-50/70' : 'border-transparent hover:bg-sand-50'"
                :aria-current="selectedId === m.id ? 'true' : undefined"
                @click="select(m.id)"
              >
                <span class="flex items-center gap-2">
                  <span class="font-medium text-sm text-sand-900 truncate" :class="m.status === 'new' ? 'font-semibold' : ''">{{ m.name }}</span>
                  <span v-if="m.sponsor" class="badge bg-amber-50 text-amber-800 !text-[0.65rem]">كافل</span>
                  <span class="ms-auto text-[0.7rem] shrink-0" :class="deadline(m)?.tone ?? 'text-sand-400'">{{ deadline(m)?.text ?? relative(m.created_at) }}</span>
                </span>
                <span class="block text-xs text-sand-500 truncate mt-0.5">{{ TOPICS[m.subject] ?? m.subject }} · {{ m.body }}</span>
              </button>
            </li>
          </ul>
        </div>
      </aside>

      <!-- ══ الرسالة والرد ══ -->
      <section v-if="selectedId" class="flex flex-col min-h-0 fixed inset-0 z-40 bg-sand-0 lg:static lg:z-auto" aria-labelledby="ctTitle">
        <div v-if="detailLoading && !current" class="p-6 space-y-3">
          <div v-for="n in 3" :key="n" class="h-12 rounded bg-sand-100 animate-pulse" />
        </div>

        <template v-else-if="current">
          <div class="flex items-start gap-3 px-4 py-3 border-b border-sand-200">
            <button class="btn btn-ghost !p-1.5 lg:hidden" type="button" aria-label="رجوع" @click="select(null)">
              <BaseIcon name="chevron" :size="18" />
            </button>
            <div class="flex-1 min-w-0">
              <h2 id="ctTitle" class="font-semibold text-sand-900 flex flex-wrap items-center gap-2">
                {{ current.name }}
                <RouterLink v-if="current.sponsor" :to="`/sponsors/${current.sponsor.id}`" class="badge bg-amber-50 text-amber-800 hover:underline">
                  كافل · <span class="num">{{ current.sponsor.code }}</span>
                </RouterLink>
              </h2>
              <p class="text-xs text-sand-500 mt-0.5 flex flex-wrap gap-x-3">
                <a :href="`mailto:${current.email}`" class="num hover:text-clay-700" dir="ltr">{{ current.email }}</a>
                <a v-if="current.phone" :href="`tel:${current.phone}`" class="num hover:text-clay-700" dir="ltr">{{ current.phone }}</a>
                <span>{{ TOPICS[current.subject] ?? current.subject }}</span>
              </p>
            </div>
            <span class="flex flex-col items-end gap-1 shrink-0">
              <StatusBadge :label="current.status_label" :value="current.status" />
              <span v-if="deadline(current)" class="text-[0.7rem] font-medium" :class="deadline(current).tone">{{ deadline(current).text }}</span>
            </span>
          </div>

          <!-- الرسالة وردودها -->
          <ol class="flex-1 min-h-0 overflow-y-auto px-4 py-5 space-y-3 bg-sand-25" aria-label="المحادثة">
            <li class="flex justify-start">
              <div class="max-w-[85%] rounded-2xl px-4 py-3 bg-sand-0 border border-sand-200 text-sm leading-relaxed">
                <p class="text-[0.7rem] text-sand-500 mb-1">{{ current.name }} · {{ dateTime(current.created_at) }}</p>
                <p class="whitespace-pre-line text-sand-900">{{ current.body }}</p>
              </div>
            </li>
            <li v-for="r in current.replies" :key="r.id" class="flex justify-end">
              <div class="max-w-[85%] rounded-2xl px-4 py-3 bg-clay-50 text-sm leading-relaxed">
                <p class="text-[0.7rem] text-clay-800 mb-1">ردّ {{ r.author ?? 'الفريق' }} · {{ relative(r.created_at) }}</p>
                <p class="whitespace-pre-line text-sand-900">{{ r.body }}</p>
              </div>
            </li>
            <li v-if="!current.replies?.length && current.handler" class="text-center text-xs text-sand-400">
              {{ current.status === 'closed' ? 'أُغلقت' : 'بدأت معالجتها' }} بيد {{ current.handler.name }}، ولا رد مسجَّل من المنصة.
            </li>
          </ol>

          <!-- الرد -->
          <form class="border-t border-sand-200 px-4 py-3 space-y-2" @submit.prevent="send(false)">
            <label class="sr-only" for="ctReply">ردّك</label>
            <textarea
              id="ctReply" ref="composer" v-model="draft" class="input" rows="3" maxlength="5000"
              :placeholder="`ردّك على ${current.name}، يصله بالبريد من عنوان كفالة…`"
            />
            <div class="flex flex-wrap items-center gap-2">
              <button class="btn btn-primary" type="submit" :disabled="sending || !draft.trim()">
                <BaseIcon :name="sending ? 'loader' : 'mail'" :size="15" :class="{ 'animate-spin': sending }" /> أرسل
              </button>
              <button v-if="current.status !== 'closed'" class="btn btn-outline" type="button" :disabled="sending || !draft.trim()" @click="send(true)">
                أرسل وأغلق
              </button>
              <button v-if="!draft" class="btn btn-ghost text-sm" type="button" @click="useTemplate">ابدأ من قالب</button>

              <span class="ms-auto flex items-center gap-1">
                <button v-if="current.status === 'new'" class="btn btn-ghost text-sm" type="button" @click="setStatus('in_progress')">بدء المعالجة</button>
                <button v-if="current.status !== 'closed'" class="btn btn-ghost text-sm" type="button" @click="setStatus('closed')">إغلاق بلا رد</button>
                <button v-else class="btn btn-ghost text-sm" type="button" @click="setStatus('new')">إعادة فتح</button>
                <button class="btn btn-ghost !px-2" type="button" :disabled="index <= 0" aria-label="السابقة" @click="go(-1)"><BaseIcon name="chevron" :size="15" /></button>
                <button class="btn btn-ghost !px-2" type="button" :disabled="index >= order.length - 1" aria-label="التالية" @click="go(1)"><BaseIcon name="chevron" :size="15" style="transform: scaleX(-1)" /></button>
              </span>
            </div>
          </form>
        </template>
      </section>

      <section v-else class="hidden lg:grid place-items-center p-10 text-center">
        <div>
          <span class="mx-auto mb-3 size-12 grid place-items-center rounded-full bg-clay-50 text-clay-700"><BaseIcon name="mail" :size="22" /></span>
          <p class="font-medium text-sand-900">{{ items.length ? 'اختر رسالة' : 'لا استفسارات هنا' }}</p>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
/**
 * التحقق من المستندات: مساحة عمل لا قائمة.
 *
 * الطابور يميناً مجمّعاً بالطفل، ولوحة المراجعة يساراً ثابتة: المستند كبيراً،
 * وفوقه ما يُطابَق به من ملف الطفل، وتحته القرار. يُقرَّر بالفأرة أو بلوحة
 * المفاتيح (V تحقّق، R رفض، J وK للتنقّل) وينتقل وحده إلى التالي، فطابورٌ
 * من ثلاثين مستنداً يُنجز دون فتح نافذة واحدة.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useQueueStore } from '@/stores/queue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { date, num, relative, years } from '@/utils/format'
import BaseIcon from '@/components/BaseIcon.vue'
import DocumentViewer from '@/components/DocumentViewer.vue'

const route = useRoute()
const router = useRouter()
const queue = useQueueStore()
const auth = useAuthStore()
const ui = useUiStore()

const DEFAULTS = { q: '', type: '' }
const { items, meta, loading, error, filters, load } = useResource(admin.documents.queue, { ...DEFAULTS, per_page: 50 })

/* ── الأنواع ─────────────────────────────────────────────────────────── */

const REQUIRED = ['birth_certificate', 'death_certificate', 'guardianship_proof']
const SHORT = { birth_certificate: 'ميلاد', death_certificate: 'وفاة الوالد', guardianship_proof: 'وصاية' }
const types = ref([])

onMounted(async () => {
  try {
    const { data } = await admin.references()
    types.value = data.data?.document_types ?? []
  } catch {
    // الفلتر يبقى «كل الأنواع»
  }
})

/** ما يُطابَق في كل نوع */
const HINTS = {
  birth_certificate: 'طابق الاسم الكامل وتاريخ الميلاد والجنس، واسم الأب مع اسم العائلة.',
  death_certificate: 'اسم المتوفّى يطابق اسم والد الطفل، وتاريخ الوفاة سابق لتسجيل الملف.',
  guardianship_proof: 'اسم الوصي وصلته بالطفل كما في ملف الأسرة.',
  medical_report: 'التقرير باسم الطفل، وتاريخه حديث، وعليه ختم الجهة.',
  school_record: 'السجل باسم الطفل، والصف يطابق ما في ملفه.',
  photo: 'الصورة للطفل نفسه وواضحة، ولا تكشف موقع سكنه.',
  other: 'صلة المستند بالطفل واضحة ومفهومة.',
}

/** أسباب الرفض الشائعة بنقرة، والنص الحر لما سواها */
const REASONS = ['الصورة غير واضحة', 'المستند لا يخص هذا الطفل', 'البيانات لا تطابق ملف الطفل', 'المستند ناقص أو مقصوص', 'ليس النوع المختار']

/* ── الطابور مجمّعاً بالطفل ──────────────────────────────────────────── */

const groups = computed(() => {
  const map = new Map()
  for (const d of items.value) {
    if (!map.has(d.orphan.id)) map.set(d.orphan.id, { orphan: d.orphan, docs: [] })
    map.get(d.orphan.id).docs.push(d)
  }
  return [...map.values()]
})

const order = computed(() => items.value.map((d) => d.id))
const current = computed(() => items.value.find((d) => d.id === Number(route.query.doc)) ?? null)
const index = computed(() => order.value.indexOf(current.value?.id))

function select(id) {
  rejecting.value = false
  router.replace({ query: { ...route.query, doc: id ?? undefined } })
}

/** على الشاشة العريضة يُفتح أول مستند وحده: لا لوحة فارغة تنتظر نقرة */
const wide = window.matchMedia?.('(min-width: 1024px)')
watch(items, (list) => {
  if (!current.value && list.length && wide?.matches) select(list[0].id)
})

const age = (dob) => (dob ? Math.floor((Date.now() - new Date(dob).getTime()) / 31557600000) : null)

/* ── القرار ──────────────────────────────────────────────────────────── */

const busy = ref(false)
const rejecting = ref(false)
const reason = ref('')
const reasonInput = ref(null)
const ready = ref(null)
const own = computed(() => current.value?.uploaded_by === auth.user?.id)

/** يُزال المستند من الطابور مكانه، وينتقل إلى ما بعده (أو ما قبله إن كان الأخير) */
function removeCurrent(doc) {
  const i = order.value.indexOf(doc.id)
  const next = order.value[i + 1] ?? order.value[i - 1] ?? null
  items.value = items.value.filter((d) => d.id !== doc.id)
  meta.value = {
    ...meta.value,
    all_count: Math.max(0, (meta.value.all_count ?? 1) - 1),
    decided_today: (meta.value.decided_today ?? 0) + 1,
    type_counts: { ...meta.value.type_counts, [doc.type]: Math.max(0, (meta.value.type_counts?.[doc.type] ?? 1) - 1) },
  }
  select(next)
  queue.refresh(true)
  if (!items.value.length) load()
}

async function verify() {
  const doc = current.value
  if (!doc || own.value || busy.value) return
  busy.value = true
  try {
    const { data } = await admin.documents.verify(doc.id)
    ui.success(`تُحقِّق من ${doc.type_label} لـ ${doc.orphan.name}.`)
    if (data?.orphan_ready) ready.value = doc.orphan
    removeCurrent(doc)
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    busy.value = false
  }
}

async function startReject() {
  if (!current.value || own.value || busy.value) return
  rejecting.value = true
  reason.value = ''
  await nextTick()
  reasonInput.value?.focus()
}

async function confirmReject() {
  const doc = current.value
  if (!doc || busy.value) return
  if (reason.value.trim().length < 5) {
    ui.error('اكتب سبب الرفض، يقرؤه من رفع المستند.')
    reasonInput.value?.focus()
    return
  }
  busy.value = true
  try {
    await admin.documents.reject(doc.id, reason.value.trim())
    ui.success(`رُفض ${doc.type_label} لـ ${doc.orphan.name}، والسبب في ملفه.`)
    rejecting.value = false
    removeCurrent(doc)
  } catch (e) {
    ui.error(e.fieldErrors?.reason?.[0] ?? e.userMessage)
  } finally {
    busy.value = false
  }
}

const go = (step) => {
  const id = order.value[index.value + step]
  if (id) select(id)
}

/* ── لوحة المفاتيح: بموضع المفتاح لا بحرفه، فتعمل بالعربية والإنجليزية ── */

function onKey(e) {
  if (e.ctrlKey || e.metaKey || e.altKey) return
  const t = e.target
  const typing = t instanceof HTMLElement && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName))
  if (document.querySelector('[role="dialog"][aria-modal="true"]')) return

  if (e.key === 'Escape' && rejecting.value) {
    e.preventDefault()
    rejecting.value = false
    return
  }
  if (typing || !current.value) return

  const action = { KeyV: verify, KeyR: startReject, KeyJ: () => go(1), KeyK: () => go(-1) }[e.code]
  if (action) {
    e.preventDefault()
    action()
  }
}
onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))

const DOT = { verified: 'bg-olive-600', pending: 'bg-amber-400', rejected: 'bg-rose-500', missing: 'bg-sand-300' }
const DOT_LABEL = { verified: 'مُتحقَّق منه', pending: 'ينتظر', rejected: 'مرفوض', missing: 'لم يُرفع' }
</script>

<template>
  <div class="flex flex-col lg:h-[calc(100dvh-4rem)]">
    <!-- ── الرأس: العدد، وإنجاز اليوم، والاختصارات ── -->
    <header class="flex flex-wrap items-end justify-between gap-3 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-sand-900">التحقق من المستندات</h1>
        <p class="text-sm text-sand-500 mt-0.5">
          <template v-if="meta.all_count">
            <span class="num">{{ num(meta.all_count) }}</span> مستند من <span class="num">{{ num(meta.orphans_count) }}</span> ملفاً بانتظار قرار
          </template>
          <template v-else-if="!loading">لا شيء بانتظار قرار</template>
        </p>
      </div>
      <div class="flex items-center gap-3 text-xs text-sand-500">
        <span class="hidden lg:flex items-center gap-1.5">
          <kbd class="kbd">V</kbd> تحقّق <kbd class="kbd">R</kbd> رفض <kbd class="kbd">J</kbd><kbd class="kbd">K</kbd> تنقّل
        </span>
        <span class="rounded-full bg-clay-50 text-clay-700 px-3 py-1 font-medium">
          أنجزت اليوم <span class="num">{{ num(meta.decided_today ?? 0) }}</span>
        </span>
      </div>
    </header>

    <div class="card flex-1 min-h-0 grid lg:grid-cols-[22rem_minmax(0,1fr)] overflow-hidden">
      <!-- ══ الطابور ══ -->
      <aside class="flex flex-col min-h-0 border-sand-200 lg:border-e" aria-label="الطابور">
        <div class="p-3 space-y-2 border-b border-sand-200">
          <label class="relative block">
            <span class="sr-only">ابحث باسم الطفل</span>
            <BaseIcon name="search" :size="15" class="absolute top-1/2 -translate-y-1/2 text-sand-400 pointer-events-none" style="inset-inline-start: 0.7rem" />
            <input v-model="filters.q" type="search" class="input ps-8 !py-1.5" placeholder="ابحث باسم الطفل أو معرّفه…" />
          </label>
          <select v-model="filters.type" class="input !py-1.5 text-sm" aria-label="نوع المستند">
            <option value="">كل الأنواع ({{ num(meta.all_count ?? 0) }})</option>
            <option v-for="t in types" :key="t.value" :value="t.value" :disabled="!meta.type_counts?.[t.value]">
              {{ t.label }} ({{ num(meta.type_counts?.[t.value] ?? 0) }})
            </option>
          </select>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto">
          <div v-if="loading && !items.length" class="p-3 space-y-2">
            <div v-for="n in 6" :key="n" class="h-12 rounded-lg bg-sand-100 animate-pulse" />
          </div>

          <div v-else-if="error" class="p-6 text-center" role="alert">
            <p class="text-sm text-sand-600">{{ error }}</p>
            <button class="btn btn-outline mt-3" type="button" @click="load">إعادة المحاولة</button>
          </div>

          <p v-else-if="!items.length" class="p-6 text-center text-sm text-sand-500">
            {{ filters.q || filters.type ? 'لا مستندات تطابق البحث.' : 'الطابور فارغ.' }}
          </p>

          <section v-for="g in groups" v-else :key="g.orphan.id" class="border-b border-sand-100">
            <header class="flex items-center gap-2 px-3 pt-3 pb-1.5">
              <span class="font-medium text-sm text-sand-900 truncate">{{ g.orphan.full_name }}</span>
              <span class="num text-[0.7rem] text-sand-400">{{ g.orphan.reference_code }}</span>
              <!-- المستندات الثلاثة المطلوبة: نقطة لكلٍّ بحالها -->
              <span class="ms-auto flex items-center gap-1" :aria-label="REQUIRED.map((t) => `${SHORT[t]}: ${DOT_LABEL[g.orphan.required?.[t] ?? 'missing']}`).join('، ')">
                <span
                  v-for="t in REQUIRED" :key="t" class="size-2 rounded-full"
                  :class="DOT[g.orphan.required?.[t] ?? 'missing']" :title="`${SHORT[t]}: ${DOT_LABEL[g.orphan.required?.[t] ?? 'missing']}`"
                />
              </span>
            </header>
            <ul class="pb-2">
              <li v-for="d in g.docs" :key="d.id">
                <button
                  type="button"
                  class="w-full text-start flex items-center gap-2.5 px-3 py-2 border-s-2 transition-colors"
                  :class="current?.id === d.id ? 'border-clay-600 bg-clay-50/70' : 'border-transparent hover:bg-sand-50'"
                  :aria-current="current?.id === d.id ? 'true' : undefined"
                  @click="select(d.id)"
                >
                  <BaseIcon :name="d.mime?.startsWith('image/') ? 'eye' : 'file'" :size="15" :class="current?.id === d.id ? 'text-clay-700' : 'text-sand-400'" />
                  <span class="flex-1 min-w-0">
                    <span class="block text-sm" :class="current?.id === d.id ? 'text-clay-800 font-medium' : 'text-sand-800'">{{ d.type_label }}</span>
                    <span class="block text-[0.7rem] text-sand-500 truncate">{{ d.uploader ?? 'غير محدد' }} · {{ relative(d.created_at) }}</span>
                  </span>
                  <span v-if="d.uploaded_by === auth.user?.id" class="text-[0.65rem] text-sand-400">رفعته أنت</span>
                </button>
              </li>
            </ul>
          </section>
        </div>
      </aside>

      <!-- ══ المراجعة ══ -->
      <section
        v-if="current"
        class="flex flex-col min-h-0 fixed inset-0 z-40 bg-sand-0 lg:static lg:z-auto"
        aria-labelledby="reviewTitle"
      >
        <!-- الرأس -->
        <div class="flex items-center gap-3 px-4 py-3 border-b border-sand-200">
          <button class="btn btn-ghost !p-1.5 lg:hidden" type="button" aria-label="رجوع إلى الطابور" @click="select(null)">
            <BaseIcon name="chevron" :size="18" />
          </button>
          <div class="flex-1 min-w-0">
            <h2 id="reviewTitle" class="font-semibold text-sand-900 truncate">{{ current.type_label }} · {{ current.orphan.full_name }}</h2>
            <p class="text-xs text-sand-500 truncate">
              <span class="num" dir="ltr">{{ current.original_name }}</span>
              · رفعه {{ current.uploader ?? 'غير محدد' }} {{ relative(current.created_at) }}
            </p>
          </div>
          <span class="hidden sm:inline text-xs text-sand-400 num">{{ index + 1 }} / {{ order.length }}</span>
          <a class="btn btn-ghost !p-2" :href="admin.documents.downloadUrl(current.id)" :aria-label="`تنزيل ${current.original_name}`" title="تنزيل">
            <BaseIcon name="download" :size="16" />
          </a>
          <RouterLink :to="`/orphans/${current.orphan.id}?tab=documents`" class="btn btn-ghost !p-2" aria-label="فتح ملف الطفل" title="فتح ملف الطفل">
            <BaseIcon name="external" :size="16" />
          </RouterLink>
        </div>

        <!-- ما يُطابَق به: من ملف الطفل، في سطر واحد فوق المستند -->
        <div class="px-4 py-3 border-b border-sand-200 bg-sand-25">
          <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <div><dt class="text-[0.7rem] text-sand-500">الاسم الكامل</dt><dd class="font-medium">{{ current.orphan.full_name }}</dd></div>
            <div><dt class="text-[0.7rem] text-sand-500">تاريخ الميلاد</dt>
              <dd>{{ date(current.orphan.date_of_birth) }} <span class="text-xs text-sand-500">({{ years(age(current.orphan.date_of_birth)) }})</span></dd>
            </div>
            <div><dt class="text-[0.7rem] text-sand-500">الجنس</dt><dd>{{ current.orphan.gender_label }}</dd></div>
            <div><dt class="text-[0.7rem] text-sand-500">الوصي</dt>
              <dd>{{ current.orphan.guardian ?? 'غير محدد' }} <span v-if="current.orphan.guardian_relation" class="text-xs text-sand-500">({{ current.orphan.guardian_relation }})</span></dd>
            </div>
            <div><dt class="text-[0.7rem] text-sand-500">الأسرة</dt><dd class="num">{{ current.orphan.family_code }}</dd></div>
          </dl>
          <p class="mt-2 text-xs text-amber-800 flex items-start gap-1.5">
            <BaseIcon name="alert" :size="13" class="mt-0.5 shrink-0" />
            {{ HINTS[current.type] ?? HINTS.other }}
          </p>
        </div>

        <!-- اكتمال ملف بقرارٍ سابق: خطوته التالية -->
        <div v-if="ready" class="flex items-center gap-3 px-4 py-2.5 bg-olive-50 border-b border-olive-200 text-sm" role="status">
          <BaseIcon name="check" :size="16" class="text-olive-700" />
          <span class="flex-1">اكتملت مستندات <strong>{{ ready.name }}</strong> الثلاثة. ملفه جاهز للتوثيق.</span>
          <RouterLink :to="`/orphans/${ready.id}`" class="text-olive-800 font-medium hover:underline">وثّقه الآن</RouterLink>
          <button class="btn btn-ghost !p-1" type="button" aria-label="إخفاء" @click="ready = null"><BaseIcon name="close" :size="14" /></button>
        </div>

        <!-- المستند -->
        <div class="flex-1 min-h-[45vh]">
          <DocumentViewer :document="current" />
        </div>

        <!-- القرار -->
        <div class="border-t border-sand-200 px-4 py-3">
          <p v-if="own" class="text-sm text-sand-500 py-1.5">رفعت هذا المستند بنفسك، فيتحقق منه غيرك.</p>

          <!-- الرفض في مكانه: أسباب جاهزة بنقرة، أو سبب يُكتب -->
          <form v-else-if="rejecting" class="space-y-2.5" @submit.prevent="confirmReject">
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="r in REASONS" :key="r" type="button" class="chip !py-1 !text-xs"
                :aria-pressed="reason === r" @click="reason = r"
              >{{ r }}</button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="sr-only" for="rejectReason">سبب الرفض</label>
              <input
                id="rejectReason" ref="reasonInput" v-model="reason" class="input flex-1 min-w-48"
                maxlength="255" placeholder="سبب الرفض، يقرؤه من رفع المستند…"
              />
              <button class="btn bg-rose-600 text-white hover:bg-rose-700" type="submit" :disabled="busy">ارفض المستند</button>
              <button class="btn btn-ghost" type="button" :disabled="busy" @click="rejecting = false">إلغاء <kbd class="kbd">Esc</kbd></button>
            </div>
          </form>

          <div v-else class="flex flex-wrap items-center gap-2">
            <button class="btn btn-primary" type="button" :disabled="busy" @click="verify">
              <BaseIcon :name="busy ? 'loader' : 'check'" :size="15" :class="{ 'animate-spin': busy }" />
              تحقّقت منه <kbd class="kbd !bg-white/15 !border-white/25 !text-white">V</kbd>
            </button>
            <button class="btn btn-outline text-rose-700" type="button" :disabled="busy" @click="startReject">
              رفض <kbd class="kbd">R</kbd>
            </button>
            <span class="ms-auto flex items-center gap-1">
              <button class="btn btn-ghost" type="button" :disabled="index <= 0" @click="go(-1)">
                <BaseIcon name="chevron" :size="15" /> السابق
              </button>
              <button class="btn btn-ghost" type="button" :disabled="index >= order.length - 1" @click="go(1)">
                التالي <BaseIcon name="chevron" :size="15" style="transform: scaleX(-1)" />
              </button>
            </span>
          </div>
        </div>
      </section>

      <!-- لا مستند مختار (أو الطابور فارغ) -->
      <section v-else class="hidden lg:grid place-items-center p-10 text-center">
        <div>
          <span class="mx-auto mb-3 size-12 grid place-items-center rounded-full bg-clay-50 text-clay-700">
            <BaseIcon name="check" :size="22" />
          </span>
          <p class="font-medium text-sand-900">{{ items.length ? 'اختر مستنداً من الطابور' : 'لا مستندات بانتظار قرار' }}</p>
          <p class="text-sm text-sand-500 mt-1">
            {{ items.length ? 'يُعرض هنا بجانب بيانات الطفل.' : 'المستندات الجديدة تظهر هنا فور رفعها.' }}
          </p>
        </div>
      </section>
    </div>
  </div>
</template>

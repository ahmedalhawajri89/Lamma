<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { confirmDialog } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, dateTime, years, num, EMPTY } from '@/utils/format'
import StatusBadge from '@/components/StatusBadge.vue'
import FundingBar from '@/components/FundingBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import DocumentsPanel from '@/components/DocumentsPanel.vue'
import OrphanFormModal from '@/components/OrphanFormModal.vue'
import ActivityTimeline from '@/components/ActivityTimeline.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const orphan = ref(null)
const loading = ref(true)
const failed = ref(false)
const busy = ref(false)

async function load() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await admin.orphans.show(route.params.id)
    orphan.value = data.data
  } catch (e) {
    failed.value = !orphan.value
    ui.error(e.userMessage ?? 'تعذّر تحميل الملف.')
  } finally {
    loading.value = false
  }
}

/**
 * إجراءات لها أثر خارج اللوحة تُؤكَّد بما ستفعله: التوثيق لا يُتراجع عنه،
 * والإخفاء يُزيل الطفل من أمام كفلاء ربما يتصفّحونه الآن. وبعد كل إجراء
 * يُعاد تحميل الملف من الخادم بدل تخمين أثره هنا.
 */
const CONFIRM = {
  verify: {
    title: 'توثيق الملف',
    message: 'التوثيق يشهد بأن المستندات طوبقت مع الملف. لا يُتراجع عنه، ويجعل الملف قابلاً للنشر والكفالة.',
    confirmLabel: 'وثّق الملف',
  },
  unpublish: {
    title: 'إخفاء الملف عن الموقع',
    message: 'يختفي الطفل من الموقع العام فوراً، ولا يستطيع كافل جديد أن يجده. الكفالات القائمة لا تتأثر.',
    confirmLabel: 'أخفِ الملف',
    danger: true,
  },
}

/**
 * تغيير المسار: تخرّج أو وفاة. للمدير وحده، بسببٍ إلزامي يبقى في سجل الملف،
 * ويُخفي الملف من الموقع.
 */
const PATHS = {
  graduated: { label: 'تسجيل تخرّج', title: 'تسجيل تخرّج الطفل', message: 'يُغلق الملف للكفالة الجديدة ويُخفى من الموقع. الكفالات القائمة تُراجَع بعدها.' },
  deceased: { label: 'تسجيل وفاة', title: 'تسجيل وفاة الطفل', message: 'يُغلق الملف نهائياً ويُخفى من الموقع، ولا يُعدَّل بعدها إلا بيد المدير.', danger: true },
}
const CLOSED = ['graduated', 'withdrawn', 'deceased']
const closed = computed(() => CLOSED.includes(orphan.value?.status))
const canChangePath = computed(() => auth.isAdmin && orphan.value && !['deceased', 'withdrawn'].includes(orphan.value.status))
const canEdit = computed(() => auth.can('orphans.update') && (auth.isAdmin || !['deceased', 'withdrawn'].includes(orphan.value?.status)))

async function changePath(status) {
  menuOpen.value = false
  const cfg = PATHS[status]
  const answer = await confirmDialog({
    title: cfg.title, message: cfg.message, reason: 'required', reasonLabel: 'السبب', confirmLabel: cfg.label, danger: cfg.danger ?? false,
  })
  if (!answer) return
  await act((id) => admin.orphans.changeStatus(id, status, answer.reason), 'غُيّر مسار الملف.')
}

async function act(fn, successMessage, confirmKey = null) {
  menuOpen.value = false
  if (confirmKey && !(await confirmDialog(CONFIRM[confirmKey]))) return

  busy.value = true
  try {
    await fn(route.params.id)
    ui.success(successMessage)
    await load()
  } catch (e) {
    ui.error(e.fieldErrors?.documents?.[0] ?? e.fieldErrors?.status?.[0] ?? e.userMessage)
  } finally {
    busy.value = false
  }
}

/* ── مسار الملف: أين هو، وما خطوته التالية ───────────────────────────── */

const REQUIRED_DOCUMENTS = ['birth_certificate', 'death_certificate', 'guardianship_proof']

const verifiedDocs = computed(() =>
  REQUIRED_DOCUMENTS.filter((type) => (orphan.value?.documents ?? []).some((d) => d.type === type && d.is_verified)).length,
)
const documentsReady = computed(() => verifiedDocs.value === REQUIRED_DOCUMENTS.length)

/** الكفالات التي تُحسب في تمويله (بانتظار الدفع ونشطة وموقوفة) */
const LIVE = ['pending', 'active', 'paused']
const liveSponsorships = computed(() => (orphan.value?.sponsorships ?? []).filter((s) => LIVE.includes(s.status)))
const pastSponsorships = computed(() => (orphan.value?.sponsorships ?? []).filter((s) => !LIVE.includes(s.status)))
const covered = computed(() => liveSponsorships.value.reduce((sum, s) => sum + Number(s.monthly_amount ?? 0), 0))
const remaining = computed(() => Math.max(0, Number(orphan.value?.monthly_need ?? 0) - covered.value))

/**
 * خمس خطوات بين ملفٍ يُنشأ وطفلٍ مكفول. كانت الأزرار في الترويسة تظهر
 * وتختفي بلا تفسير: لا يعرف المشرف لماذا «توثيق الملف» معطّل، ولا ما بقي
 * قبل أن يظهر الطفل للكفلاء. الشريط يقول الخطوة الحالية وما يلزمها.
 */
const steps = computed(() => {
  const o = orphan.value
  if (!o) return []
  const verified = Boolean(o.verified_at)
  const published = Boolean(o.published_at)
  const funded = o.funded_percent >= 100

  const list = [
    { key: 'file', label: 'إنشاء الملف', done: true, hint: date(o.created_at) },
    { key: 'docs', label: 'المستندات', done: documentsReady.value, hint: `${num(verifiedDocs.value)} من ${num(REQUIRED_DOCUMENTS.length)} مُتحقَّق منها` },
    { key: 'verify', label: 'التوثيق', done: verified, hint: verified ? date(o.verified_at) : 'بعد اكتمال المستندات' },
    { key: 'publish', label: 'النشر', done: published, hint: published ? date(o.published_at) : 'بعد التوثيق' },
    { key: 'fund', label: 'الكفالة', done: funded, hint: published ? `مغطّى ${o.funded_percent}%` : 'بعد النشر' },
  ]
  const current = list.findIndex((s) => !s.done)
  return list.map((s, i) => ({ ...s, current: i === current }))
})

/** ما يُطلب الآن، بجملة وزرّ واحد */
const nextStep = computed(() => {
  const o = orphan.value
  if (!o || closed.value) return null
  const current = steps.value.find((s) => s.current)?.key

  if (current === 'docs') {
    const missing = REQUIRED_DOCUMENTS.length - verifiedDocs.value
    return {
      text: `${num(missing)} ${missing === 1 ? 'مستند ينتظر' : 'مستندات تنتظر'} الرفع أو التحقق قبل التوثيق.`,
      action: { label: 'إلى المستندات', run: () => { tab.value = 'documents' } },
    }
  }
  if (current === 'verify') {
    return auth.can('orphans.verify')
      ? { text: 'المستندات مكتملة. الملف جاهز للتوثيق.', action: { label: 'وثّق الملف', primary: true, run: () => act(admin.orphans.verify, 'وُثّق الملف.', 'verify') } }
      : { text: 'المستندات مكتملة. ينتظر الملف توثيق المدير.' }
  }
  if (current === 'publish') {
    return auth.can('orphans.publish')
      ? { text: 'الملف موثّق ولم يُنشر. انشره ليراه الكفلاء على الموقع.', action: { label: 'انشر على الموقع', primary: true, run: () => act(admin.orphans.publish, 'نُشر الملف على الموقع.') } }
      : { text: 'الملف موثّق، وينتظر النشر.' }
  }
  if (current === 'fund') {
    return { text: `منشور على الموقع، وينقصه ${money(remaining.value)} شهرياً ليكتمل تمويله.` }
  }
  return null
})

/* ── التبويبات: الملف أولاً، والباقي عند الحاجة ───────────────────────── */

const tab = ref(route.query.tab ?? 'profile')
watch(tab, (t) => router.replace({ query: { ...route.query, tab: t === 'profile' ? undefined : t } }))

const tabs = computed(() => [
  { key: 'profile', label: 'الملف' },
  { key: 'documents', label: 'المستندات', count: `${verifiedDocs.value}/${REQUIRED_DOCUMENTS.length}` },
  { key: 'sponsorships', label: 'الكفالات', count: liveSponsorships.value.length },
  { key: 'followup', label: 'المتابعة', count: (orphan.value?.reports?.length ?? 0) + (orphan.value?.field_visits?.length ?? 0) },
  ...(auth.can('activity.view') ? [{ key: 'history', label: 'السجل' }] : []),
])

/* ── التحرير ─────────────────────────────────────────────────────────── */

const formOpen = ref(false)
const references = ref({})
const menuOpen = ref(false)

async function loadReferences() {
  // أنواع المستندات بأسمائها لكل من يرى الملف، لا لمن يعدّله وحده
  if (!auth.can('orphans.view')) return
  try {
    const { data } = await admin.references()
    references.value = data.data ?? {}
  } catch {
    // فشل القوائم لا يمنع عرض الملف، نافذة التحرير وحدها تتعطّل
  }
}

const timeline = ref(null)

async function onSaved() {
  formOpen.value = false
  await load()
  timeline.value?.reload()
}

onMounted(() => {
  load()
  loadReferences()
})

/**
 * رابط أخٍ يفتح الشاشة نفسها بمعرّف آخر، فلا تُركَّب من جديد. بلا هذا
 * يتغيّر الرابط ويبقى الملف القديم معروضاً.
 */
watch(() => route.params.id, (id, previous) => {
  if (!id || id === previous) return
  orphan.value = null
  tab.value = 'profile'
  load()
})

const CYCLE = { 1: 'شهرياً', 3: 'كل 3 أشهر', 12: 'سنوياً' }
</script>

<template>
  <div v-if="loading && !orphan" class="space-y-3">
    <div class="h-8 w-48 bg-sand-100 rounded animate-pulse" />
    <div class="h-40 bg-sand-100 rounded-2xl animate-pulse" />
    <div class="h-64 bg-sand-100 rounded-2xl animate-pulse" />
  </div>

  <div v-else-if="orphan">
    <RouterLink to="/orphans" class="inline-flex items-center gap-1.5 text-sm text-sand-600 hover:text-clay-600 mb-3">
      <BaseIcon name="chevron" :size="15" />
      الأيتام
    </RouterLink>

    <!-- ── الرأس: من هو، وأين ملفه، وما ينقصه ── -->
    <section class="card p-5 mb-4">
      <div class="flex flex-wrap gap-5 items-start">
        <img
          v-if="orphan.photo_url" :src="orphan.photo_url" alt=""
          class="size-20 rounded-2xl object-cover shrink-0" width="80" height="80"
        />
        <div v-else class="size-20 rounded-2xl bg-clay-50 text-clay-700 grid place-items-center shrink-0 text-3xl font-semibold">
          {{ orphan.first_name?.charAt(0) }}
        </div>

        <div class="flex-1 min-w-56">
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-2xl font-bold text-sand-900">{{ orphan.full_name }}</h1>
            <StatusBadge :label="orphan.status_label" :value="orphan.status" />
            <span v-if="orphan.is_urgent" class="badge bg-rose-600/10 text-rose-700">عاجل</span>
            <span v-if="orphan.is_public" class="badge bg-olive-100 text-olive-700">على الموقع</span>
          </div>
          <p class="text-sm text-sand-500 mt-1">
            <span class="num">{{ orphan.reference_code }}</span> · {{ years(orphan.age) }} · {{ orphan.gender_label }}
            · {{ orphan.program?.name ?? EMPTY }} · {{ orphan.family?.country?.name ?? EMPTY }}
          </p>

          <!-- التمويل بالأرقام لا بالنسبة وحدها -->
          <div v-if="!closed && orphan.is_public" class="mt-4 max-w-md">
            <FundingBar :percent="orphan.funded_percent" />
            <p class="text-xs text-sand-500 mt-1.5">
              يحتاج <span class="num text-sand-800">{{ money(orphan.monthly_need) }}</span> شهرياً
              · مغطّى <span class="num text-sand-800">{{ money(covered) }}</span>
              <template v-if="remaining > 0.009"> · ينقصه <span class="num text-rose-700 font-medium">{{ money(remaining) }}</span></template>
              · {{ num(liveSponsorships.length) }} {{ liveSponsorships.length === 1 ? 'كفالة قائمة' : 'كفالات قائمة' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button v-if="canEdit" class="btn btn-outline" type="button" :disabled="busy" @click="formOpen = true">
            <BaseIcon name="edit" :size="16" /> تحرير
          </button>

          <!-- إجراءات أقلّ تكراراً في قائمة واحدة لا صفّ أزرار -->
          <div v-if="canChangePath || (orphan.published_at && auth.can('orphans.publish'))" class="relative">
            <button
              class="btn btn-ghost !px-2.5" type="button" aria-haspopup="menu" :aria-expanded="menuOpen"
              aria-label="إجراءات أخرى" @click="menuOpen = !menuOpen"
            >
              <BaseIcon name="sliders" :size="16" />
            </button>
            <div v-if="menuOpen" class="fixed inset-0 z-10" @click="menuOpen = false" />
            <div v-if="menuOpen" role="menu" class="absolute end-0 mt-1 z-20 w-52 card p-1 shadow-lg">
              <button
                v-if="orphan.published_at && auth.can('orphans.publish')" role="menuitem" type="button" :disabled="busy"
                class="w-full text-start px-3 py-2 rounded-lg text-sm hover:bg-sand-50 text-sand-800"
                @click="act(admin.orphans.unpublish, 'أُخفي الملف عن الموقع.', 'unpublish')"
              >إخفاء عن الموقع</button>
              <template v-if="canChangePath">
                <button
                  v-for="(cfg, key) in PATHS" :key="key" role="menuitem" type="button" :disabled="busy"
                  class="w-full text-start px-3 py-2 rounded-lg text-sm hover:bg-sand-50"
                  :class="cfg.danger ? 'text-rose-700' : 'text-sand-800'"
                  @click="changePath(key)"
                >{{ cfg.label }}</button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- ── مسار الملف ── -->
      <div v-if="!closed" class="mt-5 pt-5 border-t border-sand-100">
        <ol class="grid grid-cols-5 gap-2" aria-label="مسار الملف">
          <li v-for="s in steps" :key="s.key" class="min-w-0" :aria-current="s.current ? 'step' : undefined">
            <div class="h-1 rounded-full mb-2" :class="s.done ? 'bg-clay-500' : s.current ? 'bg-amber-400' : 'bg-sand-200'" />
            <p class="text-xs font-medium flex items-center gap-1" :class="s.done ? 'text-clay-700' : s.current ? 'text-sand-900' : 'text-sand-400'">
              <BaseIcon v-if="s.done" name="check" :size="12" />
              {{ s.label }}
            </p>
            <p class="text-[0.7rem] text-sand-500 truncate hidden sm:block">{{ s.hint }}</p>
          </li>
        </ol>

        <div v-if="nextStep" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-sand-50 px-4 py-3">
          <p class="text-sm text-sand-800">{{ nextStep.text }}</p>
          <button
            v-if="nextStep.action" type="button" :disabled="busy"
            class="btn" :class="nextStep.action.primary ? 'btn-primary' : 'btn-outline'"
            @click="nextStep.action.run()"
          >{{ nextStep.action.label }}</button>
        </div>
      </div>

      <p v-else class="mt-5 pt-5 border-t border-sand-100 text-sm text-sand-600">
        الملف مغلق ({{ orphan.status_label }})، فلا يُعرض للكفالة الجديدة.
      </p>
    </section>

    <!-- ── التبويبات ── -->
    <nav class="border-b border-sand-200 mb-4" aria-label="أقسام الملف">
      <ul class="flex gap-1 overflow-x-auto -mb-px [scrollbar-width:none]">
        <li v-for="t in tabs" :key="t.key">
          <button
            type="button"
            class="flex items-center gap-2 px-3 py-2.5 text-sm whitespace-nowrap border-b-2 transition-colors"
            :class="tab === t.key ? 'border-clay-600 text-clay-700 font-semibold' : 'border-transparent text-sand-600 hover:text-sand-900'"
            :aria-pressed="tab === t.key" @click="tab = t.key"
          >
            {{ t.label }}
            <span
              v-if="t.count !== undefined" class="num rounded-full px-1.5 text-xs"
              :class="tab === t.key ? 'bg-clay-50 text-clay-700' : 'bg-sand-100 text-sand-600'"
            >{{ t.count }}</span>
          </button>
        </li>
      </ul>
    </nav>

    <!-- الملف -->
    <div v-if="tab === 'profile'" class="grid gap-4 lg:grid-cols-3">
      <section class="card p-5 lg:col-span-2">
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4 text-sm">
          <div><dt class="text-sand-500 text-xs mb-0.5">تاريخ الميلاد</dt><dd>{{ date(orphan.date_of_birth) }}</dd></div>
          <div><dt class="text-sand-500 text-xs mb-0.5">الجنس</dt><dd>{{ orphan.gender_label }}</dd></div>
          <div><dt class="text-sand-500 text-xs mb-0.5">البرنامج</dt><dd>{{ orphan.program?.name ?? EMPTY }}</dd></div>
          <div><dt class="text-sand-500 text-xs mb-0.5">الحاجة الشهرية</dt><dd class="num">{{ money(orphan.monthly_need) }}</dd></div>
          <div><dt class="text-sand-500 text-xs mb-0.5">الصف</dt><dd>{{ orphan.grade_level ?? EMPTY }}</dd></div>
          <div><dt class="text-sand-500 text-xs mb-0.5">الحالة الصحية</dt><dd>{{ orphan.health_status ?? EMPTY }}</dd></div>
        </dl>

        <div v-if="orphan.dream || orphan.story" class="mt-5 pt-5 border-t border-sand-100 space-y-3">
          <p v-if="orphan.dream" class="text-sm">
            <span class="text-sand-500">الحلم:</span> {{ orphan.dream }}
          </p>
          <p v-if="orphan.story" class="text-sm leading-relaxed text-sand-700">{{ orphan.story }}</p>
        </div>
        <p v-else class="mt-5 pt-5 border-t border-sand-100 text-sm text-sand-500">
          لا قصة بعد. القصة هي ما يقرؤه الكافل قبل أن يقرّر.
          <button v-if="canEdit" type="button" class="text-clay-700 hover:underline" @click="formOpen = true">أضفها</button>
        </p>
      </section>

      <section class="card p-5">
        <h2 class="text-sm font-semibold mb-3">الأسرة</h2>
        <dl class="space-y-2.5 text-sm">
          <div class="flex justify-between gap-3"><dt class="text-sand-500">رمز الأسرة</dt>
            <dd><RouterLink :to="`/families/${orphan.family?.id}`" class="num text-clay-700 hover:underline">{{ orphan.family?.family_code }}</RouterLink></dd>
          </div>
          <div class="flex justify-between gap-3"><dt class="text-sand-500">الوصي</dt>
            <dd class="text-end">{{ orphan.family?.guardian?.full_name ?? EMPTY }}
              <span v-if="orphan.family?.guardian?.relation_label" class="text-xs text-sand-500">({{ orphan.family.guardian.relation_label }})</span>
            </dd>
          </div>
          <div class="flex justify-between gap-3"><dt class="text-sand-500">المكان</dt>
            <dd>{{ orphan.family?.country?.name }}، {{ orphan.family?.city?.name ?? EMPTY }}</dd>
          </div>
          <div class="flex justify-between gap-3"><dt class="text-sand-500">الأفراد</dt><dd class="num">{{ orphan.family?.members_count ?? EMPTY }}</dd></div>
          <div class="flex justify-between gap-3"><dt class="text-sand-500">السكن</dt><dd>{{ orphan.family?.housing_status_label ?? EMPTY }}</dd></div>
          <div v-if="orphan.family?.monthly_income !== undefined" class="flex justify-between gap-3"><dt class="text-sand-500">الدخل الشهري</dt>
            <dd class="num">{{ money(orphan.family?.monthly_income) }}</dd>
          </div>
        </dl>

        <div v-if="orphan.siblings?.length" class="mt-4 pt-4 border-t border-sand-100">
          <p class="text-xs text-sand-500 mb-2">الإخوة في المنصة</p>
          <RouterLink
            v-for="s in orphan.siblings" :key="s.id" :to="`/orphans/${s.id}`"
            class="flex items-center justify-between py-1 text-sm text-clay-700 hover:underline"
          >
            {{ s.first_name }} <span class="num text-xs text-sand-500">{{ s.reference_code }}</span>
          </RouterLink>
        </div>
      </section>
    </div>

    <!-- المستندات: ما يجعل «ثلاث مراحل تحقّق» قابلاً للإثبات -->
    <div v-else-if="tab === 'documents'" class="max-w-3xl">
      <DocumentsPanel
        :key="orphan.id"
        :orphan-id="orphan.id"
        :verified="Boolean(orphan.verified_at)"
        :types="references.document_types ?? []"
        @changed="load"
      />
    </div>

    <!-- الكفالات: القائمة أولاً، والمنتهية تحتها -->
    <section v-else-if="tab === 'sponsorships'" class="card overflow-hidden">
      <ul v-if="orphan.sponsorships?.length" class="divide-y divide-sand-100">
        <li
          v-for="s in [...liveSponsorships, ...pastSponsorships]" :key="s.id"
          class="flex flex-wrap items-center gap-x-6 gap-y-1 px-5 py-3.5"
          :class="LIVE.includes(s.status) ? '' : 'opacity-70'"
        >
          <div class="flex-1 min-w-48">
            <p class="font-medium text-sm">{{ s.sponsor?.name ?? s.sponsor?.sponsor_code ?? EMPTY }}</p>
            <p class="text-xs text-sand-500">
              <span class="num">{{ s.reference_code }}</span>
              · منذ {{ date(s.started_at) }}
              <template v-if="s.ended_at"> · انتهت {{ date(s.ended_at) }}</template>
            </p>
          </div>
          <p class="text-sm text-end">
            <span class="num font-medium">{{ money(s.monthly_amount, s.currency) }}</span>
            <span class="text-xs text-sand-500"> شهرياً{{ s.interval_months > 1 ? ` · يدفع ${CYCLE[s.interval_months]}` : '' }}</span>
          </p>
          <StatusBadge :label="s.status_label" :value="s.status" />
        </li>
      </ul>
      <p v-else class="p-8 text-center text-sm text-sand-500">لا كفالات بعد، فهذه الحالة تنتظر كافلاً.</p>
      <div v-if="orphan.sponsorships?.length" class="px-5 py-3 border-t border-sand-100 bg-sand-25">
        <RouterLink :to="`/sponsorships?q=${orphan.reference_code}`" class="text-sm text-clay-700 hover:underline">
          فتح في شاشة الكفالات
        </RouterLink>
      </div>
    </section>

    <!-- المتابعة: التقارير والزيارات معاً -->
    <div v-else-if="tab === 'followup'" class="grid gap-4 lg:grid-cols-2">
      <section class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-semibold">التقارير الربعية</h2>
          <RouterLink to="/reports" class="text-xs text-clay-700 hover:underline">كل التقارير</RouterLink>
        </div>
        <ul v-if="orphan.reports?.length" class="divide-y divide-sand-100">
          <li v-for="r in orphan.reports" :key="r.id" class="flex items-center justify-between gap-3 py-2.5">
            <div class="min-w-0">
              <p class="text-sm font-medium truncate">{{ r.title }}</p>
              <p class="text-xs text-sand-500">{{ r.period_label }} · زيارة {{ date(r.visit_date) }}</p>
            </div>
            <StatusBadge :label="r.status_label" :value="r.status" />
          </li>
        </ul>
        <p v-else class="text-sm text-sand-500 py-4">لا تقارير بعد.</p>
      </section>

      <section class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-semibold">الزيارات الميدانية</h2>
          <RouterLink to="/visits" class="text-xs text-clay-700 hover:underline">كل الزيارات</RouterLink>
        </div>
        <ol v-if="orphan.field_visits?.length" class="relative border-s border-sand-200 ms-1.5 space-y-4">
          <li v-for="v in orphan.field_visits" :key="v.id" class="ps-4 relative">
            <span class="absolute -start-[5px] top-1.5 size-2.5 rounded-full" :class="v.is_overdue ? 'bg-rose-500' : 'bg-clay-500'" />
            <p class="text-xs text-sand-500">
              {{ dateTime(v.visited_at) }}
              <span v-if="v.supervisor"> · {{ v.supervisor }}</span>
              <span v-if="v.is_overdue" class="badge bg-rose-600/10 text-rose-700 ms-1">متأخرة</span>
            </p>
            <p class="text-sm text-sand-800 mt-0.5 whitespace-pre-line">{{ v.findings }}</p>
          </li>
        </ol>
        <p v-else class="text-sm text-sand-500 py-4">لا زيارات مسجّلة.</p>
      </section>
    </div>

    <!-- السجل: كل تغيير على الملف، بمن فعله ومتى -->
    <section v-else-if="tab === 'history'" class="card p-5">
      <ActivityTimeline ref="timeline" subject-type="Orphan" :subject-id="orphan.id" empty-text="لا تغييرات مسجّلة على هذا الملف." />
    </section>

    <OrphanFormModal
      :open="formOpen" :orphan="orphan" :references="references"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>

  <!-- فشل التحميل: رسالة وزرّ لا صفحة فارغة -->
  <div v-else class="card p-10 text-center max-w-md mx-auto mt-10">
    <BaseIcon name="alert" :size="28" class="mx-auto text-rose-600 mb-3" />
    <p class="font-medium mb-1">{{ failed ? 'تعذّر تحميل ملف الطفل.' : 'الملف غير موجود.' }}</p>
    <p class="text-sm text-sand-500 mb-5">قد يكون الرابط قديماً، أو أن الاتصال انقطع.</p>
    <div class="flex justify-center gap-2">
      <button class="btn btn-primary" type="button" @click="load">إعادة المحاولة</button>
      <RouterLink to="/orphans" class="btn btn-outline">العودة إلى الأيتام</RouterLink>
    </div>
  </div>
</template>

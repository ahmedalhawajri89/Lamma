<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { confirmDialog } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, dateTime, years, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import FundingBar from '@/components/FundingBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import DocumentsPanel from '@/components/DocumentsPanel.vue'
import OrphanFormModal from '@/components/OrphanFormModal.vue'

const route = useRoute()
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
 * التوثيق والنشر يعيدان تحميل الملف من الخادم بدل تعديل الحالة محلياً:
 * الخادم قد يغيّر أكثر مما نتوقع (التوثيق يرفع الحالة من «بانتظار التحقق»
 * إلى «نشط»)، وتخمين ذلك في الواجهة يخلق اختلافاً صامتاً.
 */
/**
 * إجراءات لها أثر خارج اللوحة تُؤكَّد بما ستفعله: التوثيق لا يُتراجع عنه،
 * والإخفاء يُزيل الطفل من أمام كفلاء ربما يتصفّحونه الآن.
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
 * ويُخفي الملف من الموقع. كانت الحالة حقلاً في نموذج التعديل يقبل أي قيمة.
 */
const PATHS = {
  graduated: { label: 'تسجيل تخرّج', title: 'تسجيل تخرّج الطفل', message: 'يُغلق الملف للكفالة الجديدة ويُخفى من الموقع. الكفالات القائمة تُراجَع بعدها.' },
  deceased: { label: 'تسجيل وفاة', title: 'تسجيل وفاة الطفل', message: 'يُغلق الملف نهائياً ويُخفى من الموقع، ولا يُعدَّل بعدها إلا بيد المدير.', danger: true },
}
const canChangePath = computed(() => auth.isAdmin && orphan.value && !['deceased', 'withdrawn'].includes(orphan.value.status))

async function changePath(status) {
  const cfg = PATHS[status]
  const answer = await confirmDialog({
    title: cfg.title, message: cfg.message, reason: 'required', reasonLabel: 'السبب', confirmLabel: cfg.label, danger: cfg.danger ?? false,
  })
  if (!answer) return
  await act((id) => admin.orphans.changeStatus(id, status, answer.reason), 'غُيّر مسار الملف.')
}

async function act(fn, successMessage, confirmKey = null) {
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

/**
 * جاهزية التوثيق تُحسب هنا لا في لوحة المستندات.
 *
 * زرّ «توثيق الملف» يعيش في الترويسة، والخادم يرفضه بخطأ ما لم تكن
 * المستندات الثلاثة مُتحقَّقاً منها. زرٌّ يُعرض ليُرفض ليس واجهة، بل فخّ:
 * المستخدم يضغطه فيقرأ خطأً لا يدلّه على المطلوب.
 */
const REQUIRED_DOCUMENTS = ['birth_certificate', 'death_certificate', 'guardianship_proof']

const documentsReady = computed(() =>
  REQUIRED_DOCUMENTS.every((type) =>
    (orphan.value?.documents ?? []).some((d) => d.type === type && d.is_verified),
  ),
)

/* ── التحرير ─────────────────────────────────────────────────────────── */

const formOpen = ref(false)
const references = ref({})

async function loadReferences() {
  // أنواع المستندات بأسمائها لكل من يرى الملف، لا لمن يعدّله وحده
  if (!auth.can('orphans.view')) return

  try {
    const { data } = await admin.references()
    references.value = data.data ?? {}
  } catch {
    // فشل القوائم لا يمنع عرض الملف — نافذة التحرير وحدها تتعطّل
  }
}

async function onSaved() {
  formOpen.value = false
  await load()
}

onMounted(() => {
  load()
  loadReferences()
})

/**
 * رابط أخٍ يفتح الشاشة نفسها بمعرّف آخر، فلا تُركَّب من جديد. بلا هذا
 * يتغيّر الرابط ويبقى الملف القديم معروضاً، وأي إجراء بعدها يقع على الأخ
 * والشاشة تعرض غيره.
 */
watch(() => route.params.id, (id, previous) => {
  if (!id || id === previous) return
  orphan.value = null
  load()
})
</script>

<template>
  <div v-if="loading && !orphan" class="space-y-3">
    <div class="h-8 w-48 bg-sand-100 rounded animate-pulse" />
    <div class="h-64 bg-sand-100 rounded-2xl animate-pulse" />
  </div>

  <div v-else-if="orphan">
    <RouterLink to="/orphans" class="inline-flex items-center gap-1.5 text-sm text-sand-600 hover:text-clay-600 mb-3">
      <BaseIcon name="chevron" :size="15" />
      الأيتام
    </RouterLink>
    <PageHeader :title="orphan.full_name" :subtitle="`${orphan.reference_code} · ${years(orphan.age)}`">
      <template #actions>
        <button
          v-if="auth.can('orphans.update') && (auth.isAdmin || !['deceased', 'withdrawn'].includes(orphan.status))"
          class="btn btn-outline" type="button"
          :disabled="busy" @click="formOpen = true"
        >
          <BaseIcon name="edit" :size="16" /> تحرير
        </button>

        <button
          v-if="!orphan.verified_at && auth.can('orphans.verify')"
          class="btn btn-outline" type="button" :disabled="busy || !documentsReady"
          :title="documentsReady ? 'توثيق الملف' : 'المستندات الثلاثة يجب أن تكون مرفوعة ومُتحقَّقاً منها'"
          @click="act(admin.orphans.verify, 'وُثّق الملف.', 'verify')"
        >
          <BaseIcon name="shield" :size="16" /> توثيق الملف
        </button>

        <button
          v-if="orphan.verified_at && !orphan.published_at && auth.can('orphans.publish')"
          class="btn btn-primary" type="button" :disabled="busy"
          @click="act(admin.orphans.publish, 'نُشر الملف على الموقع.')"
        >
          <BaseIcon name="eye" :size="16" /> نشر على الموقع
        </button>

        <button
          v-if="orphan.published_at && auth.can('orphans.publish')"
          class="btn btn-outline" type="button" :disabled="busy"
          @click="act(admin.orphans.unpublish, 'أُخفي الملف عن الموقع.', 'unpublish')"
        >
          إخفاء عن الموقع
        </button>

        <details v-if="canChangePath" class="relative">
          <summary class="btn btn-ghost list-none cursor-pointer" aria-label="تغيير مسار الملف">
            <BaseIcon name="sliders" :size="16" /> تغيير المسار
          </summary>
          <div class="absolute end-0 mt-1 z-20 w-48 card p-1 shadow-lg">
            <button
              v-for="(cfg, key) in PATHS" :key="key" type="button" :disabled="busy"
              class="w-full text-start px-3 py-2 rounded-lg text-sm hover:bg-sand-50"
              :class="cfg.danger ? 'text-rose-700' : 'text-sand-800'"
              @click="changePath(key)"
            >
              {{ cfg.label }}
            </button>
          </div>
        </details>
      </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-3">
      <!-- الملف -->
      <div class="lg:col-span-2 space-y-4">
        <div class="card p-4">
          <div class="flex gap-4 items-start">
            <img
              v-if="orphan.photo_url" :src="orphan.photo_url" alt=""
              class="size-24 rounded-xl object-cover shrink-0" width="96" height="96"
            />
            <div v-else class="size-24 rounded-xl bg-sand-100 grid place-items-center shrink-0 text-2xl text-sand-400">
              {{ orphan.first_name?.charAt(0) }}
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-2">
                <StatusBadge :label="orphan.status_label" :value="orphan.status" />
                <span v-if="orphan.is_urgent" class="badge bg-rose-600/10 text-rose-700">عاجل</span>
                <span v-if="orphan.verified_at" class="badge bg-teal-600/10 text-teal-700" :title="`وُثّق ${date(orphan.verified_at)}`">
                  موثّق <span class="num">· {{ date(orphan.verified_at) }}</span>
                </span>
                <span v-if="orphan.is_public" class="badge bg-olive-100 text-olive-700">منشور</span>
                <span v-else class="badge bg-sand-100 text-sand-600">غير منشور</span>
              </div>

              <FundingBar :percent="orphan.funded_percent" class="mb-3 max-w-xs" />

              <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                <div><dt class="text-sand-500 text-xs">تاريخ الميلاد</dt><dd>{{ date(orphan.date_of_birth) }}</dd></div>
                <div><dt class="text-sand-500 text-xs">الجنس</dt><dd>{{ orphan.gender_label }}</dd></div>
                <div><dt class="text-sand-500 text-xs">البرنامج</dt><dd>{{ orphan.program?.name }}</dd></div>
                <div><dt class="text-sand-500 text-xs">الحاجة الشهرية</dt><dd class="num">{{ money(orphan.monthly_need) }}</dd></div>
                <div><dt class="text-sand-500 text-xs">الصف</dt><dd>{{ orphan.grade_level ?? EMPTY }}</dd></div>
                <div><dt class="text-sand-500 text-xs">الحالة الصحية</dt><dd>{{ orphan.health_status ?? EMPTY }}</dd></div>
              </dl>
            </div>
          </div>

          <p v-if="orphan.dream" class="mt-4 pt-4 border-t border-sand-100 text-sm">
            <span class="text-sand-500">الحلم:</span> {{ orphan.dream }}
          </p>
          <p v-if="orphan.story" class="mt-2 text-sm leading-relaxed text-sand-700">{{ orphan.story }}</p>
        </div>

        <!-- التقارير -->
        <div class="card p-4">
          <h2 class="text-sm font-semibold mb-3">التقارير الربعية</h2>
          <ul v-if="orphan.reports?.length" class="space-y-2">
            <li v-for="r in orphan.reports" :key="r.id"
                class="flex items-center justify-between gap-3 p-2.5 rounded-lg border border-sand-100">
              <div class="min-w-0">
                <p class="text-sm font-medium truncate">{{ r.title }}</p>
                <p class="text-xs text-sand-500">{{ r.period_label }} · {{ date(r.visit_date) }}</p>
              </div>
              <StatusBadge :label="r.status_label" :value="r.status" />
            </li>
          </ul>
          <p v-else class="text-sm text-sand-500 py-4">لا تقارير بعد.</p>
        </div>

        <!-- الزيارات -->
        <div class="card p-4">
          <h2 class="text-sm font-semibold mb-3">الزيارات الميدانية</h2>
          <ul v-if="orphan.field_visits?.length" class="space-y-2">
            <li v-for="v in orphan.field_visits" :key="v.id" class="p-2.5 rounded-lg border border-sand-100">
              <div class="flex items-center justify-between gap-2 mb-1">
                <span class="text-xs text-sand-500">{{ dateTime(v.visited_at) }}</span>
                <span v-if="v.is_overdue" class="badge bg-rose-600/10 text-rose-700">متأخرة</span>
              </div>
              <p class="text-sm text-sand-700">{{ v.findings }}</p>
              <p v-if="v.supervisor" class="text-xs text-sand-500 mt-1">المشرف: {{ v.supervisor }}</p>
            </li>
          </ul>
          <p v-else class="text-sm text-sand-500 py-4">لا زيارات مسجّلة.</p>
        </div>
      </div>

      <!-- الجانب -->
      <div class="space-y-4">
        <div class="card p-4">
          <h2 class="text-sm font-semibold mb-3">الأسرة</h2>
          <dl class="space-y-2 text-sm">
            <div><dt class="text-xs text-sand-500">رمز الأسرة</dt><dd class="num">{{ orphan.family?.family_code }}</dd></div>
            <div><dt class="text-xs text-sand-500">الوصي</dt>
              <dd>{{ orphan.family?.guardian?.full_name }}
                <span class="text-xs text-sand-500">({{ orphan.family?.guardian?.relation_label }})</span>
              </dd>
            </div>
            <div><dt class="text-xs text-sand-500">الدولة والمدينة</dt>
              <dd>{{ orphan.family?.country?.name }}، {{ orphan.family?.city?.name ?? EMPTY }}</dd>
            </div>
            <div><dt class="text-xs text-sand-500">عدد الأفراد</dt><dd class="num">{{ orphan.family?.members_count }}</dd></div>
            <div><dt class="text-xs text-sand-500">وضع السكن</dt><dd>{{ orphan.family?.housing_status_label }}</dd></div>
            <div><dt class="text-xs text-sand-500">دخل الأسرة الشهري</dt>
              <dd class="num">{{ money(orphan.family?.monthly_income) }}</dd>
            </div>
          </dl>

          <div v-if="orphan.siblings?.length" class="mt-3 pt-3 border-t border-sand-100">
            <p class="text-xs text-sand-500 mb-2">الإخوة في المنصة</p>
            <RouterLink
              v-for="s in orphan.siblings" :key="s.id" :to="`/orphans/${s.id}`"
              class="block text-sm text-clay-700 hover:underline"
            >
              {{ s.first_name }} <span class="num text-xs text-sand-500">· {{ s.reference_code }}</span>
            </RouterLink>
          </div>
        </div>

        <!-- المستندات: ما يجعل «ثلاث مراحل تحقّق» قابلاً للإثبات -->
        <DocumentsPanel :key="orphan.id"
          ref="documents"
          :orphan-id="orphan.id"
          :verified="Boolean(orphan.verified_at)"
          :types="references.document_types ?? []"
          @changed="load"
        />

        <div class="card p-4">
          <h2 class="text-sm font-semibold mb-3">الكفالات</h2>
          <ul v-if="orphan.sponsorships?.length" class="space-y-2">
            <li v-for="s in orphan.sponsorships" :key="s.id" class="text-sm">
              <div class="flex items-center justify-between gap-2">
                <span class="truncate">{{ s.sponsor?.name ?? s.sponsor?.sponsor_code }}</span>
                <span class="num text-sand-600">{{ money(s.monthly_amount) }}</span>
              </div>
              <StatusBadge :label="s.status_label" :value="s.status" class="mt-0.5" />
            </li>
          </ul>
          <p v-else class="text-sm text-sand-500 py-2">لا كفالات بعد، فهذه الحالة تنتظر كافلاً.</p>
        </div>
      </div>
    </div>

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

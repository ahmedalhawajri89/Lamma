<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, dateTime } from '@/utils/format'
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
const busy = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await admin.orphans.show(route.params.id)
    orphan.value = data.data
  } catch (e) {
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
async function act(fn, successMessage) {
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
const families = ref([])

async function loadReferences() {
  if (!auth.can('orphans.update')) return

  try {
    const [refs, fams] = await Promise.all([
      admin.references(),
      admin.families.list({ per_page: 200 }),
    ])
    references.value = refs.data.data ?? {}
    families.value = fams.data.data ?? []
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
</script>

<template>
  <div v-if="loading" class="space-y-3">
    <div class="h-8 w-48 bg-sand-100 rounded animate-pulse" />
    <div class="h-64 bg-sand-100 rounded-2xl animate-pulse" />
  </div>

  <div v-else-if="orphan">
    <PageHeader :title="orphan.full_name" :subtitle="`${orphan.reference_code} · ${orphan.age} سنوات`">
      <template #actions>
        <button
          v-if="auth.can('orphans.update')" class="btn btn-outline" type="button"
          :disabled="busy" @click="formOpen = true"
        >
          <BaseIcon name="edit" :size="16" /> تحرير
        </button>

        <button
          v-if="!orphan.verified_at && auth.can('orphans.verify')"
          class="btn btn-outline" type="button" :disabled="busy || !documentsReady"
          :title="documentsReady ? 'توثيق الملف' : 'المستندات الثلاثة يجب أن تكون مرفوعة ومُتحقَّقاً منها'"
          @click="act(admin.orphans.verify, 'وُثّق الملف.')"
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
          @click="act(admin.orphans.unpublish, 'أُخفي الملف عن الموقع.')"
        >
          إخفاء عن الموقع
        </button>
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
                <span v-if="orphan.is_public" class="badge bg-olive-100 text-olive-700">منشور</span>
                <span v-else class="badge bg-sand-100 text-sand-600">غير منشور</span>
              </div>

              <FundingBar :percent="orphan.funded_percent" class="mb-3 max-w-xs" />

              <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                <div><dt class="text-sand-500 text-xs">تاريخ الميلاد</dt><dd>{{ date(orphan.date_of_birth) }}</dd></div>
                <div><dt class="text-sand-500 text-xs">الجنس</dt><dd>{{ orphan.gender_label }}</dd></div>
                <div><dt class="text-sand-500 text-xs">البرنامج</dt><dd>{{ orphan.program?.name }}</dd></div>
                <div><dt class="text-sand-500 text-xs">الحاجة الشهرية</dt><dd class="num">{{ money(orphan.monthly_need) }}</dd></div>
                <div><dt class="text-sand-500 text-xs">الصف</dt><dd>{{ orphan.grade_level ?? '—' }}</dd></div>
                <div><dt class="text-sand-500 text-xs">الحالة الصحية</dt><dd>{{ orphan.health_status ?? '—' }}</dd></div>
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
              <dd>{{ orphan.family?.country?.name }} — {{ orphan.family?.city?.name ?? '—' }}</dd>
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
        <DocumentsPanel
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
          <p v-else class="text-sm text-sand-500 py-2">لا كفالات — هذه الحالة تنتظر كافلاً.</p>
        </div>
      </div>
    </div>

    <OrphanFormModal
      :open="formOpen" :orphan="orphan" :references="references" :families="families"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

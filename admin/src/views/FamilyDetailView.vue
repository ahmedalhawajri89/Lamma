<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, years, num, EMPTY } from '@/utils/format'
import StatusBadge from '@/components/StatusBadge.vue'
import FundingBar from '@/components/FundingBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import ActivityTimeline from '@/components/ActivityTimeline.vue'
import FamilyFormModal from '@/components/FamilyFormModal.vue'
import OrphanFormModal from '@/components/OrphanFormModal.vue'

/**
 * صفحة الأسرة.
 *
 * كانت النقرة على صفّ الأسرة تفتح نافذة التحرير مباشرة: لا مكان تُرى فيه
 * الأسرة كاملة، بأيتامها وتمويل كلٍّ منهم، ولا طريق لإضافة يتيمٍ إليها إلا
 * من شاشة الأيتام والبحث عنها من جديد.
 */
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const family = ref(null)
const loading = ref(true)
const failed = ref(false)

async function load() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await admin.families.show(route.params.id)
    family.value = data.data
  } catch (e) {
    failed.value = !family.value
    if (e.status !== 404) ui.error(e.userMessage ?? 'تعذّر تحميل الأسرة.')
  } finally {
    loading.value = false
  }
}

const orphans = computed(() => family.value?.orphans ?? [])
const totalNeed = computed(() => orphans.value
  .filter((o) => ['active', 'fully_funded'].includes(o.status))
  .reduce((sum, o) => sum + Number(o.monthly_need ?? 0), 0))

/* ── التحرير والإضافة ────────────────────────────────────────────────── */

const references = ref({})
const editOpen = ref(false)
const addOpen = ref(false)
const timeline = ref(null)

async function loadReferences() {
  try {
    const { data } = await admin.references()
    references.value = data.data ?? {}
  } catch {
    // النافذتان وحدهما تتعطّلان
  }
}

async function onEdited() {
  editOpen.value = false
  await load()
  timeline.value?.reload()
}

function onOrphanCreated(orphan) {
  addOpen.value = false
  if (orphan?.id) router.push(`/orphans/${orphan.id}`)
  else load()
}

onMounted(() => {
  load()
  loadReferences()
})
watch(() => route.params.id, (id, prev) => { if (id && id !== prev) { family.value = null; load() } })
</script>

<template>
  <div v-if="loading && !family" class="space-y-3">
    <div class="h-8 w-48 bg-sand-100 rounded animate-pulse" />
    <div class="h-40 bg-sand-100 rounded-2xl animate-pulse" />
  </div>

  <div v-else-if="family">
    <RouterLink to="/families" class="inline-flex items-center gap-1.5 text-sm text-sand-600 hover:text-clay-600 mb-3">
      <BaseIcon name="chevron" :size="15" />
      الأسر
    </RouterLink>

    <!-- ── الرأس: الوصي والمكان والحاجة ── -->
    <section class="card p-5 mb-4">
      <div class="flex flex-wrap gap-5 items-start">
        <div class="size-16 rounded-2xl bg-sand-100 text-sand-600 grid place-items-center shrink-0">
          <BaseIcon name="home" :size="26" />
        </div>

        <div class="flex-1 min-w-56">
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-2xl font-bold text-sand-900">أسرة {{ family.guardian?.full_name ?? family.family_code }}</h1>
            <span class="badge" :class="family.housing_status === 'displaced' ? 'bg-rose-600/10 text-rose-700' : 'bg-sand-100 text-sand-700'">
              {{ family.housing_status_label }}
            </span>
          </div>
          <p class="text-sm text-sand-500 mt-1">
            <span class="num">{{ family.family_code }}</span>
            · {{ family.country?.name ?? EMPTY }}<template v-if="family.city?.name">، {{ family.city.name }}</template>
            <template v-if="family.address"> · {{ family.address }}</template>
          </p>
        </div>

        <button v-if="auth.can('families.manage')" class="btn btn-outline" type="button" @click="editOpen = true">
          <BaseIcon name="edit" :size="16" /> تحرير
        </button>
      </div>

      <!-- الأرقام التي يُقدَّر بها وضع الأسرة، في سطر واحد -->
      <dl class="mt-5 pt-5 border-t border-sand-100 grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-4 text-sm">
        <div>
          <dt class="text-xs text-sand-500 mb-0.5">الأفراد</dt>
          <dd class="num text-lg font-semibold">{{ family.members_count }}</dd>
        </div>
        <div>
          <dt class="text-xs text-sand-500 mb-0.5">دخل الأسرة الشهري</dt>
          <dd class="num text-lg font-semibold">{{ family.monthly_income !== null ? money(family.monthly_income) : EMPTY }}</dd>
        </div>
        <div>
          <dt class="text-xs text-sand-500 mb-0.5">دخل الفرد</dt>
          <dd class="num text-lg font-semibold">{{ family.per_capita_income !== null ? money(family.per_capita_income) : EMPTY }}</dd>
        </div>
        <div>
          <dt class="text-xs text-sand-500 mb-0.5">حاجة أيتامها الشهرية</dt>
          <dd class="num text-lg font-semibold">{{ money(totalNeed) }}</dd>
        </div>
      </dl>
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
      <!-- الأيتام -->
      <section class="card lg:col-span-2 overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-sand-100">
          <h2 class="text-sm font-semibold">الأيتام <span class="num text-sand-500 font-normal">({{ num(orphans.length) }})</span></h2>
          <button v-if="auth.can('orphans.create')" class="btn btn-outline !py-1.5 text-sm" type="button" @click="addOpen = true">
            <BaseIcon name="plus" :size="15" /> إضافة يتيم
          </button>
        </div>

        <ul v-if="orphans.length" class="divide-y divide-sand-100">
          <li v-for="o in orphans" :key="o.id">
            <RouterLink :to="`/orphans/${o.id}`" class="flex flex-wrap items-center gap-x-5 gap-y-2 px-5 py-3.5 hover:bg-sand-25">
              <span class="flex items-center gap-3 flex-1 min-w-48">
                <img v-if="o.photo_url" :src="o.photo_url" alt="" class="size-9 rounded-full object-cover" width="36" height="36" />
                <span v-else class="size-9 rounded-full bg-clay-50 text-clay-700 grid place-items-center text-sm font-semibold">{{ o.first_name?.charAt(0) }}</span>
                <span class="min-w-0">
                  <span class="block font-medium text-sand-900">{{ o.full_name ?? o.first_name }}</span>
                  <span class="block text-xs text-sand-500"><span class="num">{{ o.reference_code }}</span> · {{ years(o.age) }} · {{ o.program?.name ?? EMPTY }}</span>
                </span>
              </span>
              <span class="w-40">
                <FundingBar v-if="['active', 'fully_funded'].includes(o.status)" :percent="o.funded_percent" />
                <span v-else class="text-xs text-sand-400">لم يُنشر بعد</span>
              </span>
              <StatusBadge :label="o.status_label" :value="o.status" />
            </RouterLink>
          </li>
        </ul>

        <div v-else class="px-5 py-10 text-center">
          <p class="text-sm text-sand-500">لا أيتام مسجّلين لهذه الأسرة بعد.</p>
          <button v-if="auth.can('orphans.create')" class="btn btn-primary mt-4" type="button" @click="addOpen = true">
            <BaseIcon name="plus" :size="16" /> أضف أول يتيم
          </button>
        </div>
      </section>

      <div class="space-y-4">
        <!-- الوصي -->
        <section class="card p-5">
          <h2 class="text-sm font-semibold mb-3">الوصي</h2>
          <dl class="space-y-2.5 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-sand-500">الاسم</dt><dd>{{ family.guardian?.full_name ?? EMPTY }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-sand-500">الصلة</dt><dd>{{ family.guardian?.relation_label ?? EMPTY }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-sand-500">العمل</dt><dd>{{ family.guardian?.occupation || EMPTY }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-sand-500">دخله الشهري</dt>
              <dd class="num">{{ family.guardian?.monthly_income !== null && family.guardian?.monthly_income !== undefined ? money(family.guardian.monthly_income) : EMPTY }}</dd>
            </div>
            <!-- الهاتف لمن يتعامل مع الأسر وحده: الخادم لا يرسله للمحاسب -->
            <div v-if="family.guardian?.phone !== undefined" class="flex justify-between gap-3"><dt class="text-sand-500">الهاتف</dt>
              <dd>
                <a v-if="family.guardian.phone" :href="`tel:${family.guardian.phone}`" class="num text-clay-700 hover:underline" dir="ltr">{{ family.guardian.phone }}</a>
                <span v-else>{{ EMPTY }}</span>
              </dd>
            </div>
          </dl>
        </section>

        <!-- السجل -->
        <section v-if="auth.can('activity.view')" class="card p-5">
          <h2 class="text-sm font-semibold mb-3">السجل</h2>
          <ActivityTimeline ref="timeline" subject-type="Family" :subject-id="family.id" empty-text="لا تعديلات مسجّلة على هذه الأسرة." />
        </section>
      </div>
    </div>

    <p class="text-xs text-sand-400 mt-4">سُجّلت في {{ date(family.created_at) }}</p>

    <FamilyFormModal
      :open="editOpen" :family="family" :references="references"
      @close="editOpen = false" @saved="onEdited"
    />
    <OrphanFormModal
      :open="addOpen" :references="references" :preset-family="family"
      @close="addOpen = false" @saved="onOrphanCreated"
    />
  </div>

  <div v-else class="card p-10 text-center max-w-md mx-auto mt-10">
    <BaseIcon name="alert" :size="28" class="mx-auto text-rose-600 mb-3" />
    <p class="font-medium mb-1">{{ failed ? 'تعذّر تحميل الأسرة.' : 'الأسرة غير موجودة.' }}</p>
    <p class="text-sm text-sand-500 mb-5">قد يكون الرابط قديماً، أو أن الاتصال انقطع.</p>
    <div class="flex justify-center gap-2">
      <button class="btn btn-primary" type="button" @click="load">إعادة المحاولة</button>
      <RouterLink to="/families" class="btn btn-outline">العودة إلى الأسر</RouterLink>
    </div>
  </div>
</template>

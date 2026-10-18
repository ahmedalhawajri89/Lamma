<script setup>
/**
 * صفحة الكافل.
 *
 * كانت نافذةً فيها كفالاته وملاحظة. هنا ما يحتاجه من يتواصل معه: كيف
 * يُصل إليه، وما التزم به وما قدّمه، وكفالاته بحالاتها، وآخر دفعاته بما
 * فشل منها وما استُرد، وملاحظات الإدارة وتفضيلاته.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, date, num, relative, EMPTY } from '@/utils/format'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const route = useRoute()
const auth = useAuthStore()
const ui = useUiStore()

const sponsor = ref(null)
const loading = ref(true)
const failed = ref(false)

async function load() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await admin.sponsors.show(route.params.id)
    sponsor.value = data.data
    notes.value = data.data.internal_notes ?? ''
    savedNotes.value = notes.value
  } catch (e) {
    failed.value = !sponsor.value
    if (e.status !== 404) ui.error(e.userMessage ?? 'تعذّر تحميل الكافل.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => route.params.id, (id, prev) => { if (id && id !== prev) { sponsor.value = null; load() } })

const LIVE = ['active', 'paused', 'pending']
const live = computed(() => (sponsor.value?.sponsorships ?? []).filter((s) => LIVE.includes(s.status)))
const ended = computed(() => (sponsor.value?.sponsorships ?? []).filter((s) => !LIVE.includes(s.status)))
const CYCLE = { 1: 'شهرياً', 3: 'كل 3 أشهر', 12: 'سنوياً' }
const PAY_TONE = { succeeded: 'text-olive-700', failed: 'text-rose-700', refunded: 'text-sand-500', pending: 'text-amber-700' }

/* ── الملاحظات والتفضيلات ─────────────────────────────────────────── */

const notes = ref('')
const savedNotes = ref('')
const saving = ref(false)

async function save(payload, message) {
  saving.value = true
  try {
    const { data } = await admin.sponsors.update(sponsor.value.id, payload)
    Object.assign(sponsor.value, { ...data.data, sponsorships: sponsor.value.sponsorships, recent_payments: sponsor.value.recent_payments })
    if ('internal_notes' in payload) savedNotes.value = notes.value
    ui.success(message)
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="loading && !sponsor" class="space-y-3">
    <div class="h-8 w-48 bg-sand-100 rounded animate-pulse" />
    <div class="h-40 bg-sand-100 rounded-2xl animate-pulse" />
  </div>

  <div v-else-if="sponsor">
    <RouterLink to="/sponsors" class="inline-flex items-center gap-1.5 text-sm text-sand-600 hover:text-clay-600 mb-3">
      <BaseIcon name="chevron" :size="15" />
      الكفلاء
    </RouterLink>

    <!-- ── الرأس: من هو، وكيف يُصل إليه ── -->
    <section class="card p-5 mb-4">
      <div class="flex flex-wrap gap-5 items-start">
        <div class="size-16 rounded-2xl bg-amber-50 text-amber-800 grid place-items-center shrink-0 text-2xl font-semibold">
          {{ sponsor.name?.charAt(0) ?? '؟' }}
        </div>
        <div class="flex-1 min-w-56">
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-2xl font-bold text-sand-900">{{ sponsor.name ?? EMPTY }}</h1>
            <span v-if="sponsor.is_anonymous" class="badge bg-sand-100 text-sand-600">مجهول للعامة</span>
            <span v-if="sponsor.prefers_zakat" class="badge bg-amber-50 text-amber-800">يفضّل الزكاة</span>
          </div>
          <p class="text-sm text-sand-500 mt-1">
            <span class="num">{{ sponsor.sponsor_code }}</span>
            · {{ sponsor.country?.name ?? EMPTY }}<template v-if="sponsor.city">، {{ sponsor.city }}</template>
            · كافل منذ {{ date(sponsor.sponsoring_since) }}
          </p>
          <p class="text-sm mt-2 flex flex-wrap gap-x-4 gap-y-1">
            <a v-if="sponsor.email" :href="`mailto:${sponsor.email}`" class="text-clay-700 hover:underline num" dir="ltr">{{ sponsor.email }}</a>
            <a v-if="sponsor.phone" :href="`tel:${sponsor.phone}`" class="text-clay-700 hover:underline num" dir="ltr">{{ sponsor.phone }}</a>
            <span class="text-sand-500">آخر دخول: {{ sponsor.last_login_at ? relative(sponsor.last_login_at) : 'لم يدخل بعد' }}</span>
          </p>
        </div>
      </div>

      <dl class="mt-5 pt-5 border-t border-sand-100 grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-4">
        <div><dt class="text-xs text-sand-500 mb-0.5">كفالات قائمة</dt><dd class="num text-lg font-semibold">{{ num(live.length) }}</dd></div>
        <div><dt class="text-xs text-sand-500 mb-0.5">التزام شهري</dt><dd class="num text-lg font-semibold">{{ money(sponsor.monthly_commitment ?? 0) }}</dd></div>
        <div><dt class="text-xs text-sand-500 mb-0.5">ما قدّمه حتى اليوم</dt><dd class="num text-lg font-semibold">{{ money(sponsor.total_given ?? 0) }}</dd></div>
        <div><dt class="text-xs text-sand-500 mb-0.5">دفعات مكتملة</dt><dd class="num text-lg font-semibold">{{ num(sponsor.payments_count ?? 0) }}</dd></div>
      </dl>
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
      <div class="lg:col-span-2 space-y-4">
        <!-- الكفالات -->
        <section class="card overflow-hidden">
          <h2 class="text-sm font-semibold px-5 py-4 border-b border-sand-100">الكفالات</h2>
          <ul v-if="sponsor.sponsorships?.length" class="divide-y divide-sand-100">
            <li
              v-for="s in [...live, ...ended]" :key="s.id"
              class="flex flex-wrap items-center gap-x-5 gap-y-1 px-5 py-3"
              :class="LIVE.includes(s.status) ? '' : 'opacity-70'"
            >
              <span class="flex items-center gap-3 flex-1 min-w-48">
                <img v-if="s.orphan?.photo_url" :src="s.orphan.photo_url" alt="" class="size-8 rounded-full object-cover" width="32" height="32" />
                <span v-else class="size-8 rounded-full bg-clay-50 text-clay-700 grid place-items-center text-xs font-semibold">{{ s.orphan?.name?.charAt(0) }}</span>
                <span class="min-w-0">
                  <RouterLink :to="`/orphans/${s.orphan?.id}`" class="block text-sm font-medium text-sand-900 hover:text-clay-700">{{ s.orphan?.name }}</RouterLink>
                  <span class="block text-xs text-sand-500">
                    <span class="num">{{ s.reference_code }}</span> · منذ {{ date(s.started_at) }}
                    <template v-if="s.ended_at"> · انتهت {{ date(s.ended_at) }}</template>
                  </span>
                </span>
              </span>
              <span class="text-sm text-end">
                <span class="num font-medium">{{ money(s.monthly_amount, s.currency) }}</span>
                <span class="text-xs text-sand-500"> شهرياً{{ s.interval_months > 1 ? ` · يدفع ${CYCLE[s.interval_months]}` : '' }}</span>
                <span v-if="s.status === 'active' && s.next_due_at" class="block text-xs text-sand-500">القسط القادم {{ date(s.next_due_at) }}</span>
              </span>
              <StatusBadge :label="s.status_label" :value="s.status" />
            </li>
          </ul>
          <p v-else class="px-5 py-8 text-center text-sm text-sand-500">لم يكفل بعد. سجّل ولم يختر طفلاً.</p>
          <div v-if="sponsor.sponsorships?.length" class="px-5 py-3 border-t border-sand-100 bg-sand-25">
            <RouterLink :to="`/sponsorships?q=${sponsor.sponsor_code}`" class="text-sm text-clay-700 hover:underline">فتح في شاشة الكفالات</RouterLink>
          </div>
        </section>

        <!-- آخر الدفعات -->
        <section class="card overflow-hidden">
          <h2 class="text-sm font-semibold px-5 py-4 border-b border-sand-100">آخر الدفعات</h2>
          <ul v-if="sponsor.recent_payments?.length" class="divide-y divide-sand-100">
            <li v-for="p in sponsor.recent_payments" :key="p.id" class="flex flex-wrap items-center gap-x-5 gap-y-1 px-5 py-2.5 text-sm">
              <span class="w-28 text-sand-600">{{ date(p.paid_at ?? p.created_at) }}</span>
              <span class="flex-1 min-w-32 text-sand-800">{{ p.sponsorship?.orphan?.name ?? EMPTY }}</span>
              <span class="num font-medium">{{ money(p.amount, p.currency) }}</span>
              <span class="text-xs text-sand-500 w-24">{{ p.card ?? p.method_label }}</span>
              <span class="text-xs font-medium w-24" :class="PAY_TONE[p.status] ?? 'text-sand-600'">{{ p.status_label }}</span>
            </li>
          </ul>
          <p v-else class="px-5 py-8 text-center text-sm text-sand-500">لا دفعات بعد.</p>
          <div v-if="sponsor.recent_payments?.length" class="px-5 py-3 border-t border-sand-100 bg-sand-25">
            <RouterLink :to="`/payments?q=${sponsor.sponsor_code}`" class="text-sm text-clay-700 hover:underline">كل دفعاته في شاشة المدفوعات</RouterLink>
          </div>
        </section>
      </div>

      <!-- الإدارة -->
      <aside class="space-y-4">
        <section class="card p-5">
          <label class="block text-sm font-semibold mb-1" for="sponsorNotes">ملاحظات الإدارة</label>
          <p class="text-xs text-sand-500 mb-2">لا يراها الكافل.</p>
          <textarea
            id="sponsorNotes" v-model="notes" class="input" rows="5" maxlength="2000"
            :readonly="!auth.can('sponsors.manage')" placeholder="ما يفيد من يتواصل معه بعدك…"
          />
          <button
            v-if="auth.can('sponsors.manage')" class="btn btn-primary mt-3 w-full justify-center" type="button"
            :disabled="saving || notes === savedNotes" @click="save({ internal_notes: notes || null }, 'حُفظت الملاحظات.')"
          >حفظ الملاحظات</button>
        </section>

        <section v-if="auth.can('sponsors.manage')" class="card p-5 space-y-3">
          <h2 class="text-sm font-semibold">التفضيلات</h2>
          <label class="flex items-start gap-3 text-sm cursor-pointer">
            <input type="checkbox" class="mt-1 accent-clay-600" :checked="sponsor.is_anonymous" :disabled="saving"
                   @change="save({ is_anonymous: $event.target.checked }, 'حُفظ التفضيل.')" />
            <span>مجهول للعامة<span class="block text-xs text-sand-500">لا يظهر اسمه على الموقع.</span></span>
          </label>
          <label class="flex items-start gap-3 text-sm cursor-pointer">
            <input type="checkbox" class="mt-1 accent-clay-600" :checked="sponsor.prefers_zakat" :disabled="saving"
                   @change="save({ prefers_zakat: $event.target.checked }, 'حُفظ التفضيل.')" />
            <span>يفضّل الزكاة<span class="block text-xs text-sand-500">تُقترح له الحالات المستحقة للزكاة.</span></span>
          </label>
        </section>
      </aside>
    </div>
  </div>

  <div v-else class="card p-10 text-center max-w-md mx-auto mt-10">
    <BaseIcon name="alert" :size="28" class="mx-auto text-rose-600 mb-3" />
    <p class="font-medium mb-1">{{ failed ? 'تعذّر تحميل الكافل.' : 'الكافل غير موجود.' }}</p>
    <div class="flex justify-center gap-2 mt-5">
      <button class="btn btn-primary" type="button" @click="load">إعادة المحاولة</button>
      <RouterLink to="/sponsors" class="btn btn-outline">العودة إلى الكفلاء</RouterLink>
    </div>
  </div>
</template>

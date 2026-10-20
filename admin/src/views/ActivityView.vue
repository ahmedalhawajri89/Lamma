<script setup>
/**
 * سجل النشاط: من فعل ماذا ومتى.
 *
 * في الأعلى نظرة عامة: إيقاع العمل في آخر ثلاثين يوماً (والضغط على يوم
 * يعرضه وحده)، ومن الأكثر عملاً. ثم أقسام بأعدادها، وشريحة «الحساسة» لما
 * يستحق نظرة ثانية: المال والحسابات والإعدادات، والاسترداد، وكل بيانات
 * خرجت من المنصة بتصدير أو تنزيل. والسجل نفسه مجمَّع بالأيام كدفتر، لكل
 * سطر ساعته وفاعله وسجلّه وما تغيّر فيه.
 *
 * الخادم يصوغ كل سطر (ActivityFormatter)، وهذه الشاشة تعرض وتفلتر فقط.
 */
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useUiStore } from '@/stores/ui'
import { saveBlob } from '@/utils/download'
import { dateTime, num } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import PeriodSelect from '@/components/PeriodSelect.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const ui = useUiStore()

const DEFAULTS = { group: '', causer_id: '', from: '', to: '', sensitive: false, subject_type: '', subject_id: '' }
const { items, meta, loading, error, filters, reset, load } = useResource(admin.activity, DEFAULTS)

const dirty = computed(() => Boolean(filters.causer_id || filters.from || filters.to || filters.sensitive || filters.subject_type))

// قوائم الفلاتر والنظرة العامة تصل مع الصفحة وتبقى أثناء التحميل التالي
const groups = ref([])
const actors = ref([])
const overview = ref(null)
watch(meta, (m) => {
  if (m?.groups?.length) groups.value = m.groups
  if (m?.actors) actors.value = m.actors
  if (m?.daily) overview.value = m
})

/* ── النظرة العامة ───────────────────────────────────────────────────── */

const peak = computed(() => Math.max(1, ...(overview.value?.daily ?? []).map((d) => d.count)))
const dayFmt = new Intl.DateTimeFormat('ar-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long' })
const shortFmt = new Intl.DateTimeFormat('ar-u-nu-latn', { day: 'numeric', month: 'short' })

/** يومٌ واحد: الضغط على عموده يعرضه، والضغط ثانيةً يعيد الفترة */
const pickedDay = computed(() => (filters.from && filters.from === filters.to ? filters.from : null))
function pickDay(d) {
  if (pickedDay.value === d) { filters.from = ''; filters.to = '' } else { filters.from = d; filters.to = d }
}
function pickActor(id) { filters.causer_id = String(filters.causer_id) === String(id) ? '' : id }
const topMax = computed(() => Math.max(1, ...(overview.value?.top_actors ?? []).map((a) => a.count)))

/* ── السجل مجمَّعاً بالأيام ──────────────────────────────────────────── */

const localDate = (iso) => {
  const d = new Date(iso)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
const todayKey = localDate(new Date())
const yesterdayKey = localDate(new Date(Date.now() - 86_400_000))
function dayLabel(key) {
  if (key === todayKey) return 'اليوم'
  if (key === yesterdayKey) return 'أمس'
  return dayFmt.format(new Date(`${key}T12:00:00`))
}

const days = computed(() => {
  const out = []
  for (const a of items.value) {
    const key = localDate(a.created_at)
    if (out.at(-1)?.key !== key) out.push({ key, label: dayLabel(key), rows: [] })
    out.at(-1).rows.push(a)
  }
  return out
})

const timeFmt = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' })
const time = (iso) => timeFmt.format(new Date(iso))

/** أيقونة الفعل ولونه: الأخضر لما يُعتمد، والأحمر لما يُنقض أو يُسترد */
const EVENTS = {
  created: ['plus', 'bg-[#064e3b]/10 text-[#064e3b]'],
  updated: ['edit', 'bg-sand-100 text-sand-600'],
  deleted: ['trash', 'bg-rose-100 text-rose-700'],
  approved: ['check', 'bg-olive-50 text-olive-700'],
  published: ['globe', 'bg-olive-50 text-olive-700'],
  document_verified: ['file-check', 'bg-olive-50 text-olive-700'],
  document_rejected: ['close', 'bg-rose-100 text-rose-700'],
  returned: ['undo', 'bg-amber-100 text-amber-800'],
  reviewed: ['chat', 'bg-[#176b56]/10 text-[#176b56]'],
  delivered: ['mail', 'bg-[#176b56]/10 text-[#176b56]'],
  replied: ['mail', 'bg-[#176b56]/10 text-[#176b56]'],
  recorded: ['receipt', 'bg-[#D5A85C]/15 text-[#a8741a]'],
  refunded: ['undo', 'bg-rose-100 text-rose-700'],
  status_changed: ['sliders', 'bg-amber-100 text-amber-800'],
  settings_updated: ['sliders', 'bg-amber-100 text-amber-800'],
  invited: ['mail', 'bg-sand-100 text-sand-600'],
  reordered: ['sliders', 'bg-sand-100 text-sand-600'],
}
function look(a) {
  if (/^(صدّر|نزّل)/.test(a.action)) return ['download', 'bg-amber-100 text-amber-800']
  return EVENTS[a.event] ?? ['history', 'bg-sand-100 text-sand-600']
}

/* ── التصدير ─────────────────────────────────────────────────────────── */

const exporting = ref(false)
async function exportCsv() {
  exporting.value = true
  try {
    const params = Object.fromEntries(Object.entries(filters).filter(([k, v]) => k !== 'page' && v !== '' && v !== false))
    saveBlob(await admin.activityExport(params), 'activity.csv')
    ui.success('صُدّر السجل. والتصدير نفسه سُجّل فيه.')
    load()
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر التصدير.')
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader title="سجل النشاط" subtitle="من فعل ماذا ومتى، وقيمة كل حقل قبل التغيير وبعده.">
      <template #actions>
        <button class="btn btn-outline" type="button" :disabled="exporting || !meta.total" @click="exportCsv">
          <BaseIcon :name="exporting ? 'loader' : 'download'" :size="15" :class="{ 'animate-spin': exporting }" />
          تصدير CSV
        </button>
      </template>
    </PageHeader>

    <!-- ══ نظرة عامة ══ -->
    <section v-if="overview" class="rounded-2xl border border-sand-200 bg-sand-0 grid lg:grid-cols-[minmax(0,1fr)_20rem] mb-5 overflow-hidden" aria-label="نظرة عامة على النشاط">
      <div class="p-5">
        <div class="flex flex-wrap items-end gap-x-8 gap-y-2">
          <div>
            <p class="text-xs text-sand-500">اليوم</p>
            <p class="text-2xl font-bold num">{{ num(overview.today_count) }} <span class="text-sm font-normal text-sand-500">إجراء</span></p>
          </div>
          <div>
            <p class="text-xs text-sand-500">آخر 7 أيام</p>
            <p class="text-2xl font-bold num">{{ num(overview.week_count) }}</p>
          </div>
          <p class="text-xs text-sand-500 ms-auto">
            {{ pickedDay ? 'يُعرض يوم واحد، اضغطه ثانيةً للعودة' : 'آخر 30 يوماً، اضغط يوماً لعرضه وحده' }}
          </p>
        </div>

        <!-- إيقاع العمل: عمود لكل يوم -->
        <div class="mt-4 flex items-end gap-[3px] h-24" role="list" aria-label="عدد الإجراءات في كل يوم">
          <button
            v-for="d in overview.daily" :key="d.date" type="button" role="listitem"
            class="group flex-1 h-full flex items-end rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-clay-500"
            :title="`${shortFmt.format(new Date(`${d.date}T12:00:00`))}: ${d.count} إجراء`"
            :aria-label="`${dayLabel(d.date)}: ${d.count} إجراء`" :aria-pressed="pickedDay === d.date"
            @click="pickDay(d.date)"
          >
            <span
              class="w-full rounded-t-[3px] transition-colors"
              :class="pickedDay === d.date ? 'bg-[#D5A85C]' : pickedDay ? 'bg-sand-200 group-hover:bg-sand-300' : d.date === todayKey ? 'bg-[#064e3b]' : 'bg-[#176b56]/45 group-hover:bg-[#176b56]'"
              :style="{ height: d.count ? `${Math.max(6, (d.count / peak) * 100)}%` : '2px' }"
            />
          </button>
        </div>
        <div class="flex justify-between text-[0.7rem] text-sand-400 mt-1.5">
          <span>{{ shortFmt.format(new Date(`${overview.daily[0].date}T12:00:00`)) }}</span>
          <span>اليوم</span>
        </div>
      </div>

      <!-- من يعمل -->
      <div class="border-t lg:border-t-0 lg:border-s border-sand-100 p-5 bg-sand-25">
        <p class="text-sm font-semibold mb-3">الأكثر نشاطاً <span class="text-xs font-normal text-sand-500">{{ filters.from || filters.to ? 'في الفترة' : 'في كل السجل' }}</span></p>
        <ul v-if="overview.top_actors.length" class="space-y-2">
          <li v-for="a in overview.top_actors" :key="a.id">
            <button
              type="button" class="w-full flex items-center gap-2.5 rounded-lg px-2 py-1.5 -mx-2 text-start transition-colors"
              :class="String(filters.causer_id) === String(a.id) ? 'bg-clay-50' : 'hover:bg-sand-100'"
              :aria-pressed="String(filters.causer_id) === String(a.id)" @click="pickActor(a.id)"
            >
              <span class="size-7 shrink-0 rounded-full bg-[#064e3b]/10 text-[#064e3b] grid place-items-center text-xs font-semibold" aria-hidden="true">{{ a.name.trim().charAt(0) }}</span>
              <span class="flex-1 min-w-0">
                <span class="flex justify-between text-sm"><span class="truncate">{{ a.name }}</span><span class="num text-sand-600">{{ num(a.count) }}</span></span>
                <span class="block h-1 rounded-full bg-sand-100 mt-1"><span class="block h-full rounded-full bg-[#176b56]" :style="{ width: `${(a.count / topMax) * 100}%` }" /></span>
              </span>
            </button>
          </li>
        </ul>
        <p v-else class="text-sm text-sand-500">لا أحد بعد.</p>
      </div>
    </section>

    <!-- ══ الأقسام والفلاتر ══ -->
    <StatusTabs
      v-model="filters.group" :options="groups" :counts="overview?.group_counts ?? {}" :total="overview?.all_count ?? null"
      label="قسم السجل"
    />

    <FilterBar :has-search="false" :dirty="dirty" @reset="reset">
      <select v-model="filters.causer_id" class="input !w-auto text-sm" aria-label="من قام به">
        <option value="">كل الفريق</option>
        <option v-for="u in actors" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
      </select>
      <PeriodSelect v-model:from="filters.from" v-model:to="filters.to" :presets="['week', 'days30', 'month', 'last_month', 'year']" />
      <button type="button" class="chip" :aria-pressed="filters.sensitive" @click="filters.sensitive = !filters.sensitive">
        <BaseIcon name="shield" :size="13" /> الحساسة <span class="chip__count">{{ num(overview?.sensitive_count ?? 0) }}</span>
      </button>
    </FilterBar>

    <!-- ══ السجل ══ -->
    <div v-if="loading && !items.length" class="rounded-2xl border border-sand-200 bg-sand-0 divide-y divide-sand-100" aria-hidden="true">
      <div v-for="n in 6" :key="n" class="h-16 animate-pulse bg-sand-50" />
    </div>

    <div v-else-if="error" class="card p-8 text-center" role="alert">
      <p class="font-medium text-sand-900">تعذّر تحميل السجل</p>
      <p class="text-sm text-sand-500 mt-1">{{ error }}</p>
      <button class="btn btn-outline mt-4" type="button" @click="load">إعادة المحاولة</button>
    </div>

    <div v-else-if="days.length" class="space-y-5" :class="{ 'opacity-60': loading }">
      <section v-for="d in days" :key="d.key" class="rounded-2xl border border-sand-200 bg-sand-0 overflow-hidden" :aria-label="d.label">
        <h2 class="flex items-baseline gap-2 px-5 py-3 bg-sand-25 border-b border-sand-100 text-sm">
          <span class="font-semibold text-sand-900">{{ d.label }}</span>
          <span class="text-xs text-sand-500"><span class="num">{{ d.rows.length }}</span> إجراء</span>
        </h2>

        <ol class="divide-y divide-sand-100">
          <li v-for="a in d.rows" :key="a.id" class="flex gap-3 sm:gap-4 px-5 py-3.5" :class="{ 'bg-amber-50/40': a.sensitive }">
            <time class="w-11 shrink-0 pt-1.5 text-xs text-sand-500 num" :datetime="a.created_at" :title="dateTime(a.created_at)">{{ time(a.created_at) }}</time>
            <span class="size-8 shrink-0 grid place-items-center rounded-lg" :class="look(a)[1]" aria-hidden="true">
              <BaseIcon :name="look(a)[0]" :size="15" />
            </span>

            <div class="min-w-0 flex-1">
              <p class="text-sm leading-relaxed">
                <button
                  v-if="a.actor_id" type="button" class="font-semibold text-sand-900 hover:text-clay-700"
                  :title="`نشاط ${a.actor} وحده`" @click="pickActor(a.actor_id)"
                >{{ a.actor }}</button>
                <span v-else class="font-semibold text-sand-900">{{ a.actor }}</span>
                {{ ' ' }}<span class="text-sand-700">{{ a.action }}</span>
                <template v-if="a.subject_name">
                  {{ ' ' }}<RouterLink v-if="a.subject_path" :to="a.subject_path" class="text-clay-700 hover:underline font-medium">{{ a.subject_name }}</RouterLink>
                  <span v-else class="font-medium text-sand-800">{{ a.subject_name }}</span>
                </template>
              </p>

              <div v-if="a.changes.length" class="mt-2 inline-flex flex-col gap-1 rounded-lg bg-sand-25 border border-sand-100 px-3 py-2 text-[0.8rem] max-w-full">
                <p v-for="c in a.changes" :key="c.field" class="flex flex-wrap items-baseline gap-x-2">
                  <span class="text-sand-500">{{ c.label }}</span>
                  <template v-if="c.before !== null && c.after !== null">
                    <span class="text-sand-400 line-through">{{ c.before }}</span>
                    <span class="text-sand-400" aria-label="صار">←</span>
                    <span class="text-sand-900 font-medium">{{ c.after }}</span>
                  </template>
                  <span v-else-if="c.after !== null" class="text-sand-900 font-medium">{{ c.after }}</span>
                  <span v-else class="text-sand-500">كان: {{ c.before }}</span>
                </p>
              </div>

              <p v-if="a.note" class="mt-2 text-[0.8rem] text-sand-700 border-s-2 border-sand-300 ps-3">{{ a.note }}</p>
            </div>

            <div class="hidden sm:flex flex-col items-end gap-1 shrink-0">
              <span v-if="a.subject_label" class="text-[0.7rem] rounded-md bg-sand-100 text-sand-600 px-2 py-0.5">{{ a.subject_label }}</span>
              <span v-if="a.sensitive" class="inline-flex items-center gap-1 text-[0.7rem] rounded-md bg-amber-100 text-amber-800 px-2 py-0.5">
                <BaseIcon name="shield" :size="11" /> حساس
              </span>
            </div>
          </li>
        </ol>
      </section>
    </div>

    <div v-else class="rounded-2xl border border-sand-200 bg-sand-0 p-10 text-center">
      <p class="font-medium text-sand-900">{{ dirty || filters.group ? 'لا نشاط يطابق هذه الفلاتر' : 'السجل فارغ' }}</p>
      <p class="text-sm text-sand-500 mt-1">{{ dirty || filters.group ? 'جرّب فترة أوسع أو أزل فلتراً.' : 'يمتلئ مع أول تعديل يجري من اللوحة.' }}</p>
    </div>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

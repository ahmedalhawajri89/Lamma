<script setup>
/**
 * لوحة المشرف الميداني: عمل الأسبوع في بلده.
 *
 * كانت أربعة أرقام وقائمة حالات عاجلة. هنا ما يخرج المشرف لأجله، بالترتيب:
 *  1. من أزور: الأقرب موعداً (ومن فات موعده أو لم يُزر قط أولاً)، وزرّ
 *     «سجّل زيارة» يفتح النموذج والطفل مختار فيه.
 *  2. تقارير الربع: كم كُتب من أطفال بلده، وما أُرجع إليه بملاحظة المراجع.
 *  3. ما رصدته الزيارات من احتياجات في الشهر الأخير.
 *  4. الملفات: مسوّدات، وما ينتظر التوثيق، ومستندات مرفوضة، وأطفال بلا صورة.
 *  5. الحالات العاجلة التي تنتظر كافلاً في بلده.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { num, relative } from '@/utils/format'
import BaseIcon from './BaseIcon.vue'

const props = defineProps({
  field: { type: Object, required: true },
  urgent: { type: Array, default: () => [] },
})

const v = computed(() => props.field.visits)
const r = computed(() => props.field.reports)
const f = computed(() => props.field.files)
const fl = computed(() => props.field.flagged)

const dayFmt = new Intl.DateTimeFormat('ar-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long' })
/** موعد الزيارة بجملة: «لم يُزر قط»، «فات موعدها بـ 5 أيام»، «اليوم»، «بعد 3 أيام» */
function due(c) {
  if (!c.last_visited_at) return { text: 'لم يُزر قط', tone: 'text-rose-700 bg-rose-50' }
  if (c.days === null) return { text: 'بلا موعد', tone: 'text-sand-600 bg-sand-100' }
  if (c.days < 0) return { text: `فات موعدها بـ ${-c.days} ${-c.days === 1 ? 'يوم' : 'أيام'}`, tone: 'text-rose-700 bg-rose-50' }
  if (c.days === 0) return { text: 'اليوم', tone: 'text-[#a8741a] bg-[#D5A85C]/15' }
  if (c.days <= 7) return { text: `بعد ${c.days} ${c.days === 1 ? 'يوم' : 'أيام'}`, tone: 'text-[#a8741a] bg-[#D5A85C]/15' }
  return { text: dayFmt.format(new Date(`${c.next_due}T12:00:00`)), tone: 'text-sand-600 bg-sand-100' }
}
const recordLink = (c) => ({ path: '/visits', query: { record: c.id, name: c.name, ref: c.reference_code } })

/** تقدّم الربع: ما أُرسل، والمسوّدات، وما لم يُبدأ */
const pct = (n) => (r.value.expected ? `${(n / r.value.expected) * 100}%` : '0%')
const done = computed(() => (r.value.expected ? Math.round((r.value.sent / r.value.expected) * 100) : 0))

const fileRows = computed(() => [
  { key: 'rejected', label: 'مستندات رُفضت، تُعاد', n: f.value.rejected_documents, to: '/orphans', tone: 'bad' },
  { key: 'drafts', label: 'ملفات ما زالت مسوّدة', n: f.value.drafts, to: '/orphans?status=draft', tone: 'warn' },
  { key: 'pending', label: 'تنتظر توثيق المدير', n: f.value.pending_verification, to: '/orphans?status=pending_verification', tone: 'neutral' },
  { key: 'photo', label: 'أطفال بلا صورة', n: f.value.no_photo, to: '/orphans', tone: 'neutral' },
])
const TONE = { bad: 'text-rose-700', warn: 'text-[#a8741a]', neutral: 'text-sand-800' }
</script>

<template>
  <div class="space-y-5">
    <!-- ══ شريط الأسبوع ══ -->
    <section class="rounded-2xl bg-[#064e3b] text-white p-5 lg:p-6 grid grid-cols-2 lg:grid-cols-4 gap-5" aria-label="أسبوعك في الميدان">
      <div>
        <p class="text-xs text-white/60">أطفال في {{ field.country }}</p>
        <p class="num text-3xl font-bold mt-1">{{ num(field.children) }}</p>
        <p class="text-xs text-white/70 mt-1">ملفاتهم مفتوحة</p>
      </div>
      <RouterLink to="/visits?bucket=overdue" class="group">
        <p class="text-xs text-white/60">زيارات تأخّرت</p>
        <p class="num text-3xl font-bold mt-1" :class="v.overdue + v.never ? 'text-[#D5A85C]' : ''">{{ num(v.overdue + v.never) }}</p>
        <p class="text-xs text-white/70 mt-1 group-hover:underline">
          <template v-if="v.overdue + v.never">{{ v.never ? `${v.never} لم يُزاروا قط` : '' }}{{ v.never && v.overdue ? '، ' : '' }}{{ v.overdue ? `${v.overdue} فات موعدهم` : '' }}</template>
          <template v-else>لا أحد متأخر</template>
        </p>
      </RouterLink>
      <div>
        <p class="text-xs text-white/60">زرتَ هذا الشهر</p>
        <p class="num text-3xl font-bold mt-1">{{ num(v.month_done) }}</p>
        <p class="text-xs text-white/70 mt-1">{{ v.week ? `${v.week} يحلّ موعدهم هذا الأسبوع` : 'لا مواعيد هذا الأسبوع' }}</p>
      </div>
      <RouterLink to="/reports" class="group">
        <p class="text-xs text-white/60">تقارير {{ r.label }}</p>
        <p class="mt-1"><span class="num text-3xl font-bold">{{ r.sent }}</span><span class="text-white/60 text-lg"> / <span class="num">{{ r.expected }}</span></span></p>
        <p class="text-xs text-white/70 mt-1 group-hover:underline">بقي على نهاية الربع <span class="num">{{ r.days_left }}</span> يوماً</p>
      </RouterLink>
    </section>

    <div class="grid gap-5 lg:grid-cols-5">
      <!-- ══ ١. من أزور ══ -->
      <section class="lg:col-span-3 rounded-2xl border border-sand-200 bg-sand-0 flex flex-col" aria-labelledby="supNext">
        <header class="flex flex-wrap items-baseline gap-x-3 px-5 pt-5 pb-3">
          <h2 id="supNext" class="font-semibold">من تزور</h2>
          <span class="text-xs text-sand-500">من لم يُزر قط أولاً، ثم الأقرب موعداً</span>
        </header>
        <ul v-if="v.next.length" class="divide-y divide-sand-100 flex-1">
          <li v-for="c in v.next" :key="c.id" class="flex items-center gap-3 px-5 py-3">
            <img v-if="c.photo_url" :src="c.photo_url" alt="" class="size-10 rounded-full object-cover shrink-0" width="40" height="40" loading="lazy" />
            <span v-else class="size-10 shrink-0 rounded-full bg-[#064e3b]/10 text-[#064e3b] grid place-items-center font-semibold" aria-hidden="true">{{ c.name.trim().charAt(0) }}</span>
            <div class="min-w-0 flex-1">
              <RouterLink :to="`/orphans/${c.id}`" class="text-sm font-medium text-sand-900 hover:text-clay-700">{{ c.name }}</RouterLink>
              <p class="text-xs text-sand-500 truncate">
                <span class="num">{{ c.reference_code }}</span><template v-if="c.city"> · {{ c.city }}</template>
                <template v-if="c.last_visited_at"> · آخر زيارة {{ relative(c.last_visited_at) }}</template>
              </p>
            </div>
            <span class="hidden sm:inline text-xs rounded-full px-2.5 py-1 shrink-0" :class="due(c).tone">{{ due(c).text }}</span>
            <RouterLink :to="recordLink(c)" class="btn btn-outline !py-1.5 !px-3 text-sm shrink-0">
              <BaseIcon name="plus" :size="14" /> <span class="hidden sm:inline">سجّل زيارة</span>
            </RouterLink>
          </li>
        </ul>
        <p v-else class="flex-1 px-5 pb-6 text-sand-600">لا أطفال بملفات مفتوحة في بلدك بعد.</p>
        <RouterLink to="/visits" class="flex items-center justify-between px-5 py-3 border-t border-sand-100 text-sm text-clay-700 hover:bg-sand-25 rounded-b-2xl">
          خطة الزيارات كاملة <BaseIcon name="chevron" :size="15" style="transform: scaleX(-1)" />
        </RouterLink>
      </section>

      <!-- ══ ٢. تقارير الربع ══ -->
      <section class="lg:col-span-2 rounded-2xl border border-sand-200 bg-sand-0 p-5 flex flex-col" aria-labelledby="supReports">
        <h2 id="supReports" class="font-semibold">تقارير {{ r.label }}</h2>
        <p class="text-xs text-sand-500">تقرير لكل طفل في بلدك، يُرسل للمراجعة قبل نهاية الربع</p>

        <p class="mt-4"><span class="num text-3xl font-bold">{{ done }}%</span> <span class="text-sm text-sand-500">أُرسلت</span></p>
        <div class="mt-3 flex h-2.5 rounded-full overflow-hidden bg-sand-100" aria-hidden="true">
          <span class="bg-[#064e3b]" :style="{ width: pct(r.sent) }" />
          <span class="bg-[#D5A85C]" :style="{ width: pct(r.drafts) }" />
        </div>
        <dl class="mt-3 grid grid-cols-3 gap-3 text-sm">
          <div><dt class="flex items-center gap-1.5 text-xs text-sand-500"><span class="size-2 rounded-full bg-[#064e3b]" />أُرسلت</dt><dd class="num font-semibold">{{ r.sent }}</dd></div>
          <div><dt class="flex items-center gap-1.5 text-xs text-sand-500"><span class="size-2 rounded-full bg-[#D5A85C]" />مسوّدة</dt><dd class="num font-semibold">{{ r.drafts }}</dd></div>
          <div><dt class="flex items-center gap-1.5 text-xs text-sand-500"><span class="size-2 rounded-full bg-sand-300" />لم تُبدأ</dt><dd class="num font-semibold" :class="r.missing ? 'text-rose-700' : ''">{{ r.missing }}</dd></div>
        </dl>

        <div v-if="r.returned.length" class="mt-5 border-t border-sand-100 pt-4">
          <p class="text-sm font-medium text-[#a8741a] mb-2">أُرجعت إليك للتعديل ({{ r.returned_count }})</p>
          <ul class="space-y-2.5">
            <li v-for="x in r.returned" :key="x.id">
              <RouterLink :to="`/reports?review=${x.id}`" class="block rounded-lg border border-[#D5A85C]/40 bg-[#D5A85C]/5 px-3 py-2 hover:bg-[#D5A85C]/10">
                <p class="text-sm font-medium">{{ x.orphan }} <span class="text-xs text-sand-500">· {{ relative(x.returned_at) }}</span></p>
                <p v-if="x.note" class="text-xs text-sand-700 mt-0.5 line-clamp-2">{{ x.note }}</p>
              </RouterLink>
            </li>
          </ul>
        </div>
        <RouterLink v-else-if="r.missing || r.drafts" to="/reports" class="mt-auto pt-4 text-sm text-clay-700 hover:underline">
          اكتب ما بقي ({{ r.missing + r.drafts }})
        </RouterLink>
        <p v-else class="mt-auto pt-4 text-sm text-olive-700 flex items-center gap-1.5"><BaseIcon name="check" :size="15" /> كل تقارير الربع أُرسلت.</p>
      </section>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
      <!-- ══ ٣. ما رصدته الزيارات ══ -->
      <section class="rounded-2xl border border-sand-200 bg-sand-0 p-5" aria-labelledby="supNeeds">
        <h2 id="supNeeds" class="font-semibold">ما رصدته الزيارات</h2>
        <p class="text-xs text-sand-500">احتياجات علّمتها زيارات الثلاثين يوماً الأخيرة</p>
        <template v-if="fl.count">
          <ul class="flex flex-wrap gap-2 mt-4">
            <li v-for="n in fl.by_need" :key="n.need" class="inline-flex items-center gap-1.5 rounded-full bg-[#c9694b]/10 text-[#9a4a31] px-3 py-1 text-sm">
              {{ n.need }} <span class="num font-semibold">{{ n.count }}</span>
            </li>
          </ul>
          <ul class="mt-4 divide-y divide-sand-100">
            <li v-for="x in fl.recent" :key="`${x.orphan_id}-${x.visited_at}`" class="py-2.5 flex items-center gap-3 text-sm">
              <RouterLink :to="`/orphans/${x.orphan_id}`" class="font-medium hover:text-clay-700">{{ x.name }}</RouterLink>
              <span class="text-sand-600 flex-1 truncate">{{ x.needs.join('، ') }}</span>
              <span class="text-xs text-sand-500 shrink-0">{{ relative(x.visited_at) }}</span>
            </li>
          </ul>
          <RouterLink to="/visits/log" class="inline-block mt-2 text-sm text-clay-700 hover:underline">سجل الزيارات</RouterLink>
        </template>
        <p v-else class="mt-4 text-sm text-sand-600">لم تُعلَّم احتياجات في زيارات الشهر الأخير.</p>
      </section>

      <!-- ══ ٤. الملفات ══ -->
      <section class="rounded-2xl border border-sand-200 bg-sand-0 p-5" aria-labelledby="supFiles">
        <h2 id="supFiles" class="font-semibold">ملفات الأطفال</h2>
        <p class="text-xs text-sand-500">ما يحتاج يدك قبل أن يُنشر الطفل أو يُكفل</p>
        <ul class="mt-3 divide-y divide-sand-100">
          <li v-for="x in fileRows" :key="x.key">
            <RouterLink :to="x.to" class="flex items-center justify-between py-3 text-sm hover:text-clay-700">
              <span class="text-sand-700">{{ x.label }}</span>
              <span class="num font-semibold" :class="x.n ? TONE[x.tone] : 'text-sand-400'">{{ x.n ? num(x.n) : 'لا شيء' }}</span>
            </RouterLink>
          </li>
        </ul>
      </section>
    </div>

    <!-- ══ ٥. عاجلة تنتظر كافلاً ══ -->
    <section v-if="urgent.length" class="rounded-2xl border border-sand-200 bg-sand-0 p-5" aria-labelledby="supUrgent">
      <div class="flex items-baseline justify-between gap-3 mb-3">
        <h2 id="supUrgent" class="font-semibold">حالات عاجلة تنتظر كافلاً في {{ field.country }}</h2>
        <RouterLink to="/orphans?urgent=1&unfunded=1" class="text-sm text-clay-700 hover:underline">عرض الكل</RouterLink>
      </div>
      <p class="text-xs text-sand-500 mb-4">ملفٌ كامل وصورة واضحة يقرّبان الطفل من كافله.</p>
      <ul class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <li v-for="o in urgent" :key="o.id">
          <RouterLink :to="`/orphans/${o.id}`" class="flex items-center gap-3 rounded-xl border border-sand-100 p-3 hover:border-sand-300">
            <img v-if="o.photo_url" :src="o.photo_url" alt="" class="size-11 rounded-lg object-cover shrink-0" width="44" height="44" loading="lazy" />
            <span v-else class="size-11 shrink-0 rounded-lg bg-sand-100 grid place-items-center font-semibold text-sand-600" aria-hidden="true">{{ (o.first_name ?? '').charAt(0) }}</span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-medium truncate">{{ o.first_name }} <span class="text-xs font-normal text-sand-500">· {{ o.age }} سنوات</span></span>
              <span class="block h-1.5 rounded-full bg-sand-100 mt-2"><span class="block h-full rounded-full bg-rose-400" :style="{ width: `${o.funded_percent ?? 0}%` }" /></span>
              <span class="block text-xs text-sand-500 mt-1"><span class="num">{{ o.funded_percent ?? 0 }}%</span> من حاجته</span>
            </span>
          </RouterLink>
        </li>
      </ul>
    </section>
  </div>
</template>

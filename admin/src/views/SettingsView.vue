<script setup>
/**
 * الإعدادات.
 *
 * لكل إعداد سياقه بجانبه: اسم المنصة ومعاينةٌ لما يراه الزائر، ونسبة
 * التشغيل المستهدفة ومعها الفعلية هذا العام، ومراجعة الرسائل ومعها عدد ما
 * ينتظرها الآن، ودورية التقارير وما تعنيه عدداً. وتحت كل حقل من غيّره ومتى،
 * وفي الأسفل آخر التغييرات بقيمها قبل وبعد.
 *
 * والحفظ شريطٌ يظهر حين يتغيّر شيء، بعدد التغييرات وزرّ تراجع، بدل زرّين
 * صامتين في رأس الصفحة وذيلها.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useForm } from '@/composables/useForm'
import { confirmDialog } from '@/composables/useConfirm'
import { dateTime, num, relative } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FormField from '@/components/FormField.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const PLATFORM = [
  { key: 'site.name', label: 'اسم المنصة', required: true },
  { key: 'site.tagline', label: 'الوصف المختصر', hint: 'سطرٌ تحت الاسم في تذييل الموقع.' },
  { key: 'site.email', label: 'بريد التواصل', type: 'email' },
  { key: 'site.phone', label: 'الهاتف', type: 'tel' },
  { key: 'site.address', label: 'العنوان' },
  { key: 'site.start_year', label: 'سنة التأسيس', type: 'number', min: 1950, max: new Date().getFullYear() },
]
const KEYS = [...PLATFORM.map((f) => f.key), 'finance.base_currency', 'finance.overhead_target_percent', 'policy.moderate_messages', 'policy.report_cadence_months']
const BOOLEAN = ['policy.moderate_messages']

const CADENCE = [
  { value: 1, label: 'كل شهر' },
  { value: 2, label: 'كل شهرين' },
  { value: 3, label: 'كل 3 أشهر' },
  { value: 6, label: 'كل 6 أشهر' },
  { value: 12, label: 'سنوياً' },
]

const loading = ref(true)
const failed = ref(false)
const context = ref({})

/**
 * يُرسَل ما تغيّر وحده. كانت كل المفاتيح تُرسَل، فإن فشل التحميل وبقي
 * النموذج فارغاً ثم حُفظ تعديل واحد، مُسح اسم الموقع وبريده وهاتفه.
 */
const form = useForm(Object.fromEntries(KEYS.map((k) => [k, ''])), (values) =>
  admin.updateSettings(Object.keys(form.changed()).map((name) => {
    const [group, ...rest] = name.split('.')
    // خانة الاختيار تصل `true`/`false` منطقيةً، والخادم يخزّن نصّاً
    return { group, key: rest.join('.'), value: BOOLEAN.includes(name) ? String(Boolean(values[name])) : values[name] }
  })),
)

const v = form.values
const err = (key) => form.errors.value[key.replace('.', '__')]
/**
 * ما يُقارَن به (snapshot في useForm) ليس تفاعلياً: بعد الحفظ تبقى القيم كما
 * هي فلا يُعاد الحساب، ويبقى الشريط يقول «تغيير لم يُحفظ». العدّاد يُعيده.
 */
const baseline = ref(0)
const changes = computed(() => (baseline.value, Object.keys(form.changed()).length))

/** القيم المحفوظة تصير ما يُقارَن به: بعد الحفظ تُقاس التغييرات منها لا مما قبلها */
function apply(stored) {
  form.setValues(Object.fromEntries(KEYS.map((k) => [k, BOOLEAN.includes(k) ? Boolean(stored[k]) : (stored[k] ?? '')])))
  baseline.value++
}

async function load() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await admin.settings()
    context.value = data.meta ?? {}
    apply(data.data ?? {})
    loadHistory()
  } catch {
    // لا نموذج بلا قيمه: حفظٌ من نموذج فارغ يمسح الإعدادات
    failed.value = true
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function save() {
  if (!changes.value || form.submitting.value) return
  // إيقاف مراجعة الرسائل قرارُ حماية طفل، فيُؤكَّد بما يعنيه
  if (form.changed()['policy.moderate_messages'] === false) {
    const ok = await confirmDialog({
      title: 'إيقاف مراجعة رسائل الكفلاء',
      message: 'ستصل رسائل الكفلاء إلى الأطفال فوراً دون أن يقرأها أحد قبلهم. سياسة حماية الطفل المعلنة على الموقع تَعِد بالمراجعة.',
      confirmLabel: 'أوقف المراجعة',
      danger: true,
    })
    if (!ok) return
  }

  const saved = await form.submit()
  if (saved && typeof saved === 'object') {
    apply(saved)
    // من غيّر ومتى: يُعاد تحميل السياق والسجل بعد الحفظ
    try { context.value = (await admin.settings()).data.meta ?? context.value } catch { /* السياق ثانوي */ }
    loadHistory()
  }
}

/* ── السياق ──────────────────────────────────────────────────────────── */

const since = computed(() => {
  const y = Number(v['site.start_year'])
  return y ? new Date().getFullYear() - y : null
})

const actual = computed(() => context.value.overhead_actual)
const target = computed(() => Number(v['finance.overhead_target_percent']) || 0)
const overTarget = computed(() => actual.value !== null && actual.value !== undefined && actual.value > target.value)
/** موضع على مقياس 0 إلى 30٪، حدود الإعداد نفسه */
const pos = (p) => `${Math.min(100, Math.max(0, (p / 30) * 100))}%`

const cadence = computed(() => Number(v['policy.report_cadence_months']) || 0)
const cadenceOptions = computed(() => (CADENCE.some((c) => c.value === cadence.value) || !cadence.value
  ? CADENCE
  : [...CADENCE, { value: cadence.value, label: `كل ${cadence.value} أشهر` }].sort((a, b) => a.value - b.value)))
const perYear = computed(() => (cadence.value ? Math.round(12 / cadence.value) : 0))

function changedBy(key) {
  const c = context.value.last_changed?.[key]
  return c ? `عدّله ${c.by} ${relative(c.at)}` : ''
}

/* ── آخر التغييرات ───────────────────────────────────────────────────── */

const history = ref(null)
async function loadHistory() {
  try {
    const { data } = await admin.activity({ event: 'settings_updated', per_page: 5 })
    history.value = data.data ?? []
  } catch {
    history.value = null
  }
}

/* ── ما لم يُحفظ ─────────────────────────────────────────────────────── */

onBeforeRouteLeave(async () => {
  if (!changes.value || form.submitting.value) return true
  return Boolean(await confirmDialog({
    title: 'تغييرات لم تُحفظ',
    message: 'في الإعدادات تغييرات لم تُحفظ بعد، وتضيع إن خرجت.',
    confirmLabel: 'اخرج دون حفظ',
    danger: true,
  }))
})
function beforeUnload(e) { if (changes.value) { e.preventDefault(); e.returnValue = '' } }
function onKey(e) { if ((e.ctrlKey || e.metaKey) && e.code === 'KeyS') { e.preventDefault(); save() } }
onMounted(() => { window.addEventListener('beforeunload', beforeUnload); document.addEventListener('keydown', onKey) })
onBeforeUnmount(() => { window.removeEventListener('beforeunload', beforeUnload); document.removeEventListener('keydown', onKey) })
</script>

<template>
  <div class="pb-24">
    <PageHeader title="الإعدادات" subtitle="ما يظهر منها على الموقع العام يتغيّر فور الحفظ." />

    <div v-if="loading" class="space-y-4">
      <div v-for="i in 3" :key="i" class="h-40 bg-sand-100 rounded-2xl animate-pulse" />
    </div>

    <div v-else-if="failed" class="card p-8 text-center" role="alert">
      <p class="font-medium text-sand-900">تعذّر تحميل الإعدادات</p>
      <p class="text-sm text-sand-500 mt-1">لا يُعرض النموذج قبل أن تُحمَّل القيم الحالية، كي لا يُحفظ فوقها فراغ.</p>
      <button class="btn btn-outline mt-4" type="button" @click="load">إعادة المحاولة</button>
    </div>

    <form v-else novalidate class="card divide-y divide-sand-100" @submit.prevent="save">
      <!-- ══ المنصة ══ -->
      <section aria-labelledby="sPlatform" class="grid lg:grid-cols-[16rem_minmax(0,1fr)] gap-6 p-6">
        <div>
          <h2 id="sPlatform" class="font-semibold">المنصة</h2>
          <p class="text-sm text-sand-500 mt-1">تظهر في تذييل الموقع العام وصفحة التواصل.</p>

          <!-- كما يراها الزائر: تتغيّر مع الكتابة -->
          <figure class="hidden lg:block mt-4 rounded-xl bg-[#0f5c5c] text-white/90 p-4 text-xs leading-relaxed" aria-label="معاينة تذييل الموقع">
            <figcaption class="text-[0.65rem] text-white/60 mb-2">كما في تذييل الموقع</figcaption>
            <p class="text-base font-bold text-white">{{ v['site.name'] || 'بلا اسم' }}</p>
            <p v-if="v['site.tagline']" class="text-white/75">{{ v['site.tagline'] }}</p>
            <p v-if="since" class="text-[#E0A63B] mt-1">نعمل منذ <span class="num">{{ since }}</span> عاماً</p>
            <ul class="mt-2 space-y-0.5 text-white/80">
              <li v-if="v['site.email']"><span class="num" dir="ltr">{{ v['site.email'] }}</span></li>
              <li v-if="v['site.phone']"><span class="num" dir="ltr">{{ v['site.phone'] }}</span></li>
              <li v-if="v['site.address']">{{ v['site.address'] }}</li>
            </ul>
          </figure>
        </div>

        <div class="grid sm:grid-cols-2 gap-x-4 gap-y-5 content-start">
          <FormField
            v-for="f in PLATFORM" :key="f.key"
            v-model="v[f.key]" :class="{ 'sm:col-span-2': f.key === 'site.tagline' || f.key === 'site.address' }"
            :label="f.label" :type="f.type ?? 'text'" :required="f.required ?? false"
            :min="f.min ?? null" :max="f.max ?? null"
            :hint="[f.hint, changedBy(f.key)].filter(Boolean).join(' · ')"
            :error="err(f.key)" @update:model-value="form.touch(f.key)"
          />
        </div>
      </section>

      <!-- ══ المال ══ -->
      <section aria-labelledby="sMoney" class="grid lg:grid-cols-[16rem_minmax(0,1fr)] gap-6 p-6">
        <div>
          <h2 id="sMoney" class="font-semibold">المال</h2>
          <p class="text-sm text-sand-500 mt-1">يُقاس عليه ما يُنشر على صفحة الشفافية.</p>
        </div>

        <div class="space-y-6">
          <div>
            <p class="text-sm font-medium text-sand-800">عملة الأساس</p>
            <p class="mt-1.5 inline-flex items-center gap-2 rounded-lg bg-sand-50 border border-sand-200 px-3 py-2 text-sm">
              <span class="num font-semibold" dir="ltr">{{ v['finance.base_currency'] || 'USD' }}</span>
              <span class="text-sand-500">ثابتة</span>
            </p>
            <p class="text-xs text-sand-500 mt-1.5">من إعداد الخادم: كل المبالغ المخزّنة محسوبة بها، وتغييرها من هنا يخلط عملتين تحت اسم واحد.</p>
          </div>

          <div>
            <label for="sOverhead" class="text-sm font-medium text-sand-800">نسبة التشغيل المستهدفة</label>
            <div class="mt-1.5 flex flex-wrap items-center gap-4">
              <!-- الرقم ثم «%» كما يُقرأ: الحقل كلّه من اليسار -->
              <span class="relative w-28" dir="ltr">
                <input
                  id="sOverhead" v-model.number="v['finance.overhead_target_percent']" type="number" min="0" max="30" step="1" lang="en"
                  class="input num !pr-8 text-left" :aria-invalid="Boolean(err('finance.overhead_target_percent'))"
                  @input="form.touch('finance.overhead_target_percent')"
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sand-500 text-sm pointer-events-none">%</span>
              </span>

              <!-- الهدف والفعلي على مقياس واحد -->
              <div v-if="actual !== null && actual !== undefined" class="flex-1 min-w-[14rem]">
                <div class="relative h-2 rounded-full bg-sand-100" aria-hidden="true">
                  <span class="absolute inset-y-0 start-0 rounded-full" :class="overTarget ? 'bg-rose-400' : 'bg-[#1e8a7a]'" :style="{ width: pos(actual) }" />
                  <span class="absolute -top-1 -bottom-1 w-0.5 bg-sand-900" :style="{ insetInlineStart: pos(target) }" />
                </div>
                <p class="text-xs mt-1.5" :class="overTarget ? 'text-rose-700' : 'text-sand-600'">
                  الفعلي في <span class="num">{{ context.overhead_year }}</span>:
                  <span class="num font-semibold">{{ actual }}%</span>
                  {{ overTarget ? 'فوق الهدف، ويظهر هكذا في تقارير الشفافية.' : 'ضمن الهدف.' }}
                </p>
              </div>
            </div>
            <p v-if="err('finance.overhead_target_percent')" class="text-xs text-rose-700 mt-1" role="alert">{{ err('finance.overhead_target_percent')[0] }}</p>
            <p class="text-xs text-sand-500 mt-1.5">
              هدفٌ يُقاس عليه، لا حدٌّ يفرضه النظام. بين 0 و30.
              <template v-if="changedBy('finance.overhead_target_percent')"> · {{ changedBy('finance.overhead_target_percent') }}</template>
            </p>
          </div>
        </div>
      </section>

      <!-- ══ السياسات ══ -->
      <section aria-labelledby="sPolicy" class="grid lg:grid-cols-[16rem_minmax(0,1fr)] gap-6 p-6">
        <div>
          <h2 id="sPolicy" class="font-semibold">السياسات</h2>
          <p class="text-sm text-sand-500 mt-1">قواعد يعمل بها الفريق كل يوم.</p>
        </div>

        <div class="space-y-6">
          <div class="flex items-start gap-4">
            <div class="flex-1">
              <p id="sModLabel" class="text-sm font-medium text-sand-800">مراجعة رسائل الكفلاء قبل وصولها للطفل</p>
              <p class="text-xs text-sand-500 mt-1">
                <template v-if="v['policy.moderate_messages']">
                  كل رسالة يقرؤها أحد الفريق قبل أن تصل.
                  <RouterLink v-if="context.messages_pending" to="/messages" class="text-clay-700 hover:underline">
                    <span class="num">{{ num(context.messages_pending) }}</span> رسالة تنتظر الآن
                  </RouterLink>
                </template>
                <span v-else class="text-rose-700">موقوفة: الرسائل تصل الأطفال دون أن يقرأها أحد.</span>
                <template v-if="changedBy('policy.moderate_messages')"> · {{ changedBy('policy.moderate_messages') }}</template>
              </p>
            </div>
            <button
              type="button" role="switch" :aria-checked="Boolean(v['policy.moderate_messages'])" aria-labelledby="sModLabel"
              class="relative w-11 h-6 rounded-full shrink-0 transition-colors mt-0.5"
              :class="v['policy.moderate_messages'] ? 'bg-clay-600' : 'bg-sand-300'"
              @click="v['policy.moderate_messages'] = !v['policy.moderate_messages']; form.touch('policy.moderate_messages')"
            >
              <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all" :class="v['policy.moderate_messages'] ? 'start-[1.375rem]' : 'start-0.5'" />
            </button>
          </div>

          <fieldset>
            <legend class="text-sm font-medium text-sand-800">كل كم يُنتظر تقرير عن الطفل</legend>
            <div class="mt-2 inline-flex flex-wrap rounded-lg border border-sand-200 p-0.5 bg-sand-50" role="radiogroup" aria-label="دورية التقارير">
              <button
                v-for="c in cadenceOptions" :key="c.value" type="button" role="radio" :aria-checked="cadence === c.value"
                class="px-3 py-1.5 text-sm rounded-md transition-colors"
                :class="cadence === c.value ? 'bg-sand-0 shadow-sm text-clay-800 font-medium' : 'text-sand-600 hover:text-sand-900'"
                @click="v['policy.report_cadence_months'] = c.value; form.touch('policy.report_cadence_months')"
              >{{ c.label }}</button>
            </div>
            <p v-if="perYear" class="text-xs text-sand-600 mt-2">
              <span class="num font-semibold">{{ perYear }}</span> {{ perYear === 1 ? 'تقرير' : 'تقارير' }} في السنة لكل طفل<template v-if="context.active_orphans">، قرابة <span class="num font-semibold">{{ num(perYear * context.active_orphans) }}</span> تقريراً لـ<span class="num">{{ num(context.active_orphans) }}</span> طفلاً نشطاً</template>.
              ومنها تُحسب التقارير المتأخرة وموعد الزيارة القادمة.
            </p>
            <p v-if="changedBy('policy.report_cadence_months')" class="text-xs text-sand-500 mt-1">{{ changedBy('policy.report_cadence_months') }}</p>
          </fieldset>
        </div>
      </section>

      <!-- ══ آخر التغييرات ══ -->
      <section aria-labelledby="sHistory" class="grid lg:grid-cols-[16rem_minmax(0,1fr)] gap-6 p-6">
        <div>
          <h2 id="sHistory" class="font-semibold">آخر التغييرات</h2>
          <p class="text-sm text-sand-500 mt-1">من غيّر ماذا، بالقيمة قبل وبعد.</p>
        </div>
        <div>
          <ol v-if="history?.length" class="space-y-4">
            <li v-for="h in history" :key="h.id" class="text-sm">
              <p><span class="font-medium">{{ h.actor }}</span> <span class="text-xs text-sand-500" :title="dateTime(h.created_at)">{{ relative(h.created_at) }}</span></p>
              <ul class="mt-1 space-y-0.5 text-[0.8rem]">
                <li v-for="c in h.changes" :key="c.field" class="flex flex-wrap items-baseline gap-x-2">
                  <span class="text-sand-500">{{ c.label }}:</span>
                  <span class="text-sand-400 line-through">{{ c.before ?? 'فارغ' }}</span>
                  <span class="text-sand-400">←</span>
                  <span class="text-sand-900">{{ c.after ?? 'فارغ' }}</span>
                </li>
              </ul>
            </li>
          </ol>
          <p v-else-if="history" class="text-sm text-sand-500">لم يُغيَّر شيء من هنا بعد.</p>
          <p v-else class="text-sm text-sand-500">تعذّر تحميل السجل.</p>
        </div>
      </section>
    </form>

    <!-- شريط الحفظ: يظهر حين يتغيّر شيء، ويبقى ظاهراً مهما طالت الصفحة -->
    <Transition
      enter-from-class="opacity-0 translate-y-2" leave-to-class="opacity-0 translate-y-2"
      enter-active-class="transition duration-150" leave-active-class="transition duration-150"
    >
      <div v-if="!loading && !failed && changes" class="sticky bottom-4 z-20 mt-4">
        <div class="mx-auto max-w-xl flex items-center gap-3 rounded-xl bg-sand-900 text-white px-4 py-3 shadow-lg" role="status">
          <span class="text-sm flex-1">
            <span class="num">{{ changes }}</span> {{ changes === 1 ? 'تغيير لم يُحفظ' : changes === 2 ? 'تغييران لم يُحفظا' : 'تغييرات لم تُحفظ' }}
          </span>
          <button class="text-sm text-white/80 hover:text-white px-2" type="button" :disabled="form.submitting.value" @click="form.reset()">تراجع</button>
          <button class="btn btn-primary !py-1.5" type="button" :disabled="form.submitting.value" @click="save">
            <BaseIcon v-if="form.submitting.value" name="loader" :size="16" class="animate-spin" />
            {{ form.submitting.value ? 'جارٍ الحفظ…' : 'حفظ' }} <kbd class="kbd !bg-white/10 !text-white/80 !border-white/20">Ctrl S</kbd>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

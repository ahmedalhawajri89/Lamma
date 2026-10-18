<script setup>
/**
 * الموظف في درجٍ جانبي: من هو، وما يقدر عليه، وماذا فعل مؤخراً.
 *
 * كان الضغط على صفٍّ يفتح نموذج التعديل مباشرة، فلا مكان يُرى فيه ما يمنحه
 * دور الموظف ولا ما فعله بهذه الصلاحيات. والإيقاف كان قائمة منسدلة داخل
 * النموذج نفسه. هنا القراءة أولاً، والتعديل زرٌّ، والإيقاف بتأكيد.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { confirmDialog, confirmState } from '@/composables/useConfirm'
import { useUiStore } from '@/stores/ui'
import { date, dateTime, num, relative, EMPTY } from '@/utils/format'
import StatusBadge from './StatusBadge.vue'
import BaseIcon from './BaseIcon.vue'

const props = defineProps({
  id: { type: Number, default: null },
})
const emit = defineEmits(['close', 'changed', 'edit'])

const ui = useUiStore()

const u = ref(null)
const recent = ref(null)
const loading = ref(false)
const busy = ref(false)

async function load(id) {
  loading.value = true
  try {
    const [{ data }, activity] = await Promise.all([
      admin.users.show(id),
      admin.activity({ causer_id: id, per_page: 8 }).catch(() => null),
    ])
    if (props.id !== id) return
    u.value = data.data
    recent.value = activity?.data?.data ?? null
  } catch (e) {
    ui.error(e.userMessage)
    emit('close')
  } finally {
    loading.value = false
  }
}

watch(() => props.id, (id) => {
  u.value = null
  recent.value = null
  if (id) load(id)
}, { immediate: true })
defineExpose({ reload: () => props.id && load(props.id) })

const ROLE = { admin: 'مدير النظام', accountant: 'محاسب', field_supervisor: 'مشرف ميداني' }
const role = computed(() => u.value?.roles?.[0])
const initial = computed(() => (u.value?.name ?? '').trim().charAt(0))

/** ما يُمنح من كل مجموعة، والمجموعة الممنوعة كلّها تُختصر في سطر */
const groups = computed(() => (u.value?.abilities ?? []).map((g) => ({
  ...g,
  granted: g.items.filter((i) => i.granted),
  denied: g.items.filter((i) => !i.granted),
})))
const allGranted = computed(() => groups.value.length > 0 && groups.value.every((g) => !g.denied.length))

/* ── الإجراءات ───────────────────────────────────────────────────────── */

async function setStatus(status) {
  const suspending = status === 'suspended'
  const ok = await confirmDialog(suspending
    ? {
        title: `إيقاف حساب ${u.value.name}`,
        message: 'يخرج من اللوحة فوراً على كل أجهزته، ولا يدخل حتى يُعاد تفعيله. لا يُحذف شيء مما سجّله.',
        confirmLabel: 'أوقف الحساب',
        danger: true,
      }
    : {
        title: `إعادة تفعيل ${u.value.name}`,
        message: 'يعود للدخول بصلاحيات دوره نفسها.',
        confirmLabel: 'أعِد التفعيل',
      })
  if (!ok) return

  busy.value = true
  try {
    await admin.users.update(u.value.id, { status })
    ui.success(suspending ? 'أُوقف الحساب وأُنهيت جلساته.' : 'أُعيد تفعيل الحساب.')
    emit('changed')
    await load(u.value.id)
  } catch (e) {
    ui.error(e.fieldErrors?.status?.[0] ?? e.userMessage)
  } finally {
    busy.value = false
  }
}

async function resendInvite() {
  busy.value = true
  try {
    const { data } = await admin.users.invite(u.value.id)
    ui.success(data.message)
    await load(u.value.id)
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    busy.value = false
  }
}

/* ── التركيز ولوحة المفاتيح ─────────────────────────────────────────── */

const panel = ref(null)
let opener = null
function onKey(e) {
  if (confirmState.open) return
  if (e.key === 'Escape' && !busy.value) { e.preventDefault(); emit('close') }
}
watch(() => Boolean(props.id), async (open) => {
  if (open) {
    opener = document.activeElement
    document.addEventListener('keydown', onKey)
    await nextTick()
    panel.value?.focus()
  } else {
    document.removeEventListener('keydown', onKey)
    opener?.focus?.()
  }
}, { immediate: true })
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <div v-if="id" class="fixed inset-0 z-30 bg-sand-900/30" aria-hidden="true" @click="!busy && emit('close')" />
    <section
      v-if="id" ref="panel" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="udTitle"
      class="fixed inset-y-0 end-0 z-40 w-full sm:w-[34rem] bg-sand-0 border-s border-sand-200 flex flex-col outline-none"
    >
      <header class="flex items-start gap-3 p-5 border-b border-sand-200">
        <span class="size-11 shrink-0 rounded-full bg-clay-50 text-clay-700 grid place-items-center text-lg font-semibold" aria-hidden="true">{{ initial }}</span>
        <div class="flex-1 min-w-0">
          <h2 id="udTitle" class="text-lg font-semibold text-sand-900 truncate">{{ u?.name ?? '…' }}</h2>
          <p v-if="u" class="text-xs text-sand-500 num truncate" dir="ltr">{{ u.email }}</p>
          <div v-if="u" class="flex flex-wrap items-center gap-2 mt-1.5">
            <span class="badge bg-clay-50 text-clay-700">{{ ROLE[role] ?? role }}</span>
            <StatusBadge :label="u.status_label" :value="u.status" />
            <span v-if="u.is_self" class="text-xs text-sand-500">حسابك</span>
          </div>
        </div>
        <button class="btn btn-ghost !p-2" type="button" aria-label="إغلاق" @click="emit('close')">
          <BaseIcon name="close" :size="18" />
        </button>
      </header>

      <div v-if="loading && !u" class="p-5 space-y-3">
        <div v-for="n in 4" :key="n" class="h-12 rounded bg-sand-100 animate-pulse" />
      </div>

      <div v-else-if="u" class="flex-1 overflow-y-auto">
        <!-- الدعوة: ما ينتظره الحساب قبل أي شيء -->
        <div v-if="u.status === 'pending'" class="mx-5 mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm">
          <p class="font-medium text-amber-900">لم يفعّل حسابه بعد</p>
          <p class="text-xs text-amber-800 mt-0.5">
            أُرسل له رابط تعيين كلمة المرور
            <template v-if="u.invited_at"><span :title="dateTime(u.invited_at)">{{ relative(u.invited_at) }}</span></template>.
            يتفعّل الحساب حين يضبطها.
          </p>
          <button class="btn btn-outline !py-1.5 text-sm mt-2.5" type="button" :disabled="busy" @click="resendInvite">
            <BaseIcon name="mail" :size="14" /> إعادة إرسال الدعوة
          </button>
        </div>

        <div class="flex flex-wrap gap-2 px-5 pt-4">
          <button class="btn btn-outline !py-1.5 text-sm" type="button" :disabled="busy" @click="emit('edit', u)">
            <BaseIcon name="edit" :size="14" /> تعديل البيانات أو الدور
          </button>
          <RouterLink :to="{ path: '/activity', query: { causer_id: u.id } }" class="btn btn-ghost !py-1.5 text-sm">
            <BaseIcon name="history" :size="14" /> كل نشاطه في السجل
          </RouterLink>
        </div>

        <dl class="grid grid-cols-2 gap-x-5 gap-y-4 px-5 py-4 text-sm">
          <div>
            <dt class="text-xs text-sand-500 mb-0.5">نطاق العمل</dt>
            <dd>
              <template v-if="role === 'field_supervisor'">
                <span v-if="u.staff_profile?.assigned_country_name">{{ u.staff_profile.assigned_country_name }} وحدها</span>
                <span v-else class="text-rose-700">بلا بلد، لا يرى شيئاً</span>
              </template>
              <template v-else>كل الدول</template>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-sand-500 mb-0.5">آخر دخول</dt>
            <dd :title="u.last_login_at ? dateTime(u.last_login_at) : ''">{{ u.last_login_at ? relative(u.last_login_at) : 'لم يدخل بعد' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-sand-500 mb-0.5">المسمّى</dt>
            <dd>{{ u.staff_profile?.title || EMPTY }}</dd>
          </div>
          <div>
            <dt class="text-xs text-sand-500 mb-0.5">الرقم الوظيفي</dt>
            <dd class="num">{{ u.staff_profile?.employee_code ?? EMPTY }}</dd>
          </div>
          <div>
            <dt class="text-xs text-sand-500 mb-0.5">الهاتف</dt>
            <dd><span class="num" dir="ltr">{{ u.phone || EMPTY }}</span></dd>
          </div>
          <div>
            <dt class="text-xs text-sand-500 mb-0.5">في الفريق منذ</dt>
            <dd>{{ date(u.hired_at ?? u.created_at) }}</dd>
          </div>
        </dl>

        <!-- حمل العمل: أرقام قليلة تقول إن كان الحساب يُستعمل -->
        <p class="mx-5 mb-4 text-xs text-sand-600 leading-relaxed">
          <span class="num font-semibold text-sand-900">{{ num(u.stats.actions_30d) }}</span> إجراء في السجل خلال 30 يوماً
          <template v-if="u.stats.last_action_at">، آخرها {{ relative(u.stats.last_action_at) }}</template>
          <template v-if="role === 'field_supervisor'">
            · <span class="num font-semibold text-sand-900">{{ num(u.stats.orphans_in_country) }}</span> طفلاً في بلده
            · <span class="num font-semibold text-sand-900">{{ num(u.stats.visits_90d) }}</span> زيارة في 90 يوماً
            <template v-if="u.stats.reports_open">· <span class="num font-semibold text-amber-800">{{ num(u.stats.reports_open) }}</span> تقرير مسوّدة</template>
          </template>
        </p>

        <!-- ما يقدر عليه -->
        <section class="border-t border-sand-100 px-5 py-4">
          <h3 class="text-sm font-semibold mb-3">ما يقدر عليه بدوره</h3>
          <!-- كل شيء: جملة لا ثلاثون شارة -->
          <p v-if="allGranted" class="text-sm text-sand-700 leading-relaxed">
            كل شيء في اللوحة، ومنه ما لا يملكه غيره:
            <span class="font-medium text-sand-900">إدارة المستخدمين، وتعديل الإعدادات، واعتماد الصرف والتقارير.</span>
          </p>
          <div v-else class="space-y-3">
            <div v-for="g in groups" :key="g.key">
              <p class="text-xs font-medium text-sand-500 mb-1">{{ g.label }}</p>
              <ul v-if="g.granted.length" class="flex flex-wrap gap-1.5">
                <li v-for="i in g.granted" :key="i.name" class="inline-flex items-center gap-1 rounded-md bg-olive-50 text-olive-700 px-2 py-0.5 text-xs">
                  <BaseIcon name="check" :size="12" /> {{ i.label }}
                </li>
              </ul>
              <p v-else class="text-xs text-sand-400">لا شيء من هذا القسم.</p>
            </div>
          </div>
          <p v-if="role === 'field_supervisor'" class="text-xs text-sand-500 mt-3">
            والنظام نفسه يحصره في بلده: لا يرى طفلاً من بلدٍ آخر ولو فتح رابطه مباشرة.
          </p>
        </section>

        <!-- آخر ما فعل -->
        <section class="border-t border-sand-100 px-5 py-4">
          <h3 class="text-sm font-semibold mb-3">آخر ما فعل</h3>
          <ol v-if="recent?.length" class="space-y-2.5">
            <li v-for="a in recent" :key="a.id" class="flex items-baseline gap-3 text-sm">
              <span class="size-1.5 rounded-full bg-sand-300 shrink-0 translate-y-[-2px]" />
              <span class="flex-1 min-w-0">
                {{ a.action }}
                <RouterLink v-if="a.subject_path && a.subject_name" :to="a.subject_path" class="text-clay-700 hover:underline">{{ a.subject_name }}</RouterLink>
                <span v-else-if="a.subject_name" class="text-sand-700">{{ a.subject_name }}</span>
              </span>
              <span class="text-xs text-sand-500 shrink-0" :title="dateTime(a.created_at)">{{ relative(a.created_at) }}</span>
            </li>
          </ol>
          <p v-else-if="recent" class="text-sm text-sand-500">لا شيء مسجّل باسمه بعد.</p>
          <p v-else class="text-sm text-sand-500">تعذّر تحميل نشاطه.</p>
        </section>

        <!-- الإيقاف: بعيداً عن الإجراءات اليومية -->
        <section v-if="!u.is_self && u.status !== 'pending'" class="mx-5 my-5 rounded-xl border p-4" :class="u.status === 'suspended' ? 'border-sand-200' : 'border-rose-200'">
          <template v-if="u.status === 'suspended'">
            <h3 class="text-sm font-semibold">الحساب موقوف</h3>
            <p class="text-xs text-sand-600 mt-1 mb-3">لا يدخل اللوحة. ما سجّله قبل الإيقاف باقٍ كما هو.</p>
            <button class="btn btn-primary !py-1.5 text-sm" type="button" :disabled="busy" @click="setStatus('active')">أعِد التفعيل</button>
          </template>
          <template v-else>
            <h3 class="text-sm font-semibold text-rose-800">إيقاف الحساب</h3>
            <p class="text-xs text-sand-600 mt-1 mb-3">لموظفٍ غادر أو حسابٍ يُشكّ فيه: يخرج فوراً من كل أجهزته. لا يُحذف شيء.</p>
            <button class="btn btn-outline !py-1.5 text-sm text-rose-700 border-rose-200 hover:!border-rose-400" type="button" :disabled="busy" @click="setStatus('suspended')">
              أوقف الحساب
            </button>
          </template>
        </section>
        <p v-else-if="u.is_self" class="mx-5 my-5 text-xs text-sand-500">لا توقف حسابك ولا تغيّر دورك بنفسك: يفعلها مدير آخر، فلا يُقفل الباب على اللوحة.</p>
      </div>
    </section>
  </Teleport>
</template>

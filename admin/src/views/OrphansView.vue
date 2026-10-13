<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { money, num, years, EMPTY } from '@/utils/format'
import { saveBlob } from '@/utils/download'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import FundingBar from '@/components/FundingBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import OrphanFormModal from '@/components/OrphanFormModal.vue'

const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const DEFAULTS = {
  q: '', status: '', country_id: '', program_id: '', family_id: '',
  urgent: false, unfunded: false, unverified: false, missing_report: '', sort: 'created_at', dir: 'desc',
}
const { items, meta, loading, filters, reset, load, error } = useResource(admin.orphans.list, DEFAULTS)

/** الحالات بترتيب مسار الملف؛ النادرة تظهر حين يكون لها ملفات */
const STATUSES = [
  { value: 'active', label: 'نشط' },
  { value: 'pending_verification', label: 'بانتظار التوثيق' },
  { value: 'draft', label: 'مسوّدة' },
  { value: 'fully_funded', label: 'مكتمل التمويل' },
  { value: 'graduated', label: 'متخرّج', rare: true },
  { value: 'withdrawn', label: 'منسحب', rare: true },
  { value: 'deceased', label: 'متوفّى', rare: true },
]

/**
 * المعرّف صار سطراً تحت الاسم لا عموداً: عمودان لهوية الطفل نفسها كانا
 * يأخذان عرضاً من التمويل، وهو ما يُقرأ الجدول من أجله.
 */
const columns = [
  { key: 'first_name', label: 'الطفل', sortable: true },
  { key: 'age', label: 'العمر', sortable: true, sortKey: 'date_of_birth' },
  { key: 'country', label: 'الدولة' },
  { key: 'program', label: 'البرنامج' },
  { key: 'funded_percent', label: 'التمويل', sortable: true },
  { key: 'monthly_need', label: 'الحاجة الشهرية', align: 'end', sortable: true },
  { key: 'status', label: 'الحالة' },
]

/** نقرة على عمود: ترتيبه تنازلياً، والثانية تعكسه. والعمر عكس تاريخ الميلاد */
function onSort(key) {
  if (filters.sort === key) {
    filters.dir = filters.dir === 'asc' ? 'desc' : 'asc'
  } else {
    filters.sort = key
    filters.dir = key === 'first_name' ? 'asc' : 'desc'
  }
}

// المشرف مقيّد ببلده على الخادم، فلا معنى لفلتر الدولة عنده
const countries = computed(() => (auth.isAdmin || !auth.user?.staff_profile?.assigned_country_id ? references.value.countries ?? [] : []))
const programs = computed(() => references.value.programs ?? [])

/** فيه ما يُمسح؟ (الترتيب ليس فلتراً) */
const dirty = computed(() => ['q', 'status', 'country_id', 'program_id', 'family_id', 'urgent', 'unfunded', 'unverified', 'missing_report']
  .some((k) => filters[k] !== DEFAULTS[k]))

/** ما ينقص الطفل شهرياً: الحاجة ناقص المغطّى فعلاً، لا تقديرٌ من النسبة */
const remaining = (row) => Math.max(0, (row.monthly_need ?? 0) - (row.funded_amount ?? (row.monthly_need * row.funded_percent) / 100))

/** سطر الرأس: العدد، ثم العاجل، ثم فجوة التمويل. أرقام تُقرأ لا بطاقات تُعدّ */
const subtitle = computed(() => {
  if (loading.value && !meta.value.total) return 'جارٍ التحميل…'
  const parts = [`${num(meta.value.total)} حالة`]
  if (meta.value.urgent_count) parts.push(`${num(meta.value.urgent_count)} عاجلة`)
  if (meta.value.funding_gap) parts.push(`ينقص النشطين ${money(meta.value.funding_gap)} شهرياً`)
  return parts.join(' · ')
})

/* ── التصدير ─────────────────────────────────────────────────────────── */

const exporting = ref(false)

async function exportCsv() {
  exporting.value = true
  try {
    const params = Object.fromEntries(Object.entries(filters).filter(([k, v]) => v !== '' && v !== false && !['page', 'sort', 'dir'].includes(k)))
    saveBlob(await admin.orphans.export(params), 'orphans.csv')
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر التصدير.')
  } finally {
    exporting.value = false
  }
}

/* ── الإنشاء ─────────────────────────────────────────────────────────── */

const formOpen = ref(false)
const references = ref({})

/**
 * القوائم المرجعية تُجلب مرة عند فتح الشاشة لا عند كل فتح للنافذة.
 * والأسرة يُبحث عنها في النافذة نفسها (SearchSelect)، لا تُحمَّل كلها هنا.
 */
async function loadReferences() {
  try {
    const { data } = await admin.references()
    references.value = data.data ?? {}
  } catch {
    // الصمت مقصود: فشل القوائم لا يمنع تصفّح الجدول، والنافذة وحدها تتعطّل
  }
}

function onSaved(orphan) {
  formOpen.value = false
  if (orphan?.id) router.push(`/orphans/${orphan.id}`)
  else load()
}

onMounted(loadReferences)
</script>

<template>
  <div>
    <PageHeader title="الأيتام" :subtitle="subtitle">
      <template #actions>
        <button class="btn btn-outline" type="button" :disabled="exporting || !meta.total" @click="exportCsv">
          <BaseIcon :name="exporting ? 'loader' : 'download'" :size="16" :class="exporting ? 'animate-spin' : ''" />
          تصدير
        </button>
        <button
          v-if="auth.can('orphans.create')" class="btn btn-primary" type="button"
          @click="formOpen = true"
        >
          <BaseIcon name="plus" :size="16" />
          ملف جديد
        </button>
      </template>
    </PageHeader>

    <StatusTabs
      v-model="filters.status" :options="STATUSES"
      :counts="meta.status_counts ?? {}" :total="meta.all_count ?? null"
      label="حالة الملف"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="ابحث بالاسم أو المعرّف أو رمز الأسرة…" :dirty="dirty" @reset="reset">
      <select v-if="countries.length" v-model="filters.country_id" class="input !w-auto text-sm" aria-label="الدولة">
        <option value="">كل الدول</option>
        <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>

      <select v-if="programs.length" v-model="filters.program_id" class="input !w-auto text-sm" aria-label="البرنامج">
        <option value="">كل البرامج</option>
        <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>

      <button type="button" class="chip" :aria-pressed="filters.urgent" @click="filters.urgent = !filters.urgent">
        <BaseIcon name="alert" :size="14" /> عاجل
      </button>
      <button type="button" class="chip" :aria-pressed="filters.unfunded" @click="filters.unfunded = !filters.unfunded">
        يحتاج كافلاً
      </button>
      <button type="button" class="chip" :aria-pressed="filters.unverified" @click="filters.unverified = !filters.unverified">
        بلا توثيق
      </button>

      <!-- يصل من شريط تغطية التقارير؛ يُرى ويُزال لا يختفي في الرابط -->
      <button v-if="filters.missing_report" type="button" class="chip" aria-pressed="true" @click="filters.missing_report = ''">
        بلا تقرير للربع {{ filters.missing_report.split('-')[1] }} من {{ filters.missing_report.split('-')[0] }}
        <BaseIcon name="close" :size="12" />
      </button>
    </FilterBar>

    <DataTable :error="error" @retry="load"
      :columns="columns" :rows="items" :loading="loading"
      :sort="filters.sort" :dir="filters.dir" @sort="onSort"
      :empty-text="dirty ? 'لا ملفات تطابق هذه الفلاتر.' : 'لا ملفات بعد.'"
      clickable @row-click="(r) => router.push(`/orphans/${r.id}`)"
    >
      <template #first_name="{ row }">
        <span class="flex items-center gap-3 min-w-0">
          <img
            v-if="row.photo_url" :src="row.photo_url" alt="" width="36" height="36"
            class="size-9 rounded-full object-cover shrink-0" loading="lazy"
          />
          <span v-else class="size-9 rounded-full bg-clay-50 text-clay-700 grid place-items-center text-sm font-semibold shrink-0">
            {{ row.first_name?.charAt(0) }}
          </span>
          <span class="min-w-0">
            <span class="flex items-center gap-1.5">
              <!-- رابط حقيقي: يُفتح في تبويب جديد ويُنسخ، والنقرة على الصف تبقى -->
              <RouterLink :to="`/orphans/${row.id}`" class="font-medium text-sand-900 hover:text-clay-700 truncate" @click.stop>
                {{ row.full_name ?? row.first_name }}
              </RouterLink>
              <span v-if="row.is_urgent" class="badge bg-rose-600/10 text-rose-700 !px-1.5">عاجل</span>
            </span>
            <span class="block num text-xs text-sand-500">{{ row.reference_code }}</span>
          </span>
        </span>
      </template>

      <template #age="{ row }">{{ years(row.age) }}</template>
      <template #country="{ row }">{{ row.family?.country?.name ?? EMPTY }}</template>
      <template #program="{ row }">{{ row.program?.name ?? EMPTY }}</template>

      <!-- لا شريط تمويل لملف لم يُنشر بعد: «0%» عليه يوحي بإهمالٍ لا وجود له -->
      <template #funded_percent="{ row }">
        <div v-if="['active', 'fully_funded'].includes(row.status)" class="min-w-36">
          <FundingBar :percent="row.funded_percent" />
          <p v-if="remaining(row) > 0.009" class="text-xs text-sand-500 mt-1">
            ينقصه <span class="num text-sand-700">{{ money(remaining(row)) }}</span>
          </p>
        </div>
        <span v-else class="text-xs text-sand-400">لم يُنشر بعد</span>
      </template>

      <template #monthly_need="{ row }"><span class="num">{{ money(row.monthly_need) }}</span></template>
      <template #status="{ row }"><StatusBadge :label="row.status_label" :value="row.status" /></template>

      <template #empty-action>
        <button v-if="dirty" class="btn btn-outline mt-4" type="button" @click="reset">مسح الفلاتر</button>
        <button v-else-if="auth.can('orphans.create')" class="btn btn-primary mt-4" type="button" @click="formOpen = true">
          <BaseIcon name="plus" :size="16" /> أنشئ أول ملف
        </button>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <OrphanFormModal
      :open="formOpen" :references="references"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

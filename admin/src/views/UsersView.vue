<script setup>
/**
 * فريق العمل: من يدخل اللوحة وبأي دور.
 *
 * الكفلاء ليسوا هنا (لهم تبويبهم). الأدوار تبويبات بأعدادها، ومن ينتظر
 * التفعيل أو موقوف شريحةٌ بعدده، والضغط على موظف يفتح درجه: ما يقدر عليه،
 * وآخر ما فعل، والإيقاف بتأكيد.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { dateTime, relative } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import UserFormModal from '@/components/UserFormModal.vue'
import UserDrawer from '@/components/UserDrawer.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const DEFAULTS = { q: '', role: '', status: '' }
const { items, meta, loading, filters, reset, load, error } = useResource(admin.users.list, DEFAULTS)

const dirty = computed(() => Boolean(filters.q || filters.status))

const ROLES = [
  { value: 'admin', label: 'مدراء' },
  { value: 'accountant', label: 'محاسبون' },
  { value: 'field_supervisor', label: 'مشرفون ميدانيون' },
]
const ROLE = { admin: 'مدير النظام', accountant: 'محاسب', field_supervisor: 'مشرف ميداني' }

const columns = [
  { key: 'name', label: 'الموظف' },
  { key: 'roles', label: 'الدور ونطاقه' },
  { key: 'last_login_at', label: 'آخر دخول' },
  { key: 'status', label: 'الحالة' },
]

/**
 * حسابٌ نشط لم يُستعمل منذ شهرين بابٌ مفتوح بلا حارس: موظفٌ غادر ولم
 * يُوقف حسابه، أو كلمة مرور منسيّة في مكان ما. يُعلَّم ليُراجَع.
 */
const DORMANT_DAYS = 60
function dormant(row) {
  if (row.status !== 'active' || !row.last_login_at) return false
  return (Date.now() - new Date(row.last_login_at)) / 86_400_000 > DORMANT_DAYS
}

const countries = ref([])
onMounted(async () => {
  try {
    const { data } = await admin.references()
    countries.value = data.data?.countries ?? []
  } catch {
    // حقل البلد في النموذج وحده يبقى فارغاً
  }
})

/* ── الدرج (?u=) والنموذج ────────────────────────────────────────────── */

const openId = computed(() => (route.query.u ? Number(route.query.u) : null))
function open(id) { router.replace({ query: { ...route.query, u: id } }) }
function close() { const { u: _, ...rest } = route.query; router.replace({ query: rest }) }

const drawer = ref(null)
const formOpen = ref(false)
const editing = ref(null)

function create() {
  editing.value = null
  formOpen.value = true
}
function edit(user) {
  editing.value = user
  formOpen.value = true
}
function onSaved(saved) {
  formOpen.value = false
  load()
  // موظفٌ جديد: يُفتح درجه ليُرى ما مُنح وأن الدعوة أُرسلت
  if (!editing.value && saved?.id) open(saved.id)
  else drawer.value?.reload()
}
</script>

<template>
  <div>
    <PageHeader title="المستخدمون" :subtitle="`فريق العمل في اللوحة: ${meta.all_count ?? meta.total} موظفين`">
      <template #actions>
        <button class="btn btn-primary" type="button" @click="create">
          <BaseIcon name="plus" :size="16" /> موظف جديد
        </button>
      </template>
    </PageHeader>

    <StatusTabs
      v-model="filters.role" :options="ROLES" :counts="meta.role_counts ?? {}" :total="meta.all_count ?? null"
      all-label="الكل" label="الدور"
    />

    <FilterBar v-model:search="filters.q" search-placeholder="اسم أو بريد…" :dirty="dirty" @reset="reset">
      <button
        type="button" class="chip" :aria-pressed="filters.status === 'pending'"
        @click="filters.status = filters.status === 'pending' ? '' : 'pending'"
      >بانتظار التفعيل <span class="chip__count">{{ meta.status_counts?.pending ?? 0 }}</span></button>
      <button
        type="button" class="chip" :aria-pressed="filters.status === 'suspended'"
        @click="filters.status = filters.status === 'suspended' ? '' : 'suspended'"
      >موقوفون <span class="chip__count">{{ meta.status_counts?.suspended ?? 0 }}</span></button>
    </FilterBar>

    <DataTable
      :error="error" :columns="columns" :rows="items" :loading="loading"
      clickable empty-text="لا موظفين بهذه الفلاتر."
      @retry="load" @row-click="(r) => open(r.id)"
    >
      <template #name="{ row }">
        <span class="flex items-center gap-3">
          <span
            class="size-9 shrink-0 rounded-full grid place-items-center text-sm font-semibold"
            :class="row.status === 'suspended' ? 'bg-sand-100 text-sand-400' : 'bg-clay-50 text-clay-700'" aria-hidden="true"
          >{{ row.name.trim().charAt(0) }}</span>
          <span class="min-w-0">
            <span class="font-medium" :class="{ 'text-sand-500': row.status === 'suspended' }">{{ row.name }}</span>
            <span v-if="row.id === auth.user?.id" class="text-xs text-sand-500"> (أنت)</span>
            <span class="block text-xs text-sand-500 num" dir="ltr">{{ row.email }}</span>
          </span>
        </span>
      </template>

      <template #roles="{ row }">
        <span class="text-sm">{{ ROLE[row.roles?.[0]] ?? row.roles?.[0] }}</span>
        <span class="block text-xs" :class="row.roles?.[0] === 'field_supervisor' && !row.staff_profile?.assigned_country_id ? 'text-rose-700' : 'text-sand-500'">
          <template v-if="row.roles?.[0] !== 'field_supervisor'">كل الدول</template>
          <template v-else-if="row.staff_profile?.assigned_country_name">{{ row.staff_profile.assigned_country_name }}</template>
          <template v-else>بلا بلد، لا يرى شيئاً</template>
        </span>
      </template>

      <template #last_login_at="{ row }">
        <span v-if="!row.last_login_at" class="text-sand-400">لم يدخل بعد</span>
        <span v-else :title="dateTime(row.last_login_at)" :class="{ 'text-amber-800': dormant(row) }">
          {{ relative(row.last_login_at) }}
          <span v-if="dormant(row)" class="block text-xs">خامل، راجع حاجته للحساب</span>
        </span>
      </template>

      <template #status="{ row }">
        <StatusBadge :label="row.status_label" :value="row.status" />
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <UserDrawer ref="drawer" :id="formOpen ? null : openId" @close="close" @changed="load" @edit="edit" />

    <UserFormModal
      :open="formOpen" :user="editing" :countries="countries"
      :current-user-id="auth.user?.id"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

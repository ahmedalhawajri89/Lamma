<script setup>
import { onMounted, ref } from 'vue'
import { useUiStore } from '@/stores/ui'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useAuthStore } from '@/stores/auth'
import { relative, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import InlineNote from '@/components/InlineNote.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import UserFormModal from '@/components/UserFormModal.vue'

const auth = useAuthStore()
const ui = useUiStore()

const STAFF_ROLES = ['admin', 'accountant', 'field_supervisor']
const isStaffRow = (row) => row.roles?.some((r) => STAFF_ROLES.includes(r))

const { items, meta, loading, filters, reset, load } = useResource(admin.users.list, {
  q: '', role: '', staff_only: true,
})

const columns = [
  { key: 'name', label: 'الاسم' },
  { key: 'roles', label: 'الدور' },
  { key: 'country', label: 'البلد المُسنَد' },
  { key: 'last_login_at', label: 'آخر دخول' },
  { key: 'status', label: 'الحالة' },
]

const formOpen = ref(false)
const editing = ref(null)
const countries = ref([])

const countryName = (row) =>
  row.staff_profile?.assigned_country_name
  ?? countries.value.find((c) => c.id === row.staff_profile?.assigned_country_id)?.name
  ?? EMPTY

async function loadCountries() {
  if (!auth.can('users.manage')) return

  try {
    const { data } = await admin.references()
    countries.value = data.data?.countries ?? []
  } catch {
    // فشل القائمة لا يمنع تصفّح الجدول — حقل البلد وحده يبقى فارغاً
  }
}

function create() {
  editing.value = null
  formOpen.value = true
}

function edit(user) {
  // حسابات الكفلاء تُدار من بوابتهم، لا من شاشة فريق العمل
  if (!auth.can('users.manage') || !isStaffRow(user)) return
  editing.value = user
  formOpen.value = true
}

const inviting = ref(null)

async function resendInvite(row) {
  inviting.value = row.id
  try {
    const { data } = await admin.users.invite(row.id)
    ui.success(data.message)
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    inviting.value = null
  }
}

function onSaved() {
  formOpen.value = false
  load()
}

onMounted(loadCountries)

const roleLabels = {
  admin: 'مدير النظام',
  accountant: 'محاسب',
  field_supervisor: 'مشرف ميداني',
  sponsor: 'كافل',
}
</script>

<template>
  <div>
    <PageHeader title="المستخدمون" :subtitle="`${meta.total} حساب`">
      <template #actions>
        <button
          v-if="auth.can('users.manage')" class="btn btn-primary" type="button"
          @click="create"
        >
          <BaseIcon name="plus" :size="16" />
          موظف جديد
        </button>
      </template>
    </PageHeader>

    <InlineNote>
      المشرف الميداني يرى أيتام بلده المُسنَد وحده، والنظام نفسه يمنعه من غيرها لا الأزرار وحدها.
    </InlineNote>

    <FilterBar v-model:search="filters.q" search-placeholder="اسم أو بريد…" @reset="reset">
      <select v-model="filters.role" class="input !w-auto text-sm" aria-label="الدور">
        <option value="">كل الأدوار</option>
        <option value="admin">مدير النظام</option>
        <option value="accountant">محاسب</option>
        <option value="field_supervisor">مشرف ميداني</option>
        <option value="sponsor">كافل</option>
      </select>
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.staff_only" type="checkbox" class="accent-clay-600" /> الموظفون فقط
      </label>
    </FilterBar>

    <DataTable
      :columns="columns" :rows="items" :loading="loading"
      :clickable="auth.can('users.manage')"
      @row-click="edit"
    >
      <template #name="{ row }">
        <span class="font-medium">{{ row.name }}</span>
        <span class="block text-xs text-sand-500 num" dir="ltr">{{ row.email }}</span>
      </template>
      <template #roles="{ row }">
        <span v-for="r in row.roles" :key="r" class="badge bg-clay-50 text-clay-700 me-1">
          {{ roleLabels[r] ?? r }}
        </span>
      </template>
      <template #country="{ row }">
        <span v-if="!row.staff_profile" class="text-sand-400">{{ EMPTY }}</span>
        <span v-else-if="row.roles?.includes('admin')" class="text-sand-500">كل الدول</span>
        <span v-else-if="row.staff_profile.assigned_country_id">{{ countryName(row) }}</span>
        <span v-else-if="row.roles?.includes('field_supervisor')" class="text-rose-700">بلا بلد، لا يرى شيئاً</span>
        <span v-else class="text-sand-500">كل الدول</span>
      </template>
      <template #last_login_at="{ row }">{{ relative(row.last_login_at) }}</template>
      <template #status="{ row }">
        <span class="inline-flex items-center gap-2">
          <StatusBadge :label="row.status_label" :value="row.status" />
          <button
            v-if="row.status === 'pending' && isStaffRow(row) && auth.can('users.manage')"
            class="text-xs text-clay-700 hover:underline disabled:opacity-50" type="button"
            :disabled="inviting === row.id" @click.stop="resendInvite(row)"
          >
            إعادة إرسال الدعوة
          </button>
        </span>
      </template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <UserFormModal
      :open="formOpen" :user="editing" :countries="countries"
      :current-user-id="auth.user?.id"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

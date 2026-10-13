<script setup>
import { ref } from 'vue'
import { admin } from '@/api/endpoints'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import FormModal from '@/components/FormModal.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { useResource } from '@/composables/useResource'
import { money, date, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import DataTable from '@/components/DataTable.vue'
import PaginationBar from '@/components/PaginationBar.vue'

const { items, meta, loading, filters, reset, error, load } = useResource(admin.sponsors.list, {
  q: '', active_only: false, zakat_only: false,
})

const columns = [
  { key: 'sponsor_code', label: 'الرمز' },
  { key: 'name', label: 'الاسم' },
  { key: 'country', label: 'الدولة' },
  { key: 'active_sponsorships_count', label: 'كفالات نشطة', align: 'end' },
  { key: 'total_given', label: 'إجمالي ما قدّمه', align: 'end' },
  { key: 'sponsoring_since', label: 'كافل منذ' },
]

const auth = useAuthStore()
const ui = useUiStore()

/**
 * تفاصيل الكافل: كفالاته وملاحظات الإدارة. كانت نقطة التفاصيل والتعديل
 * موجودتين في الخادم بلا شاشة تصلهما.
 */
const viewing = ref(null)
const detail = ref(null)
const notes = ref('')
const saving = ref(false)

async function open(row) {
  viewing.value = row
  detail.value = null
  try {
    const { data } = await admin.sponsors.show(row.id)
    detail.value = data.data
    notes.value = data.data.internal_notes ?? ''
  } catch (e) {
    ui.error(e.userMessage)
    viewing.value = null
  }
}

async function saveNotes() {
  saving.value = true
  try {
    await admin.sponsors.update(viewing.value.id, { internal_notes: notes.value || null })
    ui.success('حُفظت الملاحظات.')
  } catch (e) {
    ui.error(e.userMessage)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader title="الكفلاء" :subtitle="`${meta.total} كافل`" />

    <FilterBar v-model:search="filters.q" search-placeholder="اسم أو بريد أو رمز…" @reset="reset">
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.active_only" type="checkbox" class="accent-clay-600" /> نشط فقط
      </label>
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.zakat_only" type="checkbox" class="accent-clay-600" /> يفضّل الزكاة
      </label>
    </FilterBar>

    <DataTable :error="error" @retry="load" :columns="columns" :rows="items" :loading="loading" clickable @row-click="open">
      <template #sponsor_code="{ row }"><span class="num text-xs text-sand-600">{{ row.sponsor_code }}</span></template>
      <template #name="{ row }">
        <!--
          المجهولية للموقع العام لا للإدارة: كان يظهر «كافل يفضّل عدم ذكر اسمه»
          بلا بريد، فلا يعرف الموظف مع من يتعامل، والبحث بالاسم يجده مع ذلك
        -->
        <span class="font-medium">{{ row.name ?? EMPTY }}</span>
        <span v-if="row.is_anonymous" class="badge bg-sand-100 text-sand-600 ms-1.5">مجهول للعامة</span>
        <span class="block text-xs text-sand-500 num" dir="ltr">{{ row.email }}</span>
      </template>
      <template #country="{ row }">{{ row.country?.name ?? EMPTY }}</template>
      <template #active_sponsorships_count="{ row }">
        <span class="num">{{ row.active_sponsorships_count ?? 0 }}</span>
      </template>
      <template #total_given="{ row }"><span class="num font-medium">{{ money(row.total_given ?? 0) }}</span></template>
      <template #sponsoring_since="{ row }">{{ date(row.sponsoring_since) }}</template>
    </DataTable>

    <PaginationBar v-model="filters.page" :meta="meta" />

    <FormModal
      :open="viewing !== null" wide
      :title="viewing?.name ?? 'الكافل'" :subtitle="viewing ? `${viewing.sponsor_code}${viewing.is_anonymous ? ' · مجهول للعامة' : ''}` : ''"
      @close="viewing = null"
    >
      <div v-if="!detail" class="py-8 grid place-items-center text-sand-500">
        <span class="text-sm">جارٍ التحميل…</span>
      </div>

      <template v-else>
        <dl class="grid sm:grid-cols-3 gap-3 text-sm">
          <div><dt class="text-xs text-sand-500">البريد</dt><dd class="num" dir="ltr">{{ detail.email ?? EMPTY }}</dd></div>
          <div><dt class="text-xs text-sand-500">الهاتف</dt><dd class="num" dir="ltr">{{ detail.phone ?? EMPTY }}</dd></div>
          <div><dt class="text-xs text-sand-500">الدولة</dt><dd>{{ detail.country?.name ?? EMPTY }}<template v-if="detail.city">، {{ detail.city }}</template></dd></div>
          <div><dt class="text-xs text-sand-500">العملة المفضّلة</dt><dd class="num">{{ detail.preferred_currency ?? EMPTY }}</dd></div>
          <div><dt class="text-xs text-sand-500">يفضّل الزكاة</dt><dd>{{ detail.prefers_zakat ? 'نعم' : 'لا' }}</dd></div>
          <div><dt class="text-xs text-sand-500">كافل منذ</dt><dd>{{ date(detail.sponsoring_since) }}</dd></div>
        </dl>

        <section>
          <h3 class="text-xs font-semibold text-sand-500 mb-2">الكفالات</h3>
          <ul v-if="detail.sponsorships?.length" class="divide-y divide-sand-100 border border-sand-100 rounded-xl">
            <li v-for="s in detail.sponsorships" :key="s.id" class="flex items-center gap-3 px-3 py-2.5 text-sm">
              <RouterLink :to="`/orphans/${s.orphan?.id}`" class="flex-1 min-w-0 text-clay-700 hover:underline truncate">
                {{ s.orphan?.name }} <span class="text-xs text-sand-500 num">{{ s.reference_code }}</span>
              </RouterLink>
              <span v-if="s.monthly_amount !== undefined" class="num text-sand-700">{{ money(s.monthly_amount, s.currency) }}</span>
              <StatusBadge :label="s.status_label" :value="s.status" />
            </li>
          </ul>
          <p v-else class="text-sm text-sand-500">لا كفالات بعد.</p>
        </section>

        <section v-if="auth.can('sponsors.manage')">
          <label class="block text-xs font-semibold text-sand-500 mb-1.5" for="sponsorNotes">ملاحظات الإدارة (لا يراها الكافل)</label>
          <textarea id="sponsorNotes" v-model="notes" class="input" rows="3" maxlength="2000" />
        </section>
      </template>

      <template #actions="{ close }">
        <button class="btn btn-outline" type="button" @click="close">إغلاق</button>
        <button
          v-if="detail && auth.can('sponsors.manage')" class="btn btn-primary" type="button"
          :disabled="saving" @click="saveNotes"
        >حفظ الملاحظات</button>
      </template>
    </FormModal>
  </div>
</template>

<script setup>
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { relative, dateTime } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const { items, meta, loading, filters, reset } = useResource(admin.activity, {
  subject_type: '', event: '',
})

const eventLabels = { created: 'أنشأ', updated: 'عدّل', deleted: 'حذف' }
const subjectLabels = { Orphan: 'ملف يتيم', Sponsorship: 'كفالة', Payment: 'دفعة' }

/** أسماء الحقول المسجَّلة بالعربية (من `logOnly` في النماذج)؛ غير المعروف يُعرض كما هو. */
const fieldLabels = {
  status: 'الحالة',
  is_urgent: 'عاجل',
  monthly_need: 'الحاجة الشهرية',
  monthly_amount: 'المبلغ الشهري',
  program_id: 'البرنامج',
  verified_at: 'تاريخ التوثيق',
  published_at: 'تاريخ النشر',
  next_due_at: 'القسط القادم',
  share_percent: 'نسبة الحصة',
  cancellation_reason: 'سبب الإلغاء',
}
</script>

<template>
  <div>
    <PageHeader
      title="سجل النشاط"
      subtitle="من غيّر ماذا ومتى، وقيمة الحقل قبل التغيير وبعده."
    />

    <FilterBar :has-search="false" @reset="reset">
      <select v-model="filters.subject_type" class="input !w-auto text-sm" aria-label="النوع">
        <option value="">كل الأنواع</option>
        <option value="Orphan">ملفات الأيتام</option>
        <option value="Sponsorship">الكفالات</option>
      </select>
      <select v-model="filters.event" class="input !w-auto text-sm" aria-label="الحدث">
        <option value="">كل الأحداث</option>
        <option value="created">إنشاء</option>
        <option value="updated">تعديل</option>
        <option value="deleted">حذف</option>
      </select>
    </FilterBar>

    <div v-if="loading" class="space-y-2">
      <div v-for="n in 6" :key="n" class="card h-16 animate-pulse bg-sand-100" />
    </div>

    <ol v-else-if="items.length" class="card divide-y divide-sand-100">
      <li v-for="a in items" :key="a.id" class="p-4 flex gap-3">
        <span class="size-8 shrink-0 grid place-items-center rounded-lg bg-sand-100 text-sand-600">
          <BaseIcon name="history" :size="16" />
        </span>

        <div class="min-w-0 flex-1">
          <p class="text-sm">
            <span class="font-medium">{{ a.causer ?? 'النظام' }}</span>
            <span class="text-sand-600"> {{ eventLabels[a.event] ?? a.event }} </span>
            <span class="text-sand-800">{{ subjectLabels[a.subject_type] ?? a.subject_type }}</span>
            <span class="num text-xs text-sand-500"> #{{ a.subject_id }}</span>
          </p>

          <div
            v-if="Object.keys(a.changes.after ?? {}).length"
            class="mt-1.5 text-xs text-sand-600 flex flex-wrap gap-x-3 gap-y-0.5"
          >
            <span v-for="(v, key) in a.changes.after" :key="key">
              <span class="text-sand-500">{{ fieldLabels[key] ?? key }}:</span>
              <span v-if="a.changes.before?.[key] !== undefined" class="line-through text-sand-400 mx-1">
                {{ a.changes.before[key] }}
              </span>
              <span class="text-sand-800">{{ v }}</span>
            </span>
          </div>

          <p class="text-xs text-sand-400 mt-1" :title="dateTime(a.created_at)">{{ relative(a.created_at) }}</p>
        </div>
      </li>
    </ol>

    <p v-else class="card p-10 text-center text-sand-500">
      السجل فارغ، وسيمتلئ مع أول تعديل يجري من اللوحة.
    </p>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

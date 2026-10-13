<script setup>
/**
 * سجل النشاط: من غيّر ماذا ومتى.
 *
 * الخادم يصوغ كل سطر (ActivityFormatter): الفعل بالعربية، والسجلّ باسمه أو
 * مرجعه مع رابطه، والقيم بتسمياتها. هذه الشاشة تعرض وتفلتر فقط، فلا تتكرر
 * هنا قوائم الحقول والحالات.
 */
import { computed, ref, watch } from 'vue'
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { relative, dateTime } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const { items, meta, loading, error, filters, reset, load } = useResource(admin.activity, {
  subject_type: '', causer_id: '', from: '', to: '',
})

// قوائم الفلاتر تصل مع أول صفحة وتبقى
const types = ref([])
const actors = ref([])
watch(meta, (m) => {
  if (m?.subject_types?.length) types.value = m.subject_types
  if (m?.actors?.length) actors.value = m.actors
})

const ICONS = {
  created: 'plus',
  updated: 'edit',
  deleted: 'trash',
  approved: 'check',
  reviewed: 'chat',
  recorded: 'receipt',
  refunded: 'undo',
  verified: 'shield',
  document_verified: 'file-check',
  status_changed: 'sliders',
  settings_updated: 'sliders',
}

const TONES = {
  deleted: 'bg-rose-600/10 text-rose-700',
  refunded: 'bg-rose-600/10 text-rose-700',
  approved: 'bg-olive-50 text-olive-700',
  document_verified: 'bg-olive-50 text-olive-700',
}

const icon = (a) => ICONS[a.event] ?? 'history'
const tone = (a) => TONES[a.event] ?? 'bg-sand-100 text-sand-600'

const hasFilters = computed(() => Object.entries(filters).some(([k, v]) => k !== 'page' && v))
</script>

<template>
  <div>
    <PageHeader
      title="سجل النشاط"
      subtitle="من غيّر ماذا ومتى، وقيمة الحقل قبل التغيير وبعده."
    />

    <FilterBar :has-search="false" @reset="reset">
      <select v-model="filters.subject_type" class="input !w-auto text-sm" aria-label="نوع السجل">
        <option value="">كل الأنواع</option>
        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
      </select>
      <select v-model="filters.causer_id" class="input !w-auto text-sm" aria-label="من قام به">
        <option value="">كل المستخدمين</option>
        <option v-for="u in actors" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <label class="flex items-center gap-1.5 text-sm text-sand-600">
        من
        <input v-model="filters.from" type="date" class="input !w-auto text-sm" />
      </label>
      <label class="flex items-center gap-1.5 text-sm text-sand-600">
        إلى
        <input v-model="filters.to" type="date" class="input !w-auto text-sm" />
      </label>
    </FilterBar>

    <div v-if="loading" class="card divide-y divide-sand-100" aria-hidden="true">
      <div v-for="n in 6" :key="n" class="h-[4.5rem] animate-pulse bg-sand-50" />
    </div>

    <div v-else-if="error" class="card p-8 text-center" role="alert">
      <p class="font-medium text-sand-900">تعذّر تحميل السجل</p>
      <p class="text-sm text-sand-500 mt-1">{{ error }}</p>
      <button class="btn btn-outline mt-4" type="button" @click="load">إعادة المحاولة</button>
    </div>

    <ol v-else-if="items.length" class="card divide-y divide-sand-100">
      <li v-for="a in items" :key="a.id" class="p-4 flex gap-3">
        <span class="size-9 shrink-0 grid place-items-center rounded-lg" :class="tone(a)" aria-hidden="true">
          <BaseIcon :name="icon(a)" :size="16" />
        </span>

        <div class="min-w-0 flex-1">
          <p class="text-sm leading-relaxed">
            <span class="font-medium text-sand-900">{{ a.actor }}</span>
            {{ ' ' }}<span class="text-sand-700">{{ a.action }}</span>
            <template v-if="a.subject_name">
              {{ ' ' }}<RouterLink
                v-if="a.subject_path" :to="a.subject_path"
                class="text-clay-700 hover:underline font-medium"
              >{{ a.subject_name }}</RouterLink>
              <span v-else class="font-medium text-sand-800">{{ a.subject_name }}</span>
            </template>
          </p>

          <ul v-if="a.changes.length" class="mt-2 space-y-1 text-[0.8rem]">
            <li v-for="c in a.changes" :key="c.field" class="flex flex-wrap items-baseline gap-x-2">
              <span class="text-sand-500">{{ c.label }}:</span>
              <template v-if="c.before !== null && c.after !== null">
                <span class="text-sand-400 line-through">{{ c.before }}</span>
                <BaseIcon name="chevron" :size="12" class="text-sand-400 self-center" style="transform: scaleX(-1)" />
                <span class="text-sand-900">{{ c.after }}</span>
              </template>
              <span v-else-if="c.after !== null" class="text-sand-900">{{ c.after }}</span>
              <span v-else class="text-sand-500">كان: {{ c.before }}</span>
            </li>
          </ul>

          <p v-if="a.note" class="mt-2 text-[0.8rem] text-sand-700 bg-sand-50 rounded-lg px-3 py-1.5">
            <span class="text-sand-500">السبب:</span> {{ a.note }}
          </p>

          <p class="text-xs text-sand-400 mt-1.5">
            <time :datetime="a.created_at" :title="dateTime(a.created_at)">{{ relative(a.created_at) }}</time>
          </p>
        </div>
      </li>
    </ol>

    <p v-else class="card p-10 text-center text-sand-500">
      {{ hasFilters ? 'لا نشاط يطابق هذه الفلاتر.' : 'السجل فارغ، وسيمتلئ مع أول تعديل يجري من اللوحة.' }}
    </p>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

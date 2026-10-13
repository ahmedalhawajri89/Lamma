<script setup>
import { admin } from '@/api/endpoints'
import { useResource } from '@/composables/useResource'
import { useUiStore } from '@/stores/ui'
import { relative, dateTime } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import FilterBar from '@/components/FilterBar.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const ui = useUiStore()
const { items, meta, loading, error, filters, reset, load } = useResource(admin.contacts, {
  open_only: true,
})

const labels = { new: 'جديدة', in_progress: 'قيد المعالجة', closed: 'مغلقة' }

/** مواضيع نموذج «تواصل معنا» كما في الموقع (contact.html)؛ كانت تُعرض «general» */
const TOPICS = {
  general: 'استفسار عام',
  sponsorship: 'سؤال عن الكفالة',
  finance: 'استفسار مالي أو عن الميزانية',
  report: 'مشكلة في تقرير',
  partner: 'شراكة أو تطوّع',
  complaint: 'شكوى',
}

/**
 * وعد الرد خلال 72 ساعة، معروضاً على كل رسالة مفتوحة: كم بقي، أو كم تأخرت.
 * بلا هذا لا يعرف من يفتح القائمة أيّ الرسائل يوشك أن يُخلَف وعدها.
 */
const PROMISE_HOURS = 72
function deadline(m) {
  if (m.status === 'closed') return null
  const left = Math.round(PROMISE_HOURS - (Date.now() - new Date(m.created_at)) / 3_600_000)
  if (left < 0) return { text: `متأخرة ${Math.abs(left)} ساعة عن وعد الرد`, late: true }
  return { text: `تبقّى ${left} ساعة للرد`, late: left <= 12 }
}

const replyHref = (m) =>
  `mailto:${m.email}?subject=${encodeURIComponent(`رد: ${TOPICS[m.subject] ?? m.subject ?? 'رسالتك إلى كفالة'}`)}`

async function setStatus(row, status) {
  try {
    await admin.updateContact(row.id, status)
    ui.success('حُدّثت حالة الرسالة.')
    load()
  } catch (e) {
    ui.error(e.userMessage)
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="رسائل التواصل"
      :subtitle="`${meta.total} رسالة من نموذج «تواصل معنا» على الموقع`"
    />

    <FilterBar :has-search="false" @reset="reset">
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.open_only" type="checkbox" class="accent-clay-600" />
        المفتوحة فقط
      </label>
    </FilterBar>

    <div v-if="loading" class="space-y-2">
      <div v-for="n in 4" :key="n" class="card h-28 animate-pulse bg-sand-100" />
    </div>

    <div v-else-if="error" class="card p-8 text-center" role="alert">
      <p class="font-medium text-sand-900">تعذّر تحميل البيانات</p>
      <p class="text-sm text-sand-500 mt-1">{{ error }}</p>
      <button class="btn btn-outline mt-4" type="button" @click="load">إعادة المحاولة</button>
    </div>

    <ul v-else-if="items.length" class="card divide-y divide-sand-100">
      <li v-for="m in items" :key="m.id" class="p-4">
        <div class="flex items-start justify-between gap-3 flex-wrap mb-2">
          <div class="min-w-0">
            <p class="text-sm font-medium">
              {{ m.name }}
              <a
                :href="`mailto:${m.email}`" dir="ltr"
                class="num text-xs text-clay-700 hover:underline ms-1"
              >{{ m.email }}</a>
            </p>
            <p class="text-xs text-sand-500 mt-0.5">
              <span v-if="m.subject">{{ TOPICS[m.subject] ?? m.subject }} · </span>
              <span :title="dateTime(m.created_at)">{{ relative(m.created_at) }}</span>
              <span v-if="m.handler"> · عالجها {{ m.handler.name }}</span>
            </p>
          </div>

          <span class="flex flex-col items-end gap-1 shrink-0">
            <StatusBadge :label="labels[m.status] ?? m.status" :value="m.status" />
            <span
              v-if="deadline(m)" class="text-[0.7rem] font-medium"
              :class="deadline(m).late ? 'text-rose-700' : 'text-sand-500'"
            >{{ deadline(m).text }}</span>
          </span>
        </div>

        <p class="text-sm text-sand-800 leading-relaxed whitespace-pre-line">{{ m.body }}</p>

        <div class="flex gap-2 mt-3 pt-3 border-t border-sand-100 flex-wrap">
          <a class="btn btn-outline !py-1.5 text-xs" :href="replyHref(m)">
            <BaseIcon name="external" :size="14" /> الردّ بالبريد
          </a>

          <button
            v-if="m.status === 'new'" class="btn btn-ghost !py-1.5 text-xs" type="button"
            @click="setStatus(m, 'in_progress')"
          >
            بدء المعالجة
          </button>

          <button
            v-if="m.status !== 'closed'" class="btn btn-ghost !py-1.5 text-xs" type="button"
            @click="setStatus(m, 'closed')"
          >
            إغلاق
          </button>

          <button
            v-else class="btn btn-ghost !py-1.5 text-xs" type="button"
            @click="setStatus(m, 'new')"
          >
            إعادة فتح
          </button>
        </div>
      </li>
    </ul>

    <p v-else class="card p-10 text-center text-sand-500">
      لا رسائل في هذا التصنيف.
    </p>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

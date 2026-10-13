<script setup>
import { nextTick } from 'vue'
import { admin } from '@/api/endpoints'
import { confirmDialog } from '@/composables/useConfirm'
import { useResource } from '@/composables/useResource'
import { useUiStore } from '@/stores/ui'
import { useQueueStore } from '@/stores/queue'
import { dateTime, relative, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import InlineNote from '@/components/InlineNote.vue'
import FilterBar from '@/components/FilterBar.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const ui = useUiStore()
const queue = useQueueStore()
const { items, meta, loading, error, filters, reset, load } = useResource(admin.messages.list, {
  status: '', queue: true,
})

async function review(row, decision) {
  let reason = null

  if (decision === 'reject') {
    const answer = await confirmDialog({
      title: 'رفض الرسالة',
      message: 'لن تصل الرسالة إلى الطفل. اكتب السبب ليعرف الكافل ما ينبغي تعديله.',
      reason: 'required',
      reasonLabel: 'سبب الرفض',
      confirmLabel: 'ارفض الرسالة',
      danger: true,
    })
    if (!answer) return
    reason = answer.reason
  }

  try {
    await admin.messages.review(row.id, { decision, reason })
    ui.success(decision === 'approve' ? 'اعتُمدت الرسالة.' : 'رُفضت الرسالة.')
    queue.refresh(true)

    // خارج الطابور، أو في صفحة غير الأولى (تنزاح صفحات الخادم بعد خروج الرسالة)
    if (!filters.queue || filters.page > 1) return load()

    // في الطابور تخرج الرسالة مكانها، وينتقل التركيز إلى التالية فتُراجع
    // القائمة كلها من لوحة المفاتيح دون إعادة تحميل تقفز بالصفحة
    const index = items.value.findIndex((m) => m.id === row.id)
    items.value = items.value.filter((m) => m.id !== row.id)
    meta.value = { ...meta.value, pending: Math.max(0, (meta.value.pending ?? 1) - 1) }

    if (!items.value.length) return load()

    await nextTick()
    const next = items.value[Math.min(index, items.value.length - 1)]
    document.querySelector(`[data-message="${next.id}"] .js-approve`)?.focus()
  } catch (e) {
    ui.error(e.userMessage)
  }
}
</script>

<template>
  <div>
    <PageHeader title="الرسائل" :subtitle="`${meta.pending ?? 0} رسالة بانتظار المراجعة`" />

    <InlineNote>
      كل رسالة تمرّ بمراجعة قبل وصولها للطفل، تطبيقاً لسياسة حماية الطفل المعلنة على الموقع.
    </InlineNote>

    <FilterBar :has-search="false" @reset="reset">
      <label class="flex items-center gap-1.5 text-sm cursor-pointer">
        <input v-model="filters.queue" type="checkbox" class="accent-clay-600" /> بانتظار المراجعة فقط
      </label>
      <select v-if="!filters.queue" v-model="filters.status" class="input !w-auto text-sm" aria-label="الحالة">
        <option value="">كل الحالات</option>
        <option value="pending_review">بانتظار المراجعة</option>
        <option value="approved">معتمدة</option>
        <option value="rejected">مرفوضة</option>
      </select>
    </FilterBar>

    <div v-if="loading" class="space-y-2">
      <div v-for="n in 4" :key="n" class="card h-24 animate-pulse bg-sand-100" />
    </div>

    <div v-else-if="error" class="card p-8 text-center" role="alert">
      <p class="font-medium text-sand-900">تعذّر تحميل البيانات</p>
      <p class="text-sm text-sand-500 mt-1">{{ error }}</p>
      <button class="btn btn-outline mt-4" type="button" @click="load">إعادة المحاولة</button>
    </div>

    <ul v-else-if="items.length" class="card divide-y divide-sand-100">
      <li v-for="m in items" :key="m.id" class="p-4" :data-message="m.id">
        <div class="flex items-start justify-between gap-3 mb-2">
          <div class="text-xs text-sand-500">
            <span class="num">{{ m.sponsorship?.reference_code }}</span>
            ·
            <RouterLink
              v-if="m.sponsorship?.orphan_id" :to="`/orphans/${m.sponsorship.orphan_id}`"
              class="text-clay-700 hover:underline"
            >{{ m.sponsorship?.orphan_name }}</RouterLink>
            <template v-else>{{ m.sponsorship?.orphan_name ?? EMPTY }}</template>
            · {{ m.sender_type === 'sponsor' ? 'من الكافل' : 'من الطفل' }}
            · <time :datetime="m.created_at" :title="dateTime(m.created_at)">{{ relative(m.created_at) }}</time>
            <template v-if="m.reviewer"> · راجعها {{ m.reviewer }}</template>
          </div>
          <StatusBadge :label="m.status_label" :value="m.status" />
        </div>

        <!-- أسطر الرسالة كما كتبها صاحبها -->
        <p class="text-sm text-sand-800 leading-relaxed whitespace-pre-line">{{ m.body }}</p>

        <div v-if="m.status === 'pending_review'" class="flex gap-2 mt-3 pt-3 border-t border-sand-100">
          <button class="js-approve btn btn-primary !py-1.5 text-xs" type="button" @click="review(m, 'approve')">
            اعتماد
          </button>
          <button class="btn btn-outline !py-1.5 text-xs text-rose-700" type="button" @click="review(m, 'reject')">
            رفض
          </button>
        </div>

        <p v-else-if="m.rejection_reason" class="text-xs text-rose-700 mt-2">
          سبب الرفض: {{ m.rejection_reason }}
        </p>
      </li>
    </ul>

    <p v-else class="card p-10 text-center text-sand-500">لا رسائل في هذا التصنيف.</p>

    <PaginationBar v-model="filters.page" :meta="meta" />
  </div>
</template>

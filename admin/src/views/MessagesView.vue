<script setup>
import { admin } from '@/api/endpoints'
import { confirmDialog } from '@/composables/useConfirm'
import { useResource } from '@/composables/useResource'
import { useUiStore } from '@/stores/ui'
import { dateTime, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import InlineNote from '@/components/InlineNote.vue'
import FilterBar from '@/components/FilterBar.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const ui = useUiStore()
const { items, meta, loading, filters, reset, load } = useResource(admin.messages.list, {
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
    load()
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
    </FilterBar>

    <div v-if="loading" class="space-y-2">
      <div v-for="n in 4" :key="n" class="card h-24 animate-pulse bg-sand-100" />
    </div>

    <ul v-else-if="items.length" class="card divide-y divide-sand-100">
      <li v-for="m in items" :key="m.id" class="p-4">
        <div class="flex items-start justify-between gap-3 mb-2">
          <div class="text-xs text-sand-500">
            <span class="num">{{ m.sponsorship?.reference_code }}</span>
            · {{ m.sponsorship?.orphan_name ?? EMPTY }}
            · {{ m.sender_type === 'sponsor' ? 'من الكافل' : 'من الطفل' }}
            · {{ dateTime(m.created_at) }}
          </div>
          <StatusBadge :label="m.status_label" :value="m.status" />
        </div>

        <p class="text-sm text-sand-800 leading-relaxed">{{ m.body }}</p>

        <div v-if="m.status === 'pending_review'" class="flex gap-2 mt-3 pt-3 border-t border-sand-100">
          <button class="btn btn-primary !py-1.5 text-xs" type="button" @click="review(m, 'approve')">
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

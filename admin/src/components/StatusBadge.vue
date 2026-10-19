<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: String, default: '' },
})

/** الحالة تُترجَم إلى لون مرة واحدة هنا، لا في كل جدول. */
const tones = {
  active: 'bg-olive-100 text-olive-700',
  succeeded: 'bg-olive-100 text-olive-700',
  approved: 'bg-olive-100 text-olive-700',
  published: 'bg-olive-100 text-olive-700',
  fully_funded: 'bg-olive-100 text-olive-700',
  delivered: 'bg-olive-100 text-olive-700',

  // الجمعيات الشريكة: الموثّقة كالنشطة، وما ينتظر المراجعة كالمعلّق
  verified: 'bg-olive-100 text-olive-700',
  under_review: 'bg-amber-100 text-amber-700',
  applied: 'bg-amber-100 text-amber-700',

  pending: 'bg-amber-100 text-amber-700',
  pending_verification: 'bg-amber-100 text-amber-700',
  pending_review: 'bg-amber-100 text-amber-700',
  submitted: 'bg-amber-100 text-amber-700',
  processing: 'bg-amber-100 text-amber-700',
  paused: 'bg-amber-100 text-amber-700',
  scheduled: 'bg-amber-100 text-amber-700',

  failed: 'bg-rose-600/10 text-rose-700',
  rejected: 'bg-rose-600/10 text-rose-700',
  cancelled: 'bg-rose-600/10 text-rose-700',
  deceased: 'bg-rose-600/10 text-rose-700',
  suspended: 'bg-rose-600/10 text-rose-700',

  draft: 'bg-sand-100 text-sand-600',
  withdrawn: 'bg-sand-100 text-sand-600',
  graduated: 'bg-teal-600/10 text-teal-700',
  completed: 'bg-teal-600/10 text-teal-700',
  refunded: 'bg-sand-100 text-sand-600',

  // رسائل التواصل: الجديدة تُرى أولاً، والمغلقة تهدأ
  new: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-teal-600/10 text-teal-700',
  closed: 'bg-sand-100 text-sand-600',
  archived: 'bg-sand-100 text-sand-600',
}
</script>

<template>
  <span class="badge" :class="tones[value] ?? 'bg-sand-100 text-sand-700'">{{ label }}</span>
</template>

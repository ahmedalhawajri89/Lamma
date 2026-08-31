<script setup>
import { fundingTone } from '@/utils/format'

defineProps({
  percent: { type: Number, default: 0 },
  showLabel: { type: Boolean, default: true },
})

const fills = {
  olive: 'bg-olive-600',
  amber: 'bg-amber-500',
  clay: 'bg-clay-500',
  rose: 'bg-rose-600',
}
</script>

<template>
  <div class="flex items-center gap-2 min-w-28">
    <div
      class="h-1.5 flex-1 rounded-full bg-sand-200 overflow-hidden"
      role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100"
      :aria-label="`نسبة التمويل ${percent}%`"
    >
      <div
        class="h-full rounded-full transition-[width] duration-500"
        :class="fills[fundingTone(percent)]"
        :style="{ width: `${Math.min(100, percent)}%` }"
      />
    </div>
    <span v-if="showLabel" class="num text-xs text-sand-600 w-9 text-left">{{ percent }}%</span>
  </div>
</template>

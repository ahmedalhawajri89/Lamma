<script setup>
import BaseIcon from './BaseIcon.vue'
import { num, money } from '@/utils/format'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: null },
  format: { type: String, default: 'number' },   // number | money | raw
  icon: { type: String, default: 'gauge' },
  tone: { type: String, default: 'clay' },       // clay | olive | amber | teal | rose
  change: { type: Number, default: null },
  hint: { type: String, default: '' },
  to: { type: [String, Object], default: null },
})

const tones = {
  clay: 'bg-clay-50 text-clay-700',
  olive: 'bg-olive-50 text-olive-700',
  amber: 'bg-amber-50 text-amber-700',
  teal: 'bg-teal-600/8 text-teal-700',
  rose: 'bg-rose-600/8 text-rose-700',
}

function display() {
  if (props.value === null || props.value === undefined) return '—'
  if (props.format === 'money') return money(props.value)
  if (props.format === 'raw') return props.value
  return num(props.value)
}
</script>

<template>
  <component
    :is="to ? 'RouterLink' : 'div'" :to="to"
    class="card p-4 flex items-start justify-between gap-3"
    :class="to ? 'hover:border-clay-300 transition-colors' : ''"
  >
    <div class="min-w-0">
      <p class="text-xs text-sand-600 mb-1">{{ label }}</p>
      <p class="text-2xl font-semibold num text-sand-900">{{ display() }}</p>

      <p v-if="change !== null" class="text-xs mt-1 flex items-center gap-1"
         :class="change >= 0 ? 'text-olive-700' : 'text-rose-700'">
        <BaseIcon name="trend" :size="13" :style="change < 0 ? 'transform:scaleY(-1)' : ''" />
        <span class="num">{{ change > 0 ? '+' : '' }}{{ change }}%</span>
        <span class="text-sand-500">عن الشهر الماضي</span>
      </p>

      <p v-else-if="hint" class="text-xs text-sand-500 mt-1">{{ hint }}</p>
    </div>

    <span class="shrink-0 size-10 grid place-items-center rounded-xl" :class="tones[tone]">
      <BaseIcon :name="icon" :size="19" />
    </span>
  </component>
</template>

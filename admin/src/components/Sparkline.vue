<script setup>
/**
 * خط اتجاه صغير بجانب المؤشر: شكل الأشهر الأخيرة، لا أرقامها.
 *
 * SVG مباشر لا Chart.js: اثنا عشر رقماً لا تستحق canvas ومستمعات أحداث.
 * والزمن يجري من اليمين إلى اليسار كبقية مخططات اللوحة، فآخر شهر على
 * اليسار وعليه نقطة. والشهر الجاري لم يكتمل، فقطعته الأخيرة متقطّعة كي
 * لا يُقرأ هبوطُها انهياراً.
 */
import { computed } from 'vue'

const props = defineProps({
  values: { type: Array, default: () => [] },
  /** الشهر الأخير جارٍ لم يكتمل */
  partialLast: { type: Boolean, default: false },
  label: { type: String, default: '' },
})

const W = 100
const H = 32
const PAD = 3

const points = computed(() => {
  const v = props.values.map(Number)
  if (v.length < 2) return []

  const min = Math.min(...v)
  const max = Math.max(...v)
  const span = max - min || 1

  return v.map((n, i) => ({
    x: W - PAD - (i * (W - PAD * 2)) / (v.length - 1),
    y: H - PAD - ((n - min) / span) * (H - PAD * 2),
  }))
})

const line = (pts) => pts.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')

const solid = computed(() => line(props.partialLast ? points.value.slice(0, -1) : points.value))
const dashed = computed(() => (props.partialLast ? line(points.value.slice(-2)) : ''))
const area = computed(() => {
  const pts = points.value
  if (!pts.length) return ''
  return `${line(pts)} ${pts.at(-1).x},${H} ${pts[0].x},${H}`
})
const last = computed(() => points.value.at(-1))
</script>

<template>
  <svg
    v-if="points.length" :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none"
    class="spark block w-full h-8 overflow-visible" role="img" :aria-label="label"
  >
    <polygon :points="area" class="spark__area" />
    <polyline :points="solid" class="spark__line" vector-effect="non-scaling-stroke" />
    <polyline v-if="dashed" :points="dashed" class="spark__line spark__line--partial" vector-effect="non-scaling-stroke" />
    <circle :cx="last.x" :cy="last.y" r="2.4" class="spark__dot" vector-effect="non-scaling-stroke" />
  </svg>
</template>

<style scoped>
.spark__area { fill: var(--color-clay-50); }
.spark__line {
  fill: none;
  stroke: var(--color-clay-600);
  stroke-width: 1.75;
  stroke-linejoin: round;
  stroke-linecap: round;
}
.spark__line--partial { stroke-dasharray: 3 3; }
.spark__dot { fill: var(--color-clay-700); }
</style>

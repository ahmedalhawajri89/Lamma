<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import Chart from 'chart.js/auto'

/**
 * غلاف Chart.js.
 *
 * ثلاثة أشياء لازمة ولا يفعلها Chart.js وحده:
 *  1. `destroy()` عند التفكيك — بلا ذلك يتسرّب canvas وتتراكم مستمعات
 *     الأحداث عند كل تنقّل بين الشاشات.
 *  2. `rtl: true` و `reverse` على المحور — الرسم في صفحة عربية يبدأ يميناً.
 *  3. احترام prefers-reduced-motion — كما في بقية المشروع.
 */
const props = defineProps({
  type: { type: String, default: 'line' },
  data: { type: Object, required: true },
  options: { type: Object, default: () => ({}) },
  height: { type: Number, default: 260 },
})

const canvas = ref(null)
let chart = null

const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

const base = {
  responsive: true,
  maintainAspectRatio: false,
  animation: reduced ? false : { duration: 500 },
  locale: 'ar',
  plugins: {
    legend: {
      rtl: true,
      textDirection: 'rtl',
      labels: { usePointStyle: true, boxWidth: 8, font: { family: 'IBM Plex Sans Arabic', size: 12 } },
    },
    tooltip: {
      rtl: true,
      textDirection: 'rtl',
      backgroundColor: '#1c1917',
      titleFont: { family: 'IBM Plex Sans Arabic' },
      bodyFont: { family: 'IBM Plex Sans Arabic' },
      padding: 10,
      cornerRadius: 8,
    },
  },
  scales: {
    x: { reverse: true, grid: { display: false }, ticks: { font: { family: 'IBM Plex Sans Arabic', size: 11 } } },
    y: {
      position: 'right',
      grid: { color: '#e9dfd0' },
      border: { display: false },
      ticks: { font: { family: 'IBM Plex Sans Arabic', size: 11 }, padding: 8 },
    },
  },
}

function merge(a, b) {
  const out = { ...a }
  for (const [k, v] of Object.entries(b)) {
    out[k] = v && typeof v === 'object' && !Array.isArray(v) ? merge(a[k] ?? {}, v) : v
  }
  return out
}

function render() {
  chart?.destroy()
  if (!canvas.value) return

  const opts = merge(base, props.options)
  // الرسوم الدائرية بلا محاور — تركها يطبع تحذيراً في الطرفية
  if (['doughnut', 'pie', 'polarArea'].includes(props.type)) delete opts.scales

  chart = new Chart(canvas.value, { type: props.type, data: props.data, options: opts })
}

onMounted(render)
onBeforeUnmount(() => chart?.destroy())
watch(() => props.data, render, { deep: true })
</script>

<template>
  <div :style="{ height: `${height}px` }">
    <canvas ref="canvas" />
  </div>
</template>

<script setup>
/**
 * تبويبات الحالة فوق الجدول، بعدد كل حالة.
 *
 * كانت الحالة قائمة منسدلة تُخفي الخيارات وأعدادها: لا يعرف المستخدم أن
 * سبعة ملفات تنتظر التوثيق حتى يفتح القائمة ويختار ويرى. التبويب يقولها
 * قبل الضغط، ويقرأ أعداده من الخادم تحت الفلاتر الأخرى نفسها.
 *
 * الحالات النادرة (`rare: true`) تظهر فقط حين يكون لها عدد، أو حين تكون
 * المختارة، فلا يزدحم الشريط بتبويبات فارغة.
 */
const props = defineProps({
  options: { type: Array, required: true },       // [{ value, label, rare? }]
  counts: { type: Object, default: () => ({}) },  // { value: n }
  total: { type: Number, default: null },         // عدد «الكل»
  allLabel: { type: String, default: 'الكل' },
  label: { type: String, default: 'الحالة' },
})

const model = defineModel({ type: String, default: '' })

const visible = () => props.options.filter((o) => !o.rare || (props.counts[o.value] ?? 0) > 0 || model.value === o.value)
const fmt = (n) => (n === null || n === undefined ? '' : new Intl.NumberFormat('en-US').format(n))
</script>

<template>
  <nav class="relative mb-4 border-b border-sand-200" :aria-label="label">
    <ul class="flex gap-1 overflow-x-auto -mb-px [scrollbar-width:none]">
      <li v-for="o in [{ value: '', label: allLabel }, ...visible()]" :key="o.value || 'all'">
        <button
          type="button"
          class="flex items-center gap-2 px-3 py-2.5 text-sm whitespace-nowrap border-b-2 transition-colors"
          :class="model === o.value
            ? 'border-clay-600 text-clay-700 font-semibold'
            : 'border-transparent text-sand-600 hover:text-sand-900'"
          :aria-pressed="model === o.value"
          @click="model = o.value"
        >
          {{ o.label }}
          <span
            v-if="(o.value === '' ? total : counts[o.value]) !== null && (o.value === '' ? total : counts[o.value]) !== undefined"
            class="num rounded-full px-1.5 text-xs"
            :class="model === o.value ? 'bg-clay-50 text-clay-700' : 'bg-sand-100 text-sand-600'"
          >{{ fmt(o.value === '' ? total : (counts[o.value] ?? 0)) }}</span>
        </button>
      </li>
    </ul>
  </nav>
</template>

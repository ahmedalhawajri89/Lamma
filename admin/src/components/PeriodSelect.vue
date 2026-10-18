<script setup>
/**
 * اختيار الفترة: اختيارات جاهزة، والمخصّصة لمن يحتاجها.
 *
 * حقلا تاريخ فارغان («من/إلى») لا يقولان شيئاً، ويحتاج المستخدم إلى أن
 * يحسب أول الشهر بنفسه. الاختيارات الجاهزة تكتب في `from` و`to` نفسيهما،
 * فيبقى الرابط والخادم كما هما.
 */
import { computed, ref } from 'vue'

const from = defineModel('from', { type: String, default: '' })
const to = defineModel('to', { type: String, default: '' })

defineProps({
  presets: { type: Array, default: () => ['month', 'last_month', 'days30', 'quarter', 'year'] },
  label: { type: String, default: 'الفترة' },
})

const iso = (d) => {
  const x = new Date(d)
  x.setMinutes(x.getMinutes() - x.getTimezoneOffset())
  return x.toISOString().slice(0, 10)
}

const RANGES = {
  week: { label: 'آخر 7 أيام', range: () => { const d = new Date(); d.setDate(d.getDate() - 6); return [iso(d), ''] } },
  days30: { label: 'آخر 30 يوماً', range: () => { const d = new Date(); d.setDate(d.getDate() - 29); return [iso(d), ''] } },
  month: { label: 'هذا الشهر', range: () => { const n = new Date(); return [iso(new Date(n.getFullYear(), n.getMonth(), 1)), ''] } },
  last_month: {
    label: 'الشهر الماضي',
    range: () => { const n = new Date(); return [iso(new Date(n.getFullYear(), n.getMonth() - 1, 1)), iso(new Date(n.getFullYear(), n.getMonth(), 0))] },
  },
  quarter: { label: 'هذا الربع', range: () => { const n = new Date(); return [iso(new Date(n.getFullYear(), Math.floor(n.getMonth() / 3) * 3, 1)), ''] } },
  year: { label: 'هذا العام', range: () => [iso(new Date(new Date().getFullYear(), 0, 1)), ''] },
}

const custom = ref(false)

const value = computed({
  get() {
    if (custom.value) return 'custom'
    if (!from.value && !to.value) return ''
    const hit = Object.entries(RANGES).find(([, r]) => { const [f, t] = r.range(); return f === from.value && t === to.value })
    return hit ? hit[0] : 'custom'
  },
  set(v) {
    custom.value = v === 'custom'
    if (v === 'custom') return
    const [f, t] = v ? RANGES[v].range() : ['', '']
    from.value = f
    to.value = t
  },
})

/** «مسح الفلاتر» في الشاشة يمسح التاريخين؛ هذا يُغلق المخصّصة معه */
defineExpose({ reset: () => { custom.value = false } })
</script>

<template>
  <select v-model="value" class="input !w-auto text-sm" :aria-label="label">
    <option value="">كل الفترات</option>
    <option v-for="p in presets" :key="p" :value="p">{{ RANGES[p].label }}</option>
    <option value="custom">فترة مخصّصة…</option>
  </select>
  <template v-if="value === 'custom'">
    <label class="flex items-center gap-1.5 text-sm text-sand-600">
      من <input v-model="from" type="date" lang="en" class="input !w-auto text-sm" />
    </label>
    <label class="flex items-center gap-1.5 text-sm text-sand-600">
      إلى <input v-model="to" type="date" lang="en" class="input !w-auto text-sm" />
    </label>
  </template>
</template>

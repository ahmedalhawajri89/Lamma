<script setup>
import BaseIcon from './BaseIcon.vue'

/**
 * جدول القوائم.
 *
 * `overflow-x-auto` حول الجدول لا على الصفحة: الجدول العريض يمرّر أفقياً
 * داخل حاويته، فلا يزحف جسم الصفحة أفقياً على الجوال.
 */
defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  emptyText: { type: String, default: 'لا نتائج مطابقة.' },
  rowKey: { type: String, default: 'id' },
})

defineEmits(['row-click'])
</script>

<template>
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-sand-200 bg-sand-25">
            <th
              v-for="col in columns" :key="col.key" scope="col"
              class="px-4 py-3 text-start font-medium text-sand-600 text-xs whitespace-nowrap"
              :class="col.align === 'end' ? 'text-end' : ''"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>

        <tbody>
          <!--
            الشرط على غلافٍ لا على `<tr>` نفسه.
            `v-if` و`v-for` على العنصر الواحد يعملان في Vue 3 (‏`v-if` أسبق)
            لكنهما يُخفيان نيّة الكود، والأهمّ أن الصفوف الهيكلية بلا `@click`:
            فأي شيء يستهدف `tr:first` — اختباراً كان أو نقرة مستخدم سريعة —
            يصيب صفّاً لا يستجيب، ولا يقول أحد لماذا.
          -->
          <template v-if="loading">
            <tr v-for="n in 5" :key="`sk-${n}`" class="border-b border-sand-100">
              <td v-for="col in columns" :key="col.key" class="px-4 py-3.5">
                <div class="h-3 rounded bg-sand-100 animate-pulse" :style="{ width: `${40 + (n * 7) % 45}%` }" />
              </td>
            </tr>
          </template>

          <tr v-else-if="!rows.length">
            <td :colspan="columns.length" class="px-4 py-14 text-center">
              <BaseIcon name="search" :size="28" class="mx-auto text-sand-300 mb-2" />
              <p class="text-sand-500">{{ emptyText }}</p>
            </td>
          </tr>

          <tr
            v-else v-for="row in rows" :key="row[rowKey]"
            class="border-b border-sand-100 last:border-0 hover:bg-sand-25 transition-colors"
            @click="$emit('row-click', row)"
          >
            <td
              v-for="col in columns" :key="col.key"
              class="px-4 py-3 align-middle"
              :class="col.align === 'end' ? 'text-end' : ''"
            >
              <slot :name="col.key" :row="row" :value="row[col.key]">
                {{ row[col.key] ?? '—' }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

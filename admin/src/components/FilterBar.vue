<script setup>
import BaseIcon from './BaseIcon.vue'

defineProps({
  searchPlaceholder: { type: String, default: 'ابحث…' },
  hasSearch: { type: Boolean, default: true },
})

const search = defineModel('search', { type: String, default: '' })
defineEmits(['reset'])
</script>

<template>
  <div class="flex items-center gap-2 flex-wrap mb-4">
    <label v-if="hasSearch" class="relative flex-1 min-w-52 max-w-80">
      <span class="sr-only">{{ searchPlaceholder }}</span>
      <BaseIcon
        name="search" :size="16"
        class="absolute top-1/2 -translate-y-1/2 text-sand-400 pointer-events-none"
        style="inset-inline-start: 0.75rem"
      />
      <input v-model="search" type="search" class="input ps-9" :placeholder="searchPlaceholder" />
    </label>

    <slot />

    <button class="btn btn-ghost text-xs" type="button" @click="$emit('reset')">
      إعادة ضبط
    </button>
  </div>
</template>

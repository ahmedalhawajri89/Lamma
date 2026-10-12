<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <header class="flex items-start justify-between gap-4 flex-wrap mb-5">
    <div>
      <h1 class="text-2xl text-sand-900">{{ title }}</h1>
      <p v-if="subtitle" class="text-sm text-sand-600 mt-0.5">{{ subtitle }}</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap"><slot name="actions" /></div>
  </header>
</template>

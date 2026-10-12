<script setup>
/**
 * نافذة التأكيد المشتركة. تُركَّب مرة واحدة في `App.vue`، وتُفتح من أي شاشة
 * بـ`confirmDialog()` في `composables/useConfirm.js`.
 *
 * مبنية على `FormModal` فترث منه حبس التركيز وإعادته، وقفل التمرير، وEsc.
 */
import { ref, watch, computed } from 'vue'
import FormModal from './FormModal.vue'
import { confirmState, settleConfirm } from '@/composables/useConfirm'

const text = ref('')
const error = ref('')

watch(
  () => confirmState.open,
  (open) => {
    if (open) {
      text.value = ''
      error.value = ''
    }
  },
)

const hasReason = computed(() => confirmState.reason !== null)

function cancel() {
  settleConfirm(null)
}

function submit() {
  const reason = text.value.trim()

  if (confirmState.reason === 'required' && !reason) {
    error.value = `${confirmState.reasonLabel} مطلوب.`
    return
  }

  settleConfirm({ confirmed: true, reason: reason || null })
}
</script>

<template>
  <FormModal
    :open="confirmState.open"
    :title="confirmState.title"
    :dirty="false"
    @close="cancel"
    @submit="submit"
  >
    <p v-if="confirmState.message" class="text-sm text-sand-700 leading-relaxed">
      {{ confirmState.message }}
    </p>

    <div v-if="hasReason">
      <label class="block text-sm font-medium mb-1.5" for="confirm-reason">
        {{ confirmState.reasonLabel }}
        <span v-if="confirmState.reason === 'optional'" class="text-sand-500 font-normal">(اختياري)</span>
      </label>
      <textarea
        id="confirm-reason" v-model="text" rows="3" class="input" data-autofocus
        :aria-invalid="!!error" aria-describedby="confirm-reason-error"
      />
      <p v-if="error" id="confirm-reason-error" class="text-xs text-rose-700 mt-1" role="alert">{{ error }}</p>
    </div>

    <template #actions>
      <button class="btn btn-outline" type="button" @click="cancel">تراجع</button>
      <button
        class="btn" type="submit"
        :class="confirmState.danger ? 'bg-rose-700 text-white hover:bg-rose-600' : 'btn-primary'"
      >
        {{ confirmState.confirmLabel }}
      </button>
    </template>
  </FormModal>
</template>

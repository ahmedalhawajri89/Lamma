<script setup>
/**
 * القرار على مستند: تحقّق، أو رفض بسبب.
 *
 * يُستعمل في لوحة المراجعة وفي العرض بملء الشاشة، فالقرار واحد أينما اتُّخذ.
 * «تحقّقت منه» لا يعمل قبل فتح المستند: لا يُعتمد ما لم يُنظر إليه. والرفض
 * متاح دائماً (اسم ملف خاطئ أو نوع غير صحيح يُرى بلا فتح).
 */
import { nextTick, ref, watch } from 'vue'
import BaseIcon from './BaseIcon.vue'

const props = defineProps({
  busy: { type: Boolean, default: false },
  own: { type: Boolean, default: false },
  viewed: { type: Boolean, default: false },
  /** على خلفية داكنة (العرض بملء الشاشة) */
  dark: { type: Boolean, default: false },
  /** عمودي (اللوحة الجانبية للعرض) أو أفقي (شريط اللوحة) */
  stacked: { type: Boolean, default: false },
})

const rejecting = defineModel('rejecting', { type: Boolean, default: false })
const emit = defineEmits(['verify', 'reject', 'view'])

const REASONS = ['الصورة غير واضحة', 'المستند لا يخص هذا الطفل', 'البيانات لا تطابق ملف الطفل', 'المستند ناقص أو مقصوص', 'ليس النوع المختار']

const reason = ref('')
const input = ref(null)
const error = ref('')

watch(rejecting, async (on) => {
  reason.value = ''
  error.value = ''
  if (on) {
    await nextTick()
    input.value?.focus()
  }
})

function submit() {
  if (reason.value.trim().length < 5) {
    error.value = 'اكتب سبب الرفض، يقرؤه من رفع المستند.'
    input.value?.focus()
    return
  }
  emit('reject', reason.value.trim())
}
</script>

<template>
  <p v-if="own" class="text-sm py-1.5" :class="dark ? 'text-white/70' : 'text-sand-500'">
    رفعت هذا المستند بنفسك، فيتحقق منه غيرك.
  </p>

  <!-- الرفض في مكانه: أسباب جاهزة بنقرة، أو سبب يُكتب -->
  <form v-else-if="rejecting" class="space-y-2.5" @submit.prevent="submit">
    <div class="flex flex-wrap gap-1.5">
      <button
        v-for="r in REASONS" :key="r" type="button" class="chip !py-1 !text-xs"
        :aria-pressed="reason === r" @click="reason = r; error = ''"
      >{{ r }}</button>
    </div>
    <div class="flex flex-wrap items-center gap-2" :class="stacked ? 'flex-col items-stretch' : ''">
      <label class="sr-only" :for="`rejectReason${dark ? 'Dark' : ''}`">سبب الرفض</label>
      <input
        :id="`rejectReason${dark ? 'Dark' : ''}`" ref="input" v-model="reason" class="input flex-1 min-w-48"
        maxlength="255" placeholder="سبب الرفض، يقرؤه من رفع المستند…" :aria-invalid="Boolean(error)"
        @input="error = ''"
      />
      <button class="btn bg-rose-600 text-white hover:bg-rose-700 justify-center" type="submit" :disabled="busy">ارفض المستند</button>
      <button class="btn btn-ghost justify-center" :class="dark ? '!text-white/80 hover:!bg-white/10' : ''" type="button" :disabled="busy" @click="rejecting = false">
        إلغاء <kbd class="kbd">Esc</kbd>
      </button>
    </div>
    <p v-if="error" class="text-xs" :class="dark ? 'text-rose-300' : 'text-rose-700'" role="alert">{{ error }}</p>
  </form>

  <div v-else class="flex flex-wrap items-center gap-2" :class="stacked ? 'flex-col items-stretch' : ''">
    <button
      class="btn btn-primary justify-center" type="button" :disabled="busy || !viewed"
      :title="viewed ? '' : 'افتح المستند أولاً'" @click="emit('verify')"
    >
      <BaseIcon :name="busy ? 'loader' : 'check'" :size="15" :class="{ 'animate-spin': busy }" />
      تحقّقت منه <kbd class="kbd !bg-white/15 !border-white/25 !text-white">V</kbd>
    </button>
    <button
      class="btn justify-center" :class="dark ? 'border border-white/25 text-rose-200 hover:bg-white/10' : 'btn-outline text-rose-700'"
      type="button" :disabled="busy" @click="rejecting = true"
    >
      رفض <kbd class="kbd">R</kbd>
    </button>
    <p v-if="!viewed" class="text-xs" :class="dark ? 'text-white/60' : 'text-sand-500'">
      التحقق بعد <button type="button" class="underline hover:no-underline" @click="emit('view')">فتح المستند</button>.
    </p>
  </div>
</template>

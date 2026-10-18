<script setup>
/**
 * «منذ آخر دخول»: ما جرى والموظف غائب، مما لا يدخل طابور مهامه.
 *
 * «بانتظارك» تقول ما عليه فعله؛ وهذه تقول ما حدث: دفعات فشلت، كفالات
 * أُنهيت، كفلاء جدد، استفسارات، تقارير أُرجعت إليه، عمليات حساسة لغيره.
 * كلٌّ بطاقةٌ صغيرة برقمها ورابطها، وأمثلة منها حين يكفي سطر.
 *
 * و«تم، اطّلعت» تخفيها حتى الدخول التالي: لا تبقى خبراً قديماً فوق اللوحة
 * طوال اليوم.
 */
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { dateTime, num, relative } from '@/utils/format'
import BaseIcon from './BaseIcon.vue'

const data = ref(null)
const hidden = ref(false)
const KEY = 'kafala.since.dismissed'

onMounted(async () => {
  try {
    data.value = (await admin.since()).data.data
    try { hidden.value = localStorage.getItem(KEY) === data.value.since } catch { /* التخزين ممنوع: تظهر */ }
  } catch {
    // بطاقة إضافية: إن فشلت تغيب، واللوحة تعمل
  }
})

function dismiss() {
  hidden.value = true
  try { localStorage.setItem(KEY, data.value.since) } catch { /* تُخفى لهذه الزيارة وحدها */ }
}

const items = computed(() => data.value?.items ?? [])

const TONE = {
  bad: { dot: 'bg-rose-500', num: 'text-rose-700', icon: 'alert' },
  warn: { dot: 'bg-[#E0A63B]', num: 'text-[#a8741a]', icon: 'clock' },
  good: { dot: 'bg-[#1e8a7a]', num: 'text-[#0f5c5c]', icon: 'trend' },
  neutral: { dot: 'bg-sand-400', num: 'text-sand-800', icon: 'mail' },
}
const tone = (t) => TONE[t] ?? TONE.neutral
</script>

<template>
  <section v-if="data && !hidden" class="rounded-2xl border border-sand-200 bg-sand-0 mb-6 overflow-hidden" aria-labelledby="sinceTitle">
    <header class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 border-b border-sand-100 bg-sand-25">
      <span class="size-2 rounded-full bg-[#E0A63B]" aria-hidden="true" />
      <h2 id="sinceTitle" class="font-semibold text-sm">
        {{ data.first_visit ? 'خلال الأسبوع الماضي' : 'منذ آخر دخول لك' }}
      </h2>
      <span v-if="!data.first_visit" class="text-xs text-sand-500" :title="dateTime(data.since)">{{ relative(data.since) }}</span>
      <button type="button" class="ms-auto text-xs text-sand-600 hover:text-sand-900 inline-flex items-center gap-1" @click="dismiss">
        <BaseIcon name="check" :size="13" /> تم، اطّلعت
      </button>
    </header>

    <ul v-if="items.length" class="grid sm:grid-cols-2 xl:grid-cols-3">
      <!-- حدودٌ بـ outline تتراكب خطاً واحداً، فلا تظهر الخانة الفارغة رمادية -->
      <li v-for="i in items" :key="i.key" class="outline outline-1 outline-sand-100">
        <RouterLink :to="{ path: i.to.path, query: i.to.query }" class="group flex gap-3 h-full px-5 py-4 hover:bg-sand-25 transition-colors">
          <span class="num text-2xl font-bold leading-none pt-0.5 min-w-8" :class="tone(i.tone).num">{{ num(i.count) }}</span>
          <span class="flex-1 min-w-0">
            <span class="flex items-center gap-1.5 text-sm font-medium text-sand-900">
              <span class="size-1.5 rounded-full shrink-0" :class="tone(i.tone).dot" aria-hidden="true" />
              {{ i.title }}
            </span>
            <span v-if="i.hint" class="block text-xs text-sand-500 mt-0.5">{{ i.hint }}</span>
            <span v-for="(s, n) in i.samples" :key="n" class="block text-xs text-sand-600 mt-1 truncate" dir="auto">{{ s }}</span>
          </span>
          <BaseIcon name="chevron" :size="15" class="shrink-0 self-center text-sand-300 group-hover:text-clay-600" style="transform: scaleX(-1)" />
        </RouterLink>
      </li>
    </ul>

    <p v-else class="px-5 py-4 text-sm text-sand-600 flex items-center gap-2">
      <BaseIcon name="check" :size="16" class="text-olive-700" /> لم يجرِ شيء يستحق انتباهك منذ آخر دخول.
    </p>
  </section>
</template>

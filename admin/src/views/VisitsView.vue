<script setup>
/**
 * الزيارات الميدانية: عرضان لشاشة واحدة.
 *
 * «الخطة» تجيب سؤال المشرف كل صباح: من أزور الآن؟ كل طفل مفتوح الملف بموعد
 * زيارته، ومن فات موعده أو لم يُزر قط أولاً. و«السجل» يجيب: ماذا رأينا؟
 * الزيارات السابقة بملاحظاتها، والاحتياجات المرصودة مجمّعة.
 */
import { computed, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BaseIcon from '@/components/BaseIcon.vue'
import VisitFormModal from '@/components/VisitFormModal.vue'
import VisitsPlan from '@/components/visits/VisitsPlan.vue'
import VisitsLog from '@/components/visits/VisitsLog.vue'

const route = useRoute()
const auth = useAuthStore()

const isLog = computed(() => route.params.view === 'log')

const formOpen = ref(false)
const preset = ref(null)
const plan = ref(null)
const log = ref(null)

/** من الخطة: الطفل مختار سلفاً؛ ومن الرأس: نموذج فارغ */
function record(orphan = null) {
  preset.value = orphan ? { id: orphan.id, label: `${orphan.name} · ${orphan.reference_code}` } : null
  formOpen.value = true
}

function onSaved() {
  formOpen.value = false
  plan.value?.load()
  log.value?.load()
}
</script>

<template>
  <div>
    <header class="flex flex-wrap items-end justify-between gap-3 mb-5">
      <div>
        <h1 class="text-2xl font-bold text-sand-900">الزيارات الميدانية</h1>
        <p class="text-sm text-sand-500 mt-0.5">
          {{ isLog ? 'ما رُصد في الزيارات السابقة.' : 'من يحتاج زيارة، ومتى.' }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <!-- العرضان: رابطان حقيقيان، فيُفتح كلٌّ في تبويب ويُرسل رابطه -->
        <nav class="inline-flex rounded-xl bg-sand-100 p-1 text-sm" aria-label="عرض الزيارات">
          <RouterLink
            :to="{ name: 'visits' }" class="px-3.5 py-1.5 rounded-lg transition-colors"
            :class="!isLog ? 'bg-sand-0 shadow-sm text-sand-900 font-medium' : 'text-sand-600 hover:text-sand-900'"
            :aria-current="!isLog ? 'page' : undefined"
          >الخطة</RouterLink>
          <RouterLink
            :to="{ name: 'visits', params: { view: 'log' } }" class="px-3.5 py-1.5 rounded-lg transition-colors"
            :class="isLog ? 'bg-sand-0 shadow-sm text-sand-900 font-medium' : 'text-sand-600 hover:text-sand-900'"
            :aria-current="isLog ? 'page' : undefined"
          >السجل</RouterLink>
        </nav>

        <button v-if="auth.can('visits.record')" class="btn btn-primary" type="button" @click="record()">
          <BaseIcon name="plus" :size="16" />
          تسجيل زيارة
        </button>
      </div>
    </header>

    <VisitsLog v-if="isLog" ref="log" :key="'log'" />
    <VisitsPlan v-else ref="plan" :key="'plan'" @record="record" />

    <VisitFormModal
      :open="formOpen" :preset-orphan="preset"
      @close="formOpen = false" @saved="onSaved"
    />
  </div>
</template>

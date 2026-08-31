<script setup>
/**
 * مستندات التحقق: عرض ورفع وتحقّق وحذف.
 *
 * هذه اللوحة كانت الفجوة التي **تمنع توثيق أي ملف جديد**: التوثيق يشترط
 * شهادة ميلاد وشهادة وفاة وإثبات وصاية، كلها مُتحقَّقاً منها — ولم تكن في
 * الواجهة طريقة لرفع مستند ولا للتحقق منه. فكان زرّ «وثّق الملف» يردّ
 * بخطأ لا يملك المستخدم أن يعالجه من أي شاشة.
 *
 * ومن يرفع ليس من يتحقق — أو لا ينبغي أن يكون. الخادم يفرض صلاحية
 * `documents.manage` على الفعلين، والفصل بينهما سياسة تشغيلية تُضبط في
 * الأدوار لا هنا.
 */
import { computed, onMounted, ref } from 'vue'
import { admin } from '@/api/endpoints'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { dateTime } from '@/utils/format'
import BaseIcon from './BaseIcon.vue'
import FormModal from './FormModal.vue'
import FormField from './FormField.vue'

const props = defineProps({
  orphanId: { type: [String, Number], required: true },
  verified: { type: Boolean, default: false },

  /** أنواع المستندات من `/admin/references` — `[{ value, label }]`. */
  types: { type: Array, default: () => [] },
})

const emit = defineEmits(['changed'])

const auth = useAuthStore()
const ui = useUiStore()

const documents = ref([])
const loading = ref(true)
const busyId = ref(null)

const uploadOpen = ref(false)
const uploadType = ref('')
const uploadFile = ref(null)
const uploadErrors = ref({})
const uploading = ref(false)
const fileInput = ref(null)

const can = computed(() => auth.can('documents.manage'))

/**
 * الأنواع الثلاثة التي يشترطها التوثيق.
 *
 * عرضها كقائمة نواقص أنفع من رسالة نصّية: المستخدم يرى أيّها ينقصه بدل
 * أن يقرأ شرطاً ثم يعدّ بنفسه.
 */
const REQUIRED = ['birth_certificate', 'death_certificate', 'guardianship_proof']

const checklist = computed(() =>
  REQUIRED.map((type) => {
    const found = documents.value.find((d) => d.type === type)
    return {
      type,
      label: props.types.find((t) => t.value === type)?.label ?? type,
      state: !found ? 'missing' : found.is_verified ? 'done' : 'pending',
    }
  }),
)

const ready = computed(() => checklist.value.every((c) => c.state === 'done'))

async function load() {
  loading.value = true
  try {
    const { data } = await admin.orphans.documents(props.orphanId)
    documents.value = data.data ?? []
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر جلب المستندات.')
  } finally {
    loading.value = false
  }
}

function onFile(event) {
  uploadFile.value = event.target.files?.[0] ?? null
  delete uploadErrors.value.file
}

function openUpload() {
  uploadType.value = ''
  uploadFile.value = null
  uploadErrors.value = {}
  if (fileInput.value) fileInput.value.value = ''
  uploadOpen.value = true
}

async function upload() {
  // تحقق محلي قبل الرحلة: رسالة فورية خير من دورة كاملة إلى الخادم
  const local = {}
  if (!uploadType.value) local.type = ['اختر نوع المستند.']
  if (!uploadFile.value) local.file = ['اختر ملفاً.']

  if (Object.keys(local).length) {
    uploadErrors.value = local
    return
  }

  uploading.value = true
  uploadErrors.value = {}

  try {
    const { data } = await admin.documents.upload(props.orphanId, {
      type: uploadType.value,
      file: uploadFile.value,
    })

    ui.success(data.message)
    uploadOpen.value = false
    await load()
    emit('changed')
  } catch (e) {
    uploadErrors.value = e.fieldErrors ?? {}
    if (!Object.keys(uploadErrors.value).length) ui.error(e.userMessage ?? 'تعذّر رفع المستند.')
  } finally {
    uploading.value = false
  }
}

async function verify(document) {
  busyId.value = document.id
  try {
    const { data } = await admin.documents.verify(document.id)
    ui.success(data.message)
    await load()
    emit('changed')
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر التحقق من المستند.')
  } finally {
    busyId.value = null
  }
}

async function remove(document) {
  if (!window.confirm(`حذف «${document.type_label}»؟ لا يمكن التراجع.`)) return

  busyId.value = document.id
  try {
    const { data } = await admin.documents.remove(document.id)
    ui.success(data.message)
    await load()
    emit('changed')
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر حذف المستند.')
  } finally {
    busyId.value = null
  }
}

/** ‏١٢٤٠٠٠ → «١٢١ ك.ب» — البايتات الخام لا تقول شيئاً لأحد. */
function size(bytes) {
  if (!bytes) return ''
  return bytes < 1024 * 1024
    ? `${Math.round(bytes / 1024)} ك.ب`
    : `${(bytes / 1024 / 1024).toFixed(1)} م.ب`
}

onMounted(load)
defineExpose({ load })
</script>

<template>
  <div class="card p-4">
    <div class="flex items-center gap-2 mb-3">
      <h2 class="text-sm font-semibold flex-1">مستندات التحقق</h2>

      <button
        v-if="can" class="btn btn-outline text-xs py-1 px-2" type="button" @click="openUpload"
      >
        <BaseIcon name="upload" :size="14" />
        رفع
      </button>
    </div>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 3" :key="i" class="h-8 bg-sand-100 rounded animate-pulse" />
    </div>

    <template v-else>
      <ul v-if="documents.length" class="space-y-1 -mx-1">
        <li
          v-for="d in documents" :key="d.id"
          class="flex items-center gap-2 text-sm px-1 py-1.5 rounded-lg hover:bg-sand-50"
        >
          <BaseIcon
            :name="d.is_verified ? 'check' : 'clock'" :size="15"
            :class="d.is_verified ? 'text-olive-600' : 'text-amber-600'"
          />

          <span class="flex-1 min-w-0">
            <span class="block truncate">{{ d.type_label }}</span>
            <span class="block text-xs text-sand-500 truncate">
              {{ d.original_name }} · {{ size(d.size_bytes) }}
              <template v-if="d.is_verified"> · تُحقِّق {{ dateTime(d.verified_at) }}</template>
            </span>
          </span>

          <span class="flex items-center gap-0.5 shrink-0">
            <a
              v-if="can" class="btn btn-ghost p-1.5" :href="d.download_url"
              :title="`تنزيل ${d.original_name}`"
            >
              <BaseIcon name="download" :size="14" />
            </a>

            <button
              v-if="can && !d.is_verified" class="btn btn-ghost p-1.5 text-olive-700"
              type="button" :disabled="busyId === d.id" title="تحقّق من هذا المستند"
              @click="verify(d)"
            >
              <BaseIcon name="check" :size="14" />
            </button>

            <!--
              المُتحقَّق منه لا يُحذف: توثيق الملف قائم عليه، والخادم يرفض ذلك
              بـ422. إخفاء الزرّ يمنع نقرةً مصيرها الرفض.
            -->
            <button
              v-if="can && !d.is_verified" class="btn btn-ghost p-1.5 text-clay-700"
              type="button" :disabled="busyId === d.id" title="حذف"
              @click="remove(d)"
            >
              <BaseIcon name="trash" :size="14" />
            </button>
          </span>
        </li>
      </ul>

      <p v-else class="text-sm text-sand-500 py-2">لا مستندات مرفوعة.</p>

      <!-- قائمة النواقص: تظهر ما دام الملف غير موثّق -->
      <div v-if="!verified" class="mt-3 pt-3 border-t border-sand-100">
        <p class="text-xs text-sand-600 mb-2">
          {{ ready ? 'المستندات مكتملة — يمكن توثيق الملف.' : 'التوثيق يحتاج:' }}
        </p>

        <ul v-if="!ready" class="space-y-1">
          <li
            v-for="c in checklist" :key="c.type"
            class="flex items-center gap-1.5 text-xs"
            :class="c.state === 'done' ? 'text-olive-700' : 'text-sand-600'"
          >
            <BaseIcon
              :name="c.state === 'done' ? 'check' : c.state === 'pending' ? 'clock' : 'close'"
              :size="12"
              :class="{ 'text-amber-600': c.state === 'pending', 'text-sand-400': c.state === 'missing' }"
            />
            {{ c.label }}
            <span v-if="c.state === 'pending'" class="text-amber-700">(مرفوع، ينتظر التحقق)</span>
          </li>
        </ul>
      </div>
    </template>

    <FormModal
      :open="uploadOpen" title="رفع مستند" :busy="uploading"
      subtitle="لا يُحتسب المستند في التوثيق قبل التحقق منه."
      @close="uploadOpen = false" @submit="upload"
    >
      <FormField
        v-model="uploadType" label="نوع المستند" required autofocus
        :options="types" :error="uploadErrors.type" placeholder="اختر النوع"
      />

      <div>
        <label class="block text-sm font-medium mb-1.5" for="documentFile">
          الملف <span class="text-clay-600" aria-hidden="true">*</span>
        </label>
        <input
          id="documentFile" ref="fileInput" class="input" type="file"
          accept="application/pdf,image/jpeg,image/png,image/webp"
          :aria-invalid="uploadErrors.file ? 'true' : undefined"
          aria-describedby="documentFileHelp" @change="onFile"
        >
        <p id="documentFileHelp" class="text-xs text-sand-500 mt-1">
          PDF أو صورة. يُخزَّن خارج المجلد العام ولا يُقدَّم إلا عبر مسار محمي.
        </p>
        <p v-if="uploadErrors.file" class="text-xs text-clay-700 mt-1" role="alert">
          {{ uploadErrors.file[0] }}
        </p>
      </div>

      <template #actions>
        <button class="btn btn-outline" type="button" :disabled="uploading" @click="uploadOpen = false">
          إلغاء
        </button>
        <button class="btn btn-primary" type="submit" :disabled="uploading">
          <BaseIcon v-if="uploading" name="loader" :size="15" class="animate-spin" />
          {{ uploading ? 'جارٍ الرفع…' : 'رفع' }}
        </button>
      </template>
    </FormModal>
  </div>
</template>

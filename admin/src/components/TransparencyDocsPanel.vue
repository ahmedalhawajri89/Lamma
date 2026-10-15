<script setup>
/**
 * وثائق صفحة الشفافية: الميزانية المدقّقة، والتقرير السنوي، وتقرير الزكاة،
 * والتراخيص.
 *
 * كانت أزرار «تحميل PDF» على الموقع تشير إلى `#`. هنا يُرفع الملف لنوعه
 * وسنته فيُنشر فوراً، ورفعٌ ثانٍ لنفس النوع والسنة يستبدله. والموقع يعرض
 * أحدث سنة من كل نوع، وما لم يُرفع يقول «يُنشر بعد اعتماد المدقق».
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { admin } from '@/api/endpoints'
import { confirmDialog } from '@/composables/useConfirm'
import { useUiStore } from '@/stores/ui'
import { num } from '@/utils/format'
import BaseIcon from './BaseIcon.vue'

/* global __SITE_URL__ */
const ui = useUiStore()
const siteUrl = `${__SITE_URL__}transparency.html#reports`

const docs = ref([])
const kinds = ref([])
const loading = ref(true)
const failed = ref(false)
const busy = ref(null)
const thisYear = new Date().getFullYear()
const yearOf = reactive({})

const ICONS = { audited_budget: 'receipt', annual_report: 'trend', zakat_report: 'wallet', licenses: 'shield' }
const dateFmt = new Intl.DateTimeFormat('ar-u-nu-latn', { day: 'numeric', month: 'long', year: 'numeric' })
const size = (b) => (b >= 1048576 ? `${(b / 1048576).toFixed(1)} م.ب` : `${Math.max(1, Math.round(b / 1024))} ك.ب`)

async function load() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await admin.transparencyDocs.list()
    docs.value = data.data ?? []
    kinds.value = data.meta?.kinds ?? []
    kinds.value.forEach((k) => { yearOf[k.value] ??= thisYear - 1 })
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}
onMounted(load)

/** لكل نوع: نسخه بالسنوات، الأحدث أولاً (هو ما يعرضه الموقع) */
const rows = computed(() => kinds.value.map((k) => ({
  ...k,
  versions: docs.value.filter((d) => d.kind === k.value).sort((a, b) => (b.year ?? 0) - (a.year ?? 0)),
})))

async function upload(kind, event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  if (file.type !== 'application/pdf') { ui.error('الوثيقة PDF فقط.'); return }

  busy.value = kind.value
  try {
    const { data } = await admin.transparencyDocs.upload({ kind: kind.value, year: kind.yearly ? yearOf[kind.value] : null }, file)
    ui.success(data.message ?? 'نُشرت الوثيقة.')
    await load()
  } catch (e) {
    ui.error(e.fieldErrors?.file?.[0] ?? e.fieldErrors?.year?.[0] ?? e.userMessage ?? 'تعذّر الرفع.')
  } finally {
    busy.value = null
  }
}

async function remove(doc) {
  const ok = await confirmDialog({
    title: 'حذف الوثيقة من الموقع',
    message: `«${doc.title}» تُحذف من صفحة الشفافية فوراً، ويُحذف ملفها.`,
    confirmLabel: 'احذفها',
    danger: true,
  })
  if (!ok) return
  busy.value = doc.kind
  try {
    const { data } = await admin.transparencyDocs.remove(doc.id)
    ui.success(data.message ?? 'حُذفت.')
    await load()
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر الحذف.')
  } finally {
    busy.value = null
  }
}

const years = Array.from({ length: 8 }, (_, i) => thisYear - i)
</script>

<template>
  <div>
    <p class="text-sm text-sand-600 mb-4">
      تُنشر على
      <a :href="siteUrl" target="_blank" rel="noopener" class="text-clay-700 hover:underline">صفحة الشفافية <BaseIcon name="external" :size="12" /></a>
      فور رفعها. يعرض الموقع أحدث سنة من كل نوع، وما لم يُرفع يظهر «يُنشر بعد اعتماد المدقق».
    </p>

    <div v-if="loading" class="space-y-3"><div v-for="n in 4" :key="n" class="h-24 rounded-2xl bg-sand-100 animate-pulse" /></div>

    <div v-else-if="failed" class="card p-8 text-center">
      <p class="text-sand-600 mb-3">تعذّر تحميل الوثائق.</p>
      <button class="btn btn-outline" type="button" @click="load">إعادة المحاولة</button>
    </div>

    <ul v-else class="space-y-3">
      <li v-for="k in rows" :key="k.value" class="card p-5">
        <div class="flex flex-wrap items-start gap-4">
          <span
            class="size-11 shrink-0 rounded-xl grid place-items-center"
            :class="k.versions.length ? 'bg-clay-50 text-clay-700' : 'bg-sand-100 text-sand-400'" aria-hidden="true"
          ><BaseIcon :name="ICONS[k.value] ?? 'file'" :size="20" /></span>

          <div class="flex-1 min-w-[14rem]">
            <h3 class="font-semibold">{{ k.label }}</h3>
            <p v-if="!k.versions.length" class="text-sm text-sand-500 mt-0.5">لم تُرفع بعد. الموقع يعرض «يُنشر بعد اعتماد المدقق».</p>
            <p v-else class="text-sm text-olive-700 mt-0.5">
              <BaseIcon name="check" :size="13" /> منشورة على الموقع: {{ k.versions[0].title }}
            </p>
          </div>

          <!-- الرفع: السنة (للأنواع السنوية) ثم الملف -->
          <div class="flex items-center gap-2">
            <label v-if="k.yearly" class="sr-only" :for="`y-${k.value}`">سنة الوثيقة</label>
            <select v-if="k.yearly" :id="`y-${k.value}`" v-model.number="yearOf[k.value]" class="input !w-auto text-sm num">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <label class="btn btn-primary !py-2 text-sm cursor-pointer" :class="{ 'opacity-60 pointer-events-none': busy === k.value }">
              <BaseIcon :name="busy === k.value ? 'loader' : 'upload'" :size="14" :class="{ 'animate-spin': busy === k.value }" />
              {{ k.versions.some((d) => d.year === (k.yearly ? yearOf[k.value] : null)) ? 'استبدال PDF' : 'رفع PDF' }}
              <input type="file" accept="application/pdf" class="sr-only" :data-kind="k.value" @change="upload(k, $event)" />
            </label>
          </div>
        </div>

        <!-- النسخ المرفوعة -->
        <ul v-if="k.versions.length" class="mt-4 border-t border-sand-100 divide-y divide-sand-100">
          <li v-for="(d, i) in k.versions" :key="d.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2.5 text-sm">
            <span class="font-medium">{{ d.title }}</span>
            <span v-if="i === 0" class="badge bg-olive-50 text-olive-700">على الموقع</span>
            <span class="text-xs text-sand-500">
              PDF · <span class="num">{{ size(d.size_bytes) }}</span> · {{ dateFmt.format(new Date(d.published_at)) }}<template v-if="d.uploaded_by"> · {{ d.uploaded_by }}</template>
            </span>
            <span class="ms-auto flex items-center gap-1">
              <a :href="d.url" target="_blank" rel="noopener" class="btn btn-ghost !py-1 !px-2 text-sm"><BaseIcon name="eye" :size="14" /> عرض</a>
              <button type="button" class="btn btn-ghost !py-1 !px-2 text-sm text-rose-700" :disabled="busy === k.value" @click="remove(d)">
                <BaseIcon name="trash" :size="14" /> حذف
              </button>
            </span>
          </li>
        </ul>
      </li>
    </ul>
    <p class="text-xs text-sand-400 mt-3">PDF فقط، حتى <span class="num">{{ num(20) }}</span> ميغابايت.</p>
  </div>
</template>

<script setup>
/**
 * محرّر المقال: صفحة كتابة لا نافذة.
 *
 * كان المقال يُكتب في مربّع نصّ من عشرة أسطر داخل نافذة منبثقة، بلا معرفة
 * بشكله على الموقع حتى يُنشر. هنا العنوان بخطٍّ كبير، والنصّ في مساحة
 * محرّر منسّق (عناوين، قوائم، روابط، صور)، ومعاينةٌ بالشكل الذي يعرض به
 * الموقع المقال، والإعدادات في عمود جانبي.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter, RouterLink } from 'vue-router'
import { admin } from '@/api/endpoints'
import { confirmDialog } from '@/composables/useConfirm'
import { useUiStore } from '@/stores/ui'
import { date, num } from '@/utils/format'
import BaseIcon from '@/components/BaseIcon.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import RichTextEditor from '@/components/RichTextEditor.vue'

/* global __SITE_URL__ */
const route = useRoute()
const router = useRouter()
const ui = useUiStore()

const isNew = computed(() => route.params.id === 'new')

const BLANK = { title: '', excerpt: '', body: '', category_id: '', status: 'draft', published_at: '', is_featured: false }
const form = ref({ ...BLANK })
const saved = ref(JSON.stringify(BLANK))
const post = ref(null)
const categories = ref([])
const statuses = ref([])
const loading = ref(true)
const saving = ref(false)
const errors = ref({})

const dirty = computed(() => JSON.stringify(form.value) !== saved.value || cover.value !== null)

async function load() {
  loading.value = true
  try {
    if (isNew.value) {
      const { data } = await admin.posts.list({ per_page: 1 })
      categories.value = data.meta?.categories ?? []
      statuses.value = data.meta?.statuses ?? []
      form.value = { ...BLANK }
    } else {
      const { data } = await admin.posts.show(route.params.id)
      post.value = data.data
      categories.value = data.meta?.categories ?? []
      statuses.value = data.meta?.statuses ?? []
      const p = data.data
      form.value = {
        title: p.title ?? '',
        excerpt: p.excerpt ?? '',
        body: p.body ?? '',
        category_id: p.category_id ?? '',
        status: p.status ?? 'draft',
        published_at: p.date ?? '',
        is_featured: Boolean(p.featured),
      }
    }
    saved.value = JSON.stringify(form.value)
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر فتح المقال.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => route.params.id, (id, prev) => { if (id !== prev) load() })

/* ── ما يُحسب من النصّ ───────────────────────────────────────────────── */

/** HTML المحرّر مُحلَّلاً: للعدّ لا للعرض */
const doc = computed(() => new DOMParser().parseFromString(form.value.body || '', 'text/html').body)
const words = computed(() => (doc.value.textContent ?? '').trim().split(/\s+/).filter(Boolean).length)
// قرابة مئتي كلمة في الدقيقة للقارئ العربي، ودقيقة على الأقل
const readMinutes = computed(() => Math.max(1, Math.round(words.value / 200)))

/** الفقرات: ما في المستوى الأول من <p> فيه نصّ (لا بنود القوائم ولا الاقتباس) */
const paragraphs = computed(() => [...doc.value.children].filter((el) => el.tagName === 'P' && el.textContent.trim()).length)

const tab = ref('write')

/* ── الغلاف ──────────────────────────────────────────────────────────── */

const cover = ref(null)
const coverPreview = ref(null)
function pickCover(e) {
  const file = e.target.files?.[0] ?? null
  if (coverPreview.value) URL.revokeObjectURL(coverPreview.value)
  cover.value = file
  coverPreview.value = file ? URL.createObjectURL(file) : null
}
onBeforeUnmount(() => { if (coverPreview.value) URL.revokeObjectURL(coverPreview.value) })
const coverShown = computed(() => coverPreview.value ?? post.value?.image ?? null)

/* ── الحفظ ───────────────────────────────────────────────────────────── */

async function save(status = null) {
  if (saving.value) return
  if (status) form.value.status = status
  if (!form.value.title.trim()) {
    errors.value = { title: ['العنوان مطلوب.'] }
    return
  }

  saving.value = true
  errors.value = {}
  const payload = {
    ...form.value,
    category_id: form.value.category_id || null,
    published_at: form.value.published_at || null,
    read_minutes: readMinutes.value,
  }

  try {
    const { data } = isNew.value
      ? await admin.posts.create(payload, cover.value)
      : await admin.posts.update(post.value.id, payload, cover.value)

    ui.success(data.message ?? 'حُفظ المقال.')
    cover.value = null
    saved.value = JSON.stringify(form.value)
    if (isNew.value && data.data?.id) router.replace(`/content/posts/${data.data.id}`)
    else load()
  } catch (e) {
    errors.value = e.fieldErrors ?? {}
    ui.error(Object.values(e.fieldErrors ?? {})[0]?.[0] ?? e.userMessage ?? 'تعذّر الحفظ.')
  } finally {
    saving.value = false
  }
}

const siteUrl = computed(() => (post.value?.status === 'published' ? `${__SITE_URL__}post.html?slug=${encodeURIComponent(post.value.slug)}` : null))

/* ── ما لم يُحفظ ─────────────────────────────────────────────────────── */

onBeforeRouteLeave(async () => {
  if (!dirty.value || saving.value) return true
  return Boolean(await confirmDialog({
    title: 'تغييرات لم تُحفظ',
    message: 'في المقال تعديلات لم تُحفظ بعد، وتضيع إن خرجت.',
    confirmLabel: 'اخرج دون حفظ',
    danger: true,
  }))
})
function beforeUnload(e) { if (dirty.value) { e.preventDefault(); e.returnValue = '' } }
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))

// Ctrl/⌘+S يحفظ كما في أي محرّر
function onKey(e) {
  if ((e.ctrlKey || e.metaKey) && e.code === 'KeyS') { e.preventDefault(); save() }
}
onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))

/** المساحة تتّسع مع النصّ: لا تمرير داخل مربّع صغير */
function grow(e) {
  const el = e.target
  el.style.height = 'auto'
  el.style.height = `${el.scrollHeight}px`
}
</script>

<template>
  <div v-if="loading" class="space-y-3">
    <div class="h-8 w-48 bg-sand-100 rounded animate-pulse" />
    <div class="h-96 bg-sand-100 rounded-2xl animate-pulse" />
  </div>

  <div v-else>
    <!-- الشريط العلوي: الرجوع، والحالة، والحفظ -->
    <div class="flex flex-wrap items-center gap-3 mb-5">
      <RouterLink to="/content" class="inline-flex items-center gap-1.5 text-sm text-sand-600 hover:text-clay-600">
        <BaseIcon name="chevron" :size="15" /> المقالات
      </RouterLink>
      <StatusBadge v-if="post" :label="post.status_label" :value="post.status" />
      <span v-if="dirty" class="text-xs text-amber-700">تعديلات لم تُحفظ</span>
      <span class="ms-auto flex flex-wrap items-center gap-2">
        <a v-if="siteUrl" :href="siteUrl" target="_blank" rel="noopener" class="btn btn-ghost text-sm">
          <BaseIcon name="external" :size="14" /> على الموقع
        </a>
        <button class="btn btn-outline" type="button" :disabled="saving" @click="save()">
          حفظ <kbd class="kbd">Ctrl S</kbd>
        </button>
        <button v-if="form.status !== 'published'" class="btn btn-primary" type="button" :disabled="saving" @click="save('published')">
          <BaseIcon name="eye" :size="15" /> انشر الآن
        </button>
      </span>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
      <!-- ══ الكتابة ══ -->
      <section class="card p-6 lg:p-8 min-w-0">
        <div class="flex gap-1 mb-6 border-b border-sand-200" role="tablist">
          <button
            v-for="t in [{ k: 'write', l: 'كتابة' }, { k: 'preview', l: 'معاينة كما على الموقع' }]" :key="t.k"
            type="button" role="tab" :aria-selected="tab === t.k"
            class="px-3 py-2 text-sm -mb-px border-b-2"
            :class="tab === t.k ? 'border-clay-600 text-clay-700 font-medium' : 'border-transparent text-sand-500 hover:text-sand-800'"
            @click="tab = t.k"
          >{{ t.l }}</button>
        </div>

        <!-- v-show لا v-if: المحرّر يبقى بسجلّ تراجعه حين تُفتح المعاينة -->
        <div v-show="tab === 'write'">
          <label class="sr-only" for="postTitle">العنوان</label>
          <textarea
            id="postTitle" v-model="form.title" rows="1" maxlength="200"
            class="w-full resize-none bg-transparent text-3xl font-bold leading-snug text-sand-900 placeholder:text-sand-300 focus:outline-none"
            placeholder="عنوان المقال" :aria-invalid="Boolean(errors.title)" @input="grow"
          />
          <p v-if="errors.title" class="text-xs text-rose-700 mt-1" role="alert">{{ errors.title[0] }}</p>

          <label class="sr-only" for="postExcerpt">المقتطف</label>
          <textarea
            id="postExcerpt" v-model="form.excerpt" rows="2" maxlength="600"
            class="w-full resize-none bg-transparent text-lg text-sand-600 mt-3 placeholder:text-sand-300 focus:outline-none"
            placeholder="سطران يقرؤهما الزائر في بطاقة المدونة قبل أن يفتح المقال…" @input="grow"
          />

          <hr class="my-5 border-sand-100" />

          <RichTextEditor id="postBody" v-model="form.body" placeholder="اكتب هنا. اختر نصّاً لتجعله عريضاً أو رابطاً، أو ابدأ سطراً بعنوان من الشريط." />
          <p class="text-xs text-sand-400 mt-4 border-t border-sand-100 pt-3">
            <span class="num">{{ num(words) }}</span> كلمة · <span class="num">{{ num(paragraphs) }}</span> فقرة ·
            قرابة <span class="num">{{ readMinutes }}</span> {{ readMinutes === 1 ? 'دقيقة' : 'دقائق' }} قراءة
          </p>
        </div>

        <!-- المعاينة بقواعد الموقع: فقرات، والغلاف فوقها -->
        <article v-if="tab === 'preview'" class="max-w-2xl mx-auto">
          <p class="text-xs font-medium text-clay-700 mb-2">{{ categories.find((c) => c.id === Number(form.category_id))?.name ?? 'مقال' }}</p>
          <h1 class="text-3xl font-bold text-sand-900 leading-snug">{{ form.title || 'بلا عنوان' }}</h1>
          <p v-if="form.excerpt" class="text-lg text-sand-600 mt-3">{{ form.excerpt }}</p>
          <p class="text-xs text-sand-400 mt-3">{{ form.published_at ? date(form.published_at) : 'تاريخ النشر عند النشر' }} · {{ readMinutes }} {{ readMinutes === 1 ? 'دقيقة' : 'دقائق' }} قراءة</p>
          <img v-if="coverShown" :src="coverShown" alt="" class="w-full aspect-video object-cover rounded-xl my-6" />
          <!-- ناتج المحرّر نفسه (مخطّطه لا يقبل إلا وسوم المقال)، والخادم ينظّفه عند الحفظ -->
          <div v-if="form.body" class="prose-admin mt-6" v-html="form.body" />
          <p v-else class="mt-6 text-sand-400">لا نصّ بعد.</p>
        </article>
      </section>

      <!-- ══ الإعدادات ══ -->
      <aside class="space-y-4">
        <section class="card p-5 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-sand-500 mb-1.5" for="postStatus">الحالة</label>
            <select id="postStatus" v-model="form.status" class="input text-sm">
              <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-sand-500 mb-1.5" for="postDate">تاريخ النشر</label>
            <input id="postDate" v-model="form.published_at" type="date" lang="en" class="input text-sm" />
            <p class="text-[0.7rem] text-sand-400 mt-1">فارغاً: يوم النشر. ومستقبلاً: يُنشر في يومه (مجدول).</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-sand-500 mb-1.5" for="postCategory">التصنيف</label>
            <select id="postCategory" v-model="form.category_id" class="input text-sm">
              <option value="">بلا تصنيف</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <label class="flex items-start gap-2.5 text-sm cursor-pointer">
            <input v-model="form.is_featured" type="checkbox" class="mt-1 accent-clay-600" />
            <span>مميّز<span class="block text-xs text-sand-500">يتصدّر المدونة.</span></span>
          </label>
        </section>

        <section class="card p-5">
          <p class="text-xs font-semibold text-sand-500 mb-2">صورة الغلاف</p>
          <img v-if="coverShown" :src="coverShown" alt="" class="w-full aspect-video object-cover rounded-lg mb-3" />
          <div v-else class="w-full aspect-video rounded-lg bg-sand-100 grid place-items-center text-sand-400 mb-3">
            <BaseIcon name="eye" :size="22" />
          </div>
          <label class="btn btn-outline w-full justify-center cursor-pointer text-sm">
            <BaseIcon name="upload" :size="14" /> {{ coverShown ? 'استبدال الصورة' : 'اختر صورة' }}
            <input id="postCover" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pickCover" />
          </label>
          <p class="text-[0.7rem] text-sand-400 mt-2">JPG أو PNG أو WebP. بلا وجوه أطفال واضحة.</p>
        </section>

        <p v-if="post" class="text-xs text-sand-400 px-1">
          <span class="num" dir="ltr">/{{ post.slug }}</span><br />
          <template v-if="post.views_count"><span class="num">{{ num(post.views_count) }}</span> قراءة</template>
        </p>
      </aside>
    </div>
  </div>
</template>

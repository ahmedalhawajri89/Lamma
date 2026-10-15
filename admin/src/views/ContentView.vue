<script setup>
/**
 * محتوى الموقع العام: المقالات والأسئلة والشهادات والشركاء.
 *
 * المقالات قائمةٌ تفتح محرّراً بصفحة كاملة. والأنواع الثلاثة البسيطة
 * قوائم تُرتَّب بالأسهم وتُظهر وتُخفى بمفتاح واحد، بلا فتح نافذة لكل
 * تبديل، والحذف داخل التحرير بعد رؤية العنصر.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { confirmDialog } from '@/composables/useConfirm'
import { admin } from '@/api/endpoints'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { date, num, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import StatusTabs from '@/components/StatusTabs.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import ContentItemModal from '@/components/ContentItemModal.vue'
import PaginationBar from '@/components/PaginationBar.vue'

/* global __SITE_URL__ */
const auth = useAuthStore()
const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const TABS = [
  { key: 'posts', label: 'المقالات' },
  { key: 'faqs', label: 'الأسئلة الشائعة' },
  { key: 'testimonials', label: 'شهادات الكفلاء' },
  { key: 'partners', label: 'الشركاء' },
]

// اللسان في الرابط: يُرسَل ويبقى بعد التحديث وبعد الرجوع من المحرّر
const tab = computed({
  get: () => (TABS.some((t) => t.key === route.query.tab) ? route.query.tab : 'posts'),
  set: (v) => router.replace({ query: v === 'posts' ? {} : { tab: v } }),
})
const isPosts = computed(() => tab.value === 'posts')
const can = computed(() => auth.can('content.manage'))

/* ── المقالات ────────────────────────────────────────────────────────── */

const posts = ref([])
const postsMeta = ref({})
const postsPage = ref(1)
const postFilters = ref({ status: '', q: '', category_id: '' })
const loading = ref(true)
let sequence = 0

async function loadPosts() {
  const mine = ++sequence
  loading.value = true
  try {
    const params = { per_page: 20, page: postsPage.value, ...Object.fromEntries(Object.entries(postFilters.value).filter(([, v]) => v !== '')) }
    const { data } = await admin.posts.list(params)
    if (mine !== sequence) return
    posts.value = data.data ?? []
    postsMeta.value = data.meta ?? {}
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر جلب المقالات.')
  } finally {
    if (mine === sequence) loading.value = false
  }
}

const STATUSES = computed(() => (postsMeta.value.statuses ?? []).map((s) => ({ value: s.value, label: s.label })))
let searchTimer = null
const searchLater = () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { postsPage.value = 1; loadPosts() }, 250) }
watch(() => [postFilters.value.status, postFilters.value.category_id], () => { postsPage.value = 1; loadPosts() })
watch(() => postFilters.value.q, searchLater)
watch(postsPage, loadPosts)

const postUrl = (row) => `${__SITE_URL__}post.html?slug=${encodeURIComponent(row.slug)}`

/* ── الأنواع البسيطة ─────────────────────────────────────────────────── */

const items = ref([])
const publishKey = computed(() => (tab.value === 'partners' ? 'is_active' : 'is_published'))
const busyId = ref(null)

async function loadItems() {
  const mine = ++sequence
  loading.value = true
  items.value = []
  try {
    const { data } = await admin.content.list(tab.value)
    if (mine === sequence) items.value = data.data ?? []
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر جلب المحتوى.')
  } finally {
    if (mine === sequence) loading.value = false
  }
}

const load = () => (isPosts.value ? loadPosts() : loadItems())
watch(tab, () => { postsPage.value = 1; load() })
onMounted(load)

/** إظهار وإخفاء بنقرة، والقائمة تتبع فوراً ثم تتأكد من الخادم */
async function toggle(row) {
  const key = publishKey.value
  const next = !row[key]
  busyId.value = row.id
  row[key] = next
  try {
    await admin.content.update(tab.value, row.id, { [key]: next })
    ui.success(next ? 'ظاهر على الموقع.' : 'أُخفي عن الموقع.')
  } catch (e) {
    row[key] = !next
    ui.error(e.userMessage)
  } finally {
    busyId.value = null
  }
}

/** الترتيب بالأسهم: يُرسل الترتيب كاملاً، فلا أرقام تتصادم */
async function move(index, step) {
  const to = index + step
  if (to < 0 || to >= items.value.length) return
  const list = [...items.value]
  ;[list[index], list[to]] = [list[to], list[index]]
  items.value = list
  try {
    await admin.content.reorder(tab.value, list.map((x) => x.id))
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر حفظ الترتيب.')
    loadItems()
  }
}

const label = (row) => row.question ?? row.author_name ?? row.name
const sublabel = (row) => (tab.value === 'faqs' ? row.answer : tab.value === 'testimonials' ? row.quote : row.url)

/* ── الإنشاء والتحرير ────────────────────────────────────────────────── */

const itemOpen = ref(false)
const editing = ref(null)

function create() {
  if (isPosts.value) router.push('/content/posts/new')
  else { editing.value = null; itemOpen.value = true }
}

function openRow(row) {
  if (!can.value) return
  if (isPosts.value) router.push(`/content/posts/${row.id}`)
  else { editing.value = row; itemOpen.value = true }
}

function onSaved() {
  itemOpen.value = false
  load()
}

async function remove(row) {
  const answer = await confirmDialog({
    title: 'حذف العنصر',
    message: `سيُحذف «${label(row)}» من الموقع، ولا يمكن التراجع.`,
    confirmLabel: 'احذف',
    danger: true,
  })
  if (!answer) return
  try {
    const { data } = await admin.content.remove(tab.value, row.id)
    ui.success(data.message ?? 'حُذف العنصر.')
    itemOpen.value = false
    loadItems()
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر الحذف.')
  }
}
</script>

<template>
  <div>
    <PageHeader title="محتوى الموقع" subtitle="ما يقرأه الزائر على الموقع العام، ويتغيّر فور الحفظ.">
      <template #actions>
        <button v-if="can" class="btn btn-primary" type="button" @click="create">
          <BaseIcon name="plus" :size="16" />
          {{ isPosts ? 'مقال جديد' : 'إضافة' }}
        </button>
      </template>
    </PageHeader>

    <div class="flex gap-1 mb-4 border-b border-sand-200 overflow-x-auto [scrollbar-width:none]" role="tablist">
      <button
        v-for="t in TABS" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key"
        class="px-3 py-2 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition-colors"
        :class="tab === t.key ? 'border-clay-600 text-clay-700' : 'border-transparent text-sand-600 hover:text-sand-900'"
        @click="tab = t.key"
      >{{ t.label }}</button>
    </div>

    <!-- ══ المقالات ══ -->
    <template v-if="isPosts">
      <StatusTabs
        v-model="postFilters.status" :options="STATUSES" :counts="postsMeta.status_counts ?? {}"
        :total="postsMeta.all_count ?? null" label="حالة المقال"
      />

      <div class="flex flex-wrap items-center gap-2 mb-4">
        <label class="relative flex-1 min-w-52 max-w-80">
          <span class="sr-only">ابحث في العناوين</span>
          <BaseIcon name="search" :size="16" class="absolute top-1/2 -translate-y-1/2 text-sand-400 pointer-events-none" style="inset-inline-start: 0.75rem" />
          <input v-model="postFilters.q" type="search" class="input ps-9" placeholder="ابحث في العناوين والمقتطفات…" />
        </label>
        <select v-model="postFilters.category_id" class="input !w-auto text-sm" aria-label="التصنيف">
          <option value="">كل التصنيفات</option>
          <option v-for="c in postsMeta.categories ?? []" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <div v-if="loading && !posts.length" class="card divide-y divide-sand-100">
        <div v-for="n in 4" :key="n" class="h-20 animate-pulse bg-sand-50" />
      </div>

      <ul v-else-if="posts.length" class="card divide-y divide-sand-100" aria-label="المقالات">
        <li v-for="p in posts" :key="p.id">
          <div
            class="flex items-center gap-4 p-3 sm:p-4 hover:bg-sand-25 cursor-pointer focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-clay-600"
            tabindex="0" @click="openRow(p)" @keydown.enter="openRow(p)"
          >
            <img v-if="p.image" :src="p.image" alt="" class="w-24 aspect-video rounded-lg object-cover shrink-0" loading="lazy" />
            <span v-else class="w-24 aspect-video rounded-lg bg-sand-100 grid place-items-center text-sand-300 shrink-0"><BaseIcon name="file" :size="18" /></span>

            <span class="flex-1 min-w-0">
              <span class="flex flex-wrap items-center gap-1.5">
                <span class="font-medium text-sand-900">{{ p.title }}</span>
                <span v-if="p.featured" class="badge bg-clay-600/10 text-clay-700">مميّز</span>
              </span>
              <span class="block text-xs text-sand-500 mt-0.5 line-clamp-1">{{ p.excerpt || EMPTY }}</span>
              <span class="block text-xs text-sand-400 mt-1">
                {{ p.category ?? 'بلا تصنيف' }} · {{ p.date ? date(p.date) : 'لم يُنشر' }}
                <template v-if="p.views_count"> · <span class="num">{{ num(p.views_count) }}</span> قراءة</template>
              </span>
            </span>

            <span class="flex flex-col items-end gap-1.5 shrink-0">
              <StatusBadge :label="p.status_label ?? p.status" :value="p.status" />
              <a
                v-if="p.status === 'published'" :href="postUrl(p)" target="_blank" rel="noopener"
                class="inline-flex items-center gap-1 text-xs text-clay-700 hover:underline" @click.stop
              ><BaseIcon name="external" :size="12" /> على الموقع</a>
            </span>
          </div>
        </li>
      </ul>

      <div v-else class="card p-10 text-center">
        <p class="text-sand-500">{{ postFilters.q || postFilters.status || postFilters.category_id ? 'لا مقالات تطابق البحث.' : 'لا مقالات بعد.' }}</p>
        <button v-if="can" class="btn btn-primary mt-4" type="button" @click="create"><BaseIcon name="plus" :size="16" /> اكتب أول مقال</button>
      </div>

      <PaginationBar v-model="postsPage" :meta="postsMeta" />
    </template>

    <!-- ══ الأسئلة والشهادات والشركاء ══ -->
    <template v-else>
      <div v-if="loading && !items.length" class="card divide-y divide-sand-100">
        <div v-for="n in 4" :key="n" class="h-16 animate-pulse bg-sand-50" />
      </div>

      <ol v-else-if="items.length" class="card divide-y divide-sand-100" :aria-label="TABS.find((t) => t.key === tab)?.label">
        <li v-for="(row, i) in items" :key="row.id" class="flex items-center gap-3 p-3 sm:px-4">
          <!-- الترتيب: كما يظهر على الموقع -->
          <span v-if="can" class="flex flex-col shrink-0">
            <button type="button" class="btn btn-ghost !p-1" :disabled="i === 0" :aria-label="`انقل «${label(row)}» إلى الأعلى`" @click="move(i, -1)">
              <BaseIcon name="chevron" :size="14" style="transform: rotate(-90deg)" />
            </button>
            <button type="button" class="btn btn-ghost !p-1" :disabled="i === items.length - 1" :aria-label="`انقل «${label(row)}» إلى الأسفل`" @click="move(i, 1)">
              <BaseIcon name="chevron" :size="14" style="transform: rotate(90deg)" />
            </button>
          </span>

          <button type="button" class="flex-1 min-w-0 text-start" :disabled="!can" @click="openRow(row)">
            <span class="block font-medium text-sand-900" :class="row[publishKey] ? '' : 'text-sand-500'">{{ label(row) }}</span>
            <span v-if="tab === 'testimonials' && row.role" class="block text-xs text-sand-500">{{ row.role }}</span>
            <span class="block text-xs text-sand-500 line-clamp-1 mt-0.5" :dir="tab === 'partners' ? 'ltr' : undefined">{{ sublabel(row) || EMPTY }}</span>
          </button>

          <!-- ظاهر أم مخفي: مفتاحٌ لا نافذة -->
          <button
            v-if="can" type="button" role="switch" :aria-checked="Boolean(row[publishKey])" :disabled="busyId === row.id"
            :aria-label="`${row[publishKey] ? 'ظاهر' : 'مخفي'}: ${label(row)}`"
            class="flex items-center gap-2 text-xs shrink-0" @click="toggle(row)"
          >
            <span class="hidden sm:inline" :class="row[publishKey] ? 'text-olive-700' : 'text-sand-500'">{{ row[publishKey] ? 'ظاهر' : 'مخفي' }}</span>
            <span class="relative w-9 h-5 rounded-full transition-colors" :class="row[publishKey] ? 'bg-clay-600' : 'bg-sand-300'">
              <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" :class="row[publishKey] ? 'start-[1.125rem]' : 'start-0.5'" />
            </span>
          </button>
        </li>
      </ol>

      <div v-else class="card p-10 text-center">
        <p class="text-sand-500">لا عناصر بعد.</p>
        <button v-if="can" class="btn btn-primary mt-4" type="button" @click="create"><BaseIcon name="plus" :size="16" /> أضف أول عنصر</button>
      </div>
    </template>

    <ContentItemModal
      :open="itemOpen" :type="tab" :item="editing"
      @close="itemOpen = false" @saved="onSaved" @remove="remove"
    />
  </div>
</template>

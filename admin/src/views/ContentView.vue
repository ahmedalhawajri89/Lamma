<script setup>
/**
 * محتوى الموقع العام: المقالات والأسئلة والشهادات والشركاء.
 *
 * هذه الشاشة لم تكن موجودة. فكل ما يقرأه الزائر — المدونة، وأجوبة الأسئلة
 * الشائعة، وشهادات الكفلاء، وشعارات الشركاء — كان يُكتب في بذرة قاعدة
 * البيانات وحدها. أي تعديل يعني تحرير سكربت ثم إعادة بذر.
 *
 * وأربعة أنواع في شاشة واحدة بألسنة لا أربع شاشات في القائمة الجانبية:
 * القائمة طويلة أصلاً، وهذه الأربعة يزورها المحرّر معاً.
 */
import { computed, onMounted, ref, watch } from 'vue'
import { confirmDialog } from '@/composables/useConfirm'
import { admin } from '@/api/endpoints'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { date, EMPTY } from '@/utils/format'
import PageHeader from '@/components/PageHeader.vue'
import DataTable from '@/components/DataTable.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import PostFormModal from '@/components/PostFormModal.vue'
import ContentItemModal from '@/components/ContentItemModal.vue'

const auth = useAuthStore()
const ui = useUiStore()

const TABS = [
  { key: 'posts', label: 'المقالات' },
  { key: 'faqs', label: 'الأسئلة الشائعة' },
  { key: 'testimonials', label: 'شهادات الكفلاء' },
  { key: 'partners', label: 'الشركاء' },
]

const COLUMNS = {
  posts: [
    { key: 'title', label: 'العنوان' },
    { key: 'category', label: 'التصنيف' },
    { key: 'date', label: 'تاريخ النشر' },
    { key: 'views_count', label: 'قراءات', align: 'end' },
    { key: 'status', label: 'الحالة' },
  ],
  faqs: [
    { key: 'question', label: 'السؤال' },
    { key: 'category', label: 'التصنيف' },
    { key: 'sort_order', label: 'الترتيب', align: 'end' },
    { key: 'state', label: 'الحالة' },
    { key: 'actions', label: 'إجراءات', align: 'end' },
  ],
  testimonials: [
    { key: 'author_name', label: 'الاسم' },
    { key: 'role', label: 'الصفة' },
    { key: 'quote', label: 'الشهادة' },
    { key: 'sort_order', label: 'الترتيب', align: 'end' },
    { key: 'state', label: 'الحالة' },
    { key: 'actions', label: 'إجراءات', align: 'end' },
  ],
  partners: [
    { key: 'name', label: 'الاسم' },
    { key: 'url', label: 'الرابط' },
    { key: 'sort_order', label: 'الترتيب', align: 'end' },
    { key: 'state', label: 'الحالة' },
    { key: 'actions', label: 'إجراءات', align: 'end' },
  ],
}

const tab = ref('posts')
const items = ref([])
const loading = ref(true)
const categories = ref([])
const statuses = ref([])
let sequence = 0

const postOpen = ref(false)
const itemOpen = ref(false)
const editing = ref(null)

const can = computed(() => auth.can('content.manage'))
const columns = computed(() => COLUMNS[tab.value])
const isPosts = computed(() => tab.value === 'posts')

/** حقل حالة النشر يختلف اسمه بين الجداول — والشاشة تعرضه بعمود واحد. */
const publishKey = computed(() => (tab.value === 'partners' ? 'is_active' : 'is_published'))

async function load() {
  // استجابة تبويب سابق تصل متأخرة لا تملأ أعمدة التبويب الجديد
  const mine = ++sequence
  loading.value = true
  items.value = []

  try {
    const { data } = isPosts.value
      ? await admin.posts.list({ per_page: 50 })
      : await admin.content.list(tab.value)

    if (mine !== sequence) return
    items.value = data.data ?? []
    if (isPosts.value) {
      categories.value = data.meta?.categories ?? []
      statuses.value = data.meta?.statuses ?? []
    }
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر جلب المحتوى.')
  } finally {
    if (mine === sequence) loading.value = false
  }
}


watch(tab, load)

function create() {
  editing.value = null
  if (isPosts.value) postOpen.value = true
  else itemOpen.value = true
}

function edit(row) {
  if (!can.value) return
  editing.value = row
  if (isPosts.value) postOpen.value = true
  else itemOpen.value = true
}

function onSaved() {
  postOpen.value = false
  itemOpen.value = false
  load()
}

/**
 * الحذف للأنواع البسيطة وحدها.
 *
 * المقال لا يُحذف بل يُؤرشف: رابطه قد يكون منشوراً في مكان ما، وحذفه يترك
 * ٤٠٤ لمن يفتحه. والأرشفة تُخفيه عن المدونة وتُبقي الرابط عاملاً.
 */
async function remove(row) {
  const label = row.question ?? row.author_name ?? row.name
  const answer = await confirmDialog({
    title: 'حذف العنصر',
    message: `سيُحذف «${label}» من الموقع، ولا يمكن التراجع.`,
    confirmLabel: 'احذف',
    danger: true,
  })
  if (!answer) return

  try {
    const { data } = await admin.content.remove(tab.value, row.id)
    ui.success(data.message ?? 'حُذف العنصر.')
    await load()
  } catch (e) {
    ui.error(e.userMessage ?? 'تعذّر الحذف.')
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="محتوى الموقع"
      subtitle="ما يقرأه الزائر على الموقع العام، ويتغيّر فور الحفظ."
    >
      <template #actions>
        <button v-if="can" class="btn btn-primary" type="button" @click="create">
          <BaseIcon name="plus" :size="16" />
          {{ isPosts ? 'مقال جديد' : 'إضافة' }}
        </button>
      </template>
    </PageHeader>

    <!-- الألسنة: `aria-selected` لا صنفٌ نشط وحده -->
    <div class="flex gap-1 mb-4 border-b border-sand-200" role="tablist">
      <button
        v-for="t in TABS" :key="t.key"
        class="px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="tab === t.key
          ? 'border-clay-600 text-clay-700'
          : 'border-transparent text-sand-600 hover:text-sand-900'"
        type="button" role="tab" :aria-selected="tab === t.key"
        @click="tab = t.key"
      >
        {{ t.label }}
      </button>
    </div>

    <DataTable
      :columns="columns" :rows="items" :loading="loading"
      :clickable="can"
      @row-click="edit"
    >
      <!-- المقالات -->
      <template #title="{ row }">
        <span class="font-medium">{{ row.title }}</span>
        <span v-if="row.featured" class="badge bg-clay-600/10 text-clay-700 ms-1.5">مميّز</span>
      </template>
      <template #category="{ row }">{{ row.category ?? EMPTY }}</template>
      <template #date="{ row }">{{ date(row.date) }}</template>
      <template #views_count="{ row }"><span class="num">{{ row.views_count ?? 0 }}</span></template>
      <template #status="{ row }"><StatusBadge :label="row.status_label ?? row.status" :value="row.status" /></template>

      <!-- الأنواع البسيطة -->
      <template #question="{ row }"><span class="font-medium">{{ row.question }}</span></template>
      <template #quote="{ row }">
        <span class="text-sand-600 line-clamp-1">{{ row.quote }}</span>
      </template>
      <template #url="{ row }">
        <a
          v-if="row.url" class="text-clay-700 hover:underline" :href="row.url"
          target="_blank" rel="noopener" @click.stop
        >{{ row.url }}</a>
        <span v-else class="text-sand-500">{{ EMPTY }}</span>
      </template>
      <template #sort_order="{ row }"><span class="num">{{ row.sort_order }}</span></template>
      <template #state="{ row }">
        <span
          class="badge"
          :class="row[publishKey] ? 'bg-olive-100 text-olive-700' : 'bg-sand-100 text-sand-600'"
        >
          {{ row[publishKey] ? 'ظاهر' : 'مخفي' }}
        </span>
      </template>
      <template #actions="{ row }">
        <button
          v-if="can" class="btn btn-ghost p-1.5 text-rose-700 hover:bg-rose-600/10" type="button"
          title="حذف" aria-label="حذف" @click.stop="remove(row)"
        >
          <BaseIcon name="trash" :size="14" />
        </button>
      </template>
    </DataTable>

    <PostFormModal
      :open="postOpen" :post="editing" :categories="categories" :statuses="statuses"
      @close="postOpen = false" @saved="onSaved"
    />

    <ContentItemModal
      :open="itemOpen" :type="tab" :item="editing"
      @close="itemOpen = false" @saved="onSaved"
    />
  </div>
</template>

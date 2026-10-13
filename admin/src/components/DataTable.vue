<script setup>
import { onBeforeUnmount, ref } from 'vue'
import { EMPTY } from '@/utils/format'
import BaseIcon from './BaseIcon.vue'

/**
 * جدول القوائم.
 *
 * على الشاشات العريضة جدول، وعلى الجوال بطاقات: كان الجدول يتمرّر أفقياً
 * داخل حاويته على الجوال، فيغيب عمود الحالة والإجراءات خارج الشاشة ولا
 * يعرف المستخدم أنهما هناك. البطاقة تعرض كل عمود باسمه، والأول عنوانها.
 *
 * وثلاث حالات لا حالتان: يُحمَّل، أو فشل (برسالة وزر إعادة)، أو فارغ.
 * كان الفشل يظهر «لا نتائج مطابقة»، فيظن المستخدم أن البيانات فارغة.
 */
defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },

  /** رسالة الفشل من `useResource().error` */
  error: { type: String, default: null },

  emptyText: { type: String, default: 'لا نتائج مطابقة.' },
  rowKey: { type: String, default: 'id' },

  /**
   * الصف يفتح شيئاً (ملف أو نافذة تعديل). حينها يصير قابلاً للتركيز ويُفتح
   * بـEnter أو المسافة، لا بالفأرة وحدها، ويحمل مؤشر اليد هو لا الجدول كله.
   */
  clickable: { type: Boolean, default: false },

  /** الترتيب الحالي: العمود (`sortable: true` أو `sortKey`) واتجاهه */
  sort: { type: String, default: '' },
  dir: { type: String, default: 'desc' },
})

const emit = defineEmits(['row-click', 'retry', 'sort'])

function activate(row) {
  emit('row-click', row)
}

const sortKey = (col) => col.sortKey ?? col.key

/**
 * عرضٌ واحد في الصفحة لا عرضان مخفيّ أحدهما بالـCSS: الإخفاء يُبقي كل قيمة
 * مرتين في الصفحة (لأدوات القراءة والبحث والاختبار)، ويرسم كل خانة مرتين.
 */
const query = window.matchMedia?.('(max-width: 767px)')
const mobile = ref(query?.matches ?? false)
const onChange = (e) => { mobile.value = e.matches }
query?.addEventListener?.('change', onChange)
onBeforeUnmount(() => query?.removeEventListener?.('change', onChange))
</script>

<template>
  <div class="card overflow-hidden">
    <!-- ── الحالتان المشتركتان: الفشل والفراغ ── -->
    <div v-if="!loading && error" class="px-4 py-12 text-center" role="alert">
      <BaseIcon name="alert" :size="26" class="mx-auto text-rose-600 mb-2" />
      <p class="font-medium text-sand-900">تعذّر تحميل البيانات</p>
      <p class="text-sm text-sand-500 mt-1">{{ error }}</p>
      <button class="btn btn-outline mt-4" type="button" @click="emit('retry')">إعادة المحاولة</button>
    </div>

    <template v-else>
      <!-- ── الجدول: الشاشات المتوسطة فما فوق ── -->
      <div v-if="!mobile" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-sand-200 bg-sand-25">
              <th
                v-for="col in columns" :key="col.key" scope="col"
                class="px-4 py-3 text-start font-medium text-sand-600 text-xs whitespace-nowrap"
                :class="col.align === 'end' ? 'text-end' : ''"
                :aria-sort="col.sortable && sort === sortKey(col) ? (dir === 'asc' ? 'ascending' : 'descending') : undefined"
              >
                <button
                  v-if="col.sortable" type="button"
                  class="inline-flex items-center gap-1 hover:text-sand-900"
                  :class="sort === sortKey(col) ? 'text-sand-900' : ''"
                  @click="emit('sort', sortKey(col))"
                >
                  {{ col.label }}
                  <BaseIcon
                    name="chevron" :size="12"
                    :class="sort === sortKey(col) ? 'opacity-100' : 'opacity-30'"
                    :style="{ transform: sort === sortKey(col) && dir === 'asc' ? 'rotate(-90deg)' : 'rotate(90deg)' }"
                  />
                </button>
                <template v-else>{{ col.label }}</template>
              </th>
            </tr>
          </thead>

          <tbody>
            <!--
              الشرط على غلافٍ لا على `<tr>` نفسه: الصفوف الهيكلية بلا `@click`،
              فأي شيء يستهدف `tr:first` يصيب صفّاً لا يستجيب، ولا يقول أحد لماذا.
            -->
            <template v-if="loading">
              <tr v-for="n in 5" :key="`sk-${n}`" class="border-b border-sand-100">
                <td v-for="col in columns" :key="col.key" class="px-4 py-3.5">
                  <div class="h-3 rounded bg-sand-100 animate-pulse" :style="{ width: `${40 + (n * 7) % 45}%` }" />
                </td>
              </tr>
            </template>

            <tr v-else-if="!rows.length">
              <td :colspan="columns.length" class="px-4 py-14 text-center">
                <BaseIcon name="search" :size="28" class="mx-auto text-sand-300 mb-2" />
                <p class="text-sand-500">{{ emptyText }}</p>
                <slot name="empty-action" />
              </td>
            </tr>

            <tr
              v-else v-for="row in rows" :key="row[rowKey]"
              class="border-b border-sand-100 last:border-0 hover:bg-sand-25 transition-colors"
              :class="clickable ? 'cursor-pointer focus-visible:bg-clay-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-clay-600' : ''"
              :tabindex="clickable ? 0 : undefined"
              @click="activate(row)"
              @keydown.enter="clickable && activate(row)"
              @keydown.space.prevent="clickable && activate(row)"
            >
              <td
                v-for="col in columns" :key="col.key"
                class="px-4 py-3 align-middle"
                :class="col.align === 'end' ? 'text-end' : ''"
              >
                <slot :name="col.key" :row="row" :value="row[col.key]">
                  {{ row[col.key] ?? EMPTY }}
                </slot>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── البطاقات: الجوال ── -->
      <ul v-else class="divide-y divide-sand-100" :aria-busy="loading">
        <template v-if="loading">
          <li v-for="n in 4" :key="`skm-${n}`" class="p-4 space-y-2" aria-hidden="true">
            <div class="h-3.5 w-1/2 rounded bg-sand-100 animate-pulse" />
            <div class="h-3 w-3/4 rounded bg-sand-100 animate-pulse" />
          </li>
        </template>

        <li v-else-if="!rows.length" class="px-4 py-12 text-center text-sand-500">
          {{ emptyText }}
          <slot name="empty-action" />
        </li>

        <li
          v-else v-for="row in rows" :key="`m-${row[rowKey]}`"
          class="p-4"
          :class="clickable ? 'cursor-pointer active:bg-sand-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-clay-600' : ''"
          :tabindex="clickable ? 0 : undefined"
          @click="activate(row)"
          @keydown.enter="clickable && activate(row)"
        >
          <!-- العمود الأول عنوان البطاقة -->
          <div class="font-medium text-sand-900">
            <slot :name="columns[0].key" :row="row" :value="row[columns[0].key]">
              {{ row[columns[0].key] ?? EMPTY }}
            </slot>
          </div>

          <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-sm">
            <template v-for="col in columns.slice(1).filter((c) => c.mobile !== false)" :key="col.key">
              <dt class="text-xs text-sand-500 pt-0.5">{{ col.label }}</dt>
              <dd class="min-w-0 text-sand-800">
                <slot :name="col.key" :row="row" :value="row[col.key]">
                  {{ row[col.key] ?? EMPTY }}
                </slot>
              </dd>
            </template>
          </dl>
        </li>
      </ul>
    </template>
  </div>
</template>

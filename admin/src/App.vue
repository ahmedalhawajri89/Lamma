<script setup>
import { computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useQueueStore } from '@/stores/queue'
import BaseIcon from '@/components/BaseIcon.vue'
import ToastStack from '@/components/ToastStack.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const auth = useAuthStore()
const ui = useUiStore()
const queue = useQueueStore()
const router = useRouter()
const route = useRoute()

const blank = computed(() => route.meta.layout === 'blank')

/** القائمة تُبنى من الموجّه نفسه، فإضافة شاشة لا تحتاج تعديل مكانين. */
const links = computed(() =>
  router.getRoutes().filter(
    (r) => r.meta?.title && !r.meta.hidden && !r.meta.guest &&
      (!r.meta.permission || auth.can(r.meta.permission)),
  ),
)

/**
 * الشريط مجمّع بحسب طبيعة العمل (`meta.group` في الموجّه).
 * القسم الذي لم يبقَ فيه رابط مسموح لهذا الدور لا يظهر أصلاً.
 */
const GROUPS = [
  { key: null, title: null },
  { key: 'cases', title: 'الحالات' },
  { key: 'money', title: 'الكفالات والمال' },
  { key: 'comms', title: 'التواصل' },
  { key: 'system', title: 'النظام' },
]

const sections = computed(() =>
  GROUPS
    .map((g) => ({ ...g, links: links.value.filter((l) => (l.meta.group ?? null) === g.key) }))
    .filter((g) => g.links.length),
)

const ROLE_LABELS = { admin: 'مدير النظام', accountant: 'محاسب', field_supervisor: 'مشرف ميداني' }

// أرقام «بانتظارك» بجانب الروابط: تُحدَّث مع التنقّل، بحدّ أدنى دقيقة
watch(
  () => [auth.isStaff, route.fullPath],
  ([staff]) => { if (staff && !blank.value) queue.refresh() },
  { immediate: true },
)

async function signOut() {
  queue.clear()
  await auth.logout()
  ui.success('تم تسجيل الخروج.')
  router.push({ name: 'login' })
}

onMounted(() => auth.fetchUser())
</script>

<template>
  <RouterView v-if="blank" />

  <div v-else class="min-h-screen flex bg-sand-50">
    <a
      href="#main"
      class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:z-50 focus:bg-clay-600 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg"
      style="inset-inline-start: 0.75rem"
    >
      تخطَّ إلى المحتوى
    </a>

    <!--
      الشريط الجانبي. على الشاشات الكبيرة يُطوى إلى عمود أيقونات (`is-rail`)
      بزرّ أعلاه، فتختفي التسميات بانسياب ويظهر اسم كل رابط عند المرور عليه.
      وعلى الجوال يبقى درجاً ينزلق من الجانب كما كان.
    -->
    <aside
      class="side fixed lg:sticky top-0 h-screen shrink-0 bg-sand-0 border-e border-sand-200 z-40 flex flex-col"
      :class="[ui.sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0', { 'is-rail': ui.sidebarCollapsed }]"
    >
      <div class="side__head p-4 border-b border-sand-200 flex items-center gap-2.5">
        <img src="/logo.svg" alt="" width="36" height="36" class="shrink-0" />
        <div class="side__label min-w-0">
          <p class="font-semibold text-sand-900 leading-tight whitespace-nowrap">كفالة</p>
          <p class="text-xs text-sand-500 whitespace-nowrap">لوحة الإدارة</p>
        </div>

        <!-- الطيّ والتوسيع: على الشاشات الكبيرة وحدها -->
        <button
          class="side__toggle hidden lg:grid ms-auto size-8 place-items-center rounded-lg text-sand-500 hover:bg-sand-100 hover:text-sand-900"
          type="button"
          :aria-label="ui.sidebarCollapsed ? 'توسيع القائمة' : 'طيّ القائمة'"
          :aria-expanded="!ui.sidebarCollapsed"
          :title="ui.sidebarCollapsed ? 'توسيع القائمة' : 'طيّ القائمة'"
          @click="ui.toggleSidebar()"
        >
          <BaseIcon name="sidebar" :size="18" />
        </button>

        <button
          class="ms-auto lg:hidden text-sand-500" type="button"
          aria-label="إغلاق القائمة" @click="ui.sidebarOpen = false"
        >
          <BaseIcon name="close" :size="18" />
        </button>
      </div>

      <nav class="side__nav flex-1 overflow-y-auto p-3" aria-label="التنقل الرئيسي">
        <div v-for="section in sections" :key="section.key ?? 'home'" class="mb-3 last:mb-0">
          <p v-if="section.title" class="side__section px-3 pt-2 pb-1.5 text-xs font-medium text-sand-500 whitespace-nowrap">
            {{ section.title }}
          </p>
          <RouterLink
            v-for="link in section.links" :key="link.name" :to="{ name: link.name }"
            class="side__link relative flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-sand-700 hover:bg-sand-50 transition-colors"
            active-class="!bg-clay-50 !text-clay-700 font-medium"
            :aria-label="ui.sidebarCollapsed ? link.meta.title : undefined"
            @click="ui.sidebarOpen = false"
          >
            <BaseIcon :name="link.meta.icon ?? 'gauge'" :size="18" class="shrink-0" />
            <span class="side__label flex-1 whitespace-nowrap">{{ link.meta.title }}</span>
            <span
              v-if="queue.countsByPath[link.path]"
              class="side__badge num min-w-5 h-5 px-1.5 grid place-items-center rounded-full bg-amber-100 text-amber-700 text-[0.7rem] font-semibold"
              :aria-label="`${queue.countsByPath[link.path]} بانتظارك`"
            >
              {{ queue.countsByPath[link.path] }}
            </span>
            <!-- اسم الرابط عند المرور، حين يكون الشريط مطويّاً -->
            <span class="side__tip" aria-hidden="true">
              {{ link.meta.title }}
              <template v-if="queue.countsByPath[link.path]"> · <span class="num">{{ queue.countsByPath[link.path] }}</span></template>
            </span>
          </RouterLink>
        </div>
      </nav>

      <div class="p-3 border-t border-sand-200">
        <div class="side__user relative flex items-center gap-2.5 px-2 py-2 mb-1">
          <span class="size-9 shrink-0 grid place-items-center rounded-full bg-clay-100 text-clay-700 font-semibold">
            {{ auth.user?.name?.charAt(0) ?? '؟' }}
          </span>
          <div class="side__label min-w-0 flex-1">
            <p class="text-sm font-medium text-sand-900 truncate">{{ auth.user?.name }}</p>
            <p class="text-xs text-sand-500 truncate">{{ auth.user?.staff_profile?.title ?? ROLE_LABELS[auth.roles[0]] ?? auth.roles[0] }}</p>
          </div>
          <span class="side__tip" aria-hidden="true">{{ auth.user?.name }}</span>
        </div>

        <button
          class="side__link relative btn btn-ghost w-full justify-start text-sm" type="button"
          :aria-label="ui.sidebarCollapsed ? 'تسجيل الخروج' : undefined" @click="signOut"
        >
          <BaseIcon name="logout" :size="17" class="shrink-0" />
          <span class="side__label whitespace-nowrap">تسجيل الخروج</span>
          <span class="side__tip" aria-hidden="true">تسجيل الخروج</span>
        </button>
      </div>
    </aside>

    <div
      v-if="ui.sidebarOpen" class="fixed inset-0 bg-sand-900/40 z-30 lg:hidden"
      @click="ui.sidebarOpen = false"
    />

    <div class="flex-1 min-w-0">
      <header class="lg:hidden sticky top-0 z-20 bg-sand-0/90 backdrop-blur border-b border-sand-200 px-4 py-3 flex items-center gap-3">
        <button type="button" aria-label="فتح القائمة" @click="ui.sidebarOpen = true">
          <BaseIcon name="menu" :size="22" />
        </button>
        <span class="font-medium">{{ route.meta.title }}</span>
      </header>

      <main id="main" class="p-4 sm:p-6 lg:p-8">
        <!-- بلا Suspense: لا setup غير متزامن هنا، وشاشات كثيرة جذرها
             v-if/v-else فتصبح أكثر من عقدة جذر وهو ما يشكو منه Suspense -->
        <RouterView />
      </main>
    </div>

    <ToastStack />
    <ConfirmDialog />
  </div>
</template>

<style>
/*
 * الشريط الجانبي وطيّه.
 * العرض يتغيّر بانسياب، والتسميات تختفي بالشفافية أسرع منه قليلاً، فلا
 * يُرى نصّ مقصوص أثناء الحركة. وكله يُلغى لمن طلب تقليل الحركة.
 */
.side {
  width: 16rem;
  transition:
    width 0.28s cubic-bezier(0.4, 0, 0.2, 1),
    transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}

.side__label {
  transition: opacity 0.18s ease;
}

.side__toggle :deep(svg),
.side__toggle svg {
  transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}

/* التلميح: مخفيّ إلا في الوضع المطويّ عند المرور أو التركيز */
.side__tip {
  display: none;
}

@media (min-width: 1024px) {
  .side.is-rail {
    width: 4.5rem;
  }

  .side.is-rail .side__label {
    opacity: 0;
    width: 0;
    overflow: hidden;
    pointer-events: none;
  }

  /* الرأس: الشعار فوق زرّ التوسيع، والزرّ يدور ليشير إلى الجهة الأخرى */
  .side.is-rail .side__head {
    flex-direction: column;
    padding-inline: 0.5rem;
  }

  .side.is-rail .side__toggle {
    margin-inline-start: 0;
  }

  .side.is-rail .side__toggle svg {
    transform: scaleX(-1);
  }

  /* عناوين الأقسام تصير خطاً فاصلاً رفيعاً */
  .side.is-rail .side__section {
    height: 1px;
    margin: 0.5rem 0.75rem;
    padding: 0;
    overflow: hidden;
    font-size: 0;
    background: var(--color-sand-200);
  }

  .side.is-rail .side__link,
  .side.is-rail .side__user {
    justify-content: center;
    padding-inline: 0;
  }

  /* الرقم يصير نقطة ذهبية على طرف الأيقونة */
  .side.is-rail .side__badge {
    position: absolute;
    inset-block-start: 0.35rem;
    inset-inline-start: 1.6rem;
    min-width: 0;
    width: 0.5rem;
    height: 0.5rem;
    padding: 0;
    font-size: 0;
    background: var(--color-amber-500);
    box-shadow: 0 0 0 2px var(--color-sand-0);
  }

  /* التمرير يقصّ التلميحات الخارجة من الشريط؛ في العمود الضيق القائمة تتسع للشاشة */
  .side.is-rail .side__nav {
    overflow: visible;
  }

  .side.is-rail .side__tip {
    display: block;
    position: absolute;
    z-index: 50;
    inset-block-start: 50%;
    inset-inline-start: calc(100% + 1.1rem);
    padding: 0.35rem 0.65rem;
    border-radius: 0.5rem;
    background: var(--color-sand-900);
    color: white;
    font-size: 0.8rem;
    font-weight: 500;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    transform: translate(calc(-0.25rem * var(--tip-dir, 1)), -50%);
    transition: opacity 0.15s ease, transform 0.15s ease;
  }

  .side.is-rail .side__link:hover .side__tip,
  .side.is-rail .side__link:focus-visible .side__tip,
  .side.is-rail .side__user:hover .side__tip {
    opacity: 1;
    transform: translate(0, -50%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .side,
  .side__label,
  .side__toggle svg,
  .side__tip {
    transition: none !important;
  }
}
</style>

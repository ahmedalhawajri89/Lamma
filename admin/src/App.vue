<script setup>
import { computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import BaseIcon from '@/components/BaseIcon.vue'
import ToastStack from '@/components/ToastStack.vue'

const auth = useAuthStore()
const ui = useUiStore()
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

async function signOut() {
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

    <!-- الشريط الجانبي -->
    <aside
      class="fixed lg:sticky top-0 h-screen w-64 shrink-0 bg-sand-0 border-e border-sand-200 z-40 flex flex-col transition-transform"
      :class="ui.sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
    >
      <div class="p-4 border-b border-sand-200 flex items-center gap-2.5">
        <img src="/logo.svg" alt="" width="36" height="36" />
        <div class="min-w-0">
          <p class="font-semibold text-sand-900 leading-tight">كفالة</p>
          <p class="text-xs text-sand-500">لوحة الإدارة</p>
        </div>
        <button
          class="ms-auto lg:hidden text-sand-500" type="button"
          aria-label="إغلاق القائمة" @click="ui.sidebarOpen = false"
        >
          <BaseIcon name="close" :size="18" />
        </button>
      </div>

      <nav class="flex-1 overflow-y-auto p-3 space-y-0.5" aria-label="التنقل الرئيسي">
        <RouterLink
          v-for="link in links" :key="link.name" :to="{ name: link.name }"
          class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-sand-700 hover:bg-sand-50 transition-colors"
          active-class="!bg-clay-50 !text-clay-700 font-medium"
          @click="ui.sidebarOpen = false"
        >
          <BaseIcon :name="link.meta.icon ?? 'gauge'" :size="18" class="shrink-0" />
          {{ link.meta.title }}
        </RouterLink>
      </nav>

      <div class="p-3 border-t border-sand-200">
        <div class="flex items-center gap-2.5 px-2 py-2 mb-1">
          <span class="size-9 shrink-0 grid place-items-center rounded-full bg-clay-100 text-clay-700 font-semibold">
            {{ auth.user?.name?.charAt(0) ?? '؟' }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-sand-900 truncate">{{ auth.user?.name }}</p>
            <p class="text-xs text-sand-500 truncate">{{ auth.user?.staff_profile?.title ?? auth.roles[0] }}</p>
          </div>
        </div>

        <button class="btn btn-ghost w-full justify-start text-sm" type="button" @click="signOut">
          <BaseIcon name="logout" :size="17" />
          تسجيل الخروج
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

      <main id="main" class="p-4 sm:p-6 max-w-[1400px]">
        <!-- بلا Suspense: لا setup غير متزامن هنا، وشاشات كثيرة جذرها
             v-if/v-else فتصبح أكثر من عقدة جذر وهو ما يشكو منه Suspense -->
        <RouterView />
      </main>
    </div>

    <ToastStack />
  </div>
</template>

import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * الموجّه.
 *
 * `meta.permission` تُقرأ من صلاحيات المستخدم القادمة من الخادم، لا من قائمة
 * مكتوبة في الواجهة. وإخفاء الرابط ليس حماية: الخادم يرفض الطلب على أي حال —
 * هذا لتجنّب عرض شاشة تنتهي بـ 403.
 */
const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true, layout: 'blank' },
  },
  {
    path: '/',
    redirect: '/dashboard',
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { title: 'نظرة عامة', icon: 'gauge' },
  },
  {
    path: '/orphans',
    name: 'orphans',
    component: () => import('@/views/OrphansView.vue'),
    meta: { group: 'cases', title: 'الأيتام', icon: 'children', permission: 'orphans.view' },
  },
  {
    path: '/orphans/:id',
    name: 'orphan-detail',
    component: () => import('@/views/OrphanDetailView.vue'),
    meta: { title: 'ملف يتيم', permission: 'orphans.view', hidden: true },
  },
  {
    path: '/families',
    name: 'families',
    component: () => import('@/views/FamiliesView.vue'),
    meta: { group: 'cases', title: 'الأسر', icon: 'home', permission: 'orphans.view' },
  },
  {
    path: '/sponsors',
    name: 'sponsors',
    component: () => import('@/views/SponsorsView.vue'),
    meta: { group: 'money', title: 'الكفلاء', icon: 'users', permission: 'sponsors.view' },
  },
  {
    path: '/sponsorships',
    name: 'sponsorships',
    component: () => import('@/views/SponsorshipsView.vue'),
    meta: { group: 'money', title: 'الكفالات', icon: 'handshake', permission: 'sponsorships.view' },
  },
  {
    path: '/payments',
    name: 'payments',
    component: () => import('@/views/PaymentsView.vue'),
    meta: { group: 'money', title: 'المدفوعات', icon: 'receipt', permission: 'payments.view' },
  },
  {
    path: '/disbursements',
    name: 'disbursements',
    component: () => import('@/views/DisbursementsView.vue'),
    meta: { group: 'money', title: 'الصرف', icon: 'wallet', permission: 'disbursements.view' },
  },
  {
    path: '/reports',
    name: 'reports',
    component: () => import('@/views/ReportsView.vue'),
    meta: { group: 'cases', title: 'التقارير', icon: 'file', permission: 'reports.view' },
  },
  {
    path: '/documents',
    name: 'documents',
    component: () => import('@/views/DocumentsView.vue'),
    meta: { group: 'cases', title: 'التحقق من المستندات', icon: 'file-check', permission: 'orphans.verify' },
  },
  {
    path: '/visits',
    name: 'visits',
    component: () => import('@/views/VisitsView.vue'),
    meta: { group: 'cases', title: 'الزيارات', icon: 'map', permission: 'visits.view' },
  },
  {
    path: '/messages',
    name: 'messages',
    component: () => import('@/views/MessagesView.vue'),
    meta: { group: 'comms', title: 'الرسائل', icon: 'chat', permission: 'messages.review' },
  },
  {
    path: '/contacts',
    name: 'contacts',
    component: () => import('@/views/ContactsView.vue'),
    meta: { group: 'comms', title: 'رسائل التواصل', icon: 'mail', permission: 'content.manage' },
  },
  {
    path: '/users',
    name: 'users',
    component: () => import('@/views/UsersView.vue'),
    meta: { group: 'system', title: 'المستخدمون', icon: 'shield', permission: 'users.manage' },
  },
  {
    path: '/content',
    name: 'content',
    component: () => import('@/views/ContentView.vue'),
    meta: { group: 'comms', title: 'محتوى الموقع', icon: 'globe', permission: 'content.manage' },
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('@/views/SettingsView.vue'),
    meta: { group: 'system', title: 'الإعدادات', icon: 'sliders', permission: 'settings.manage' },
  },
  {
    path: '/activity',
    name: 'activity',
    component: () => import('@/views/ActivityView.vue'),
    meta: { group: 'system', title: 'سجل النشاط', icon: 'history', permission: 'activity.view' },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { hidden: true },
  },
]

/**
 * جذر اللوحة في شريط العنوان.
 *
 * ليس `import.meta.env.BASE_URL` عمداً: ذاك يتبع **مكان الأصول**
 * (‏`/admin-app/`) لا **مكان التطبيق** (‏`/admin`)، والاثنان مختلفان قصداً —
 * انظر `vite.config.js`. واستعماله هنا كان يحوّل الزائر إلى
 * `/admin-app/login` فيصطدم بمجلد أصول لا تطبيق.
 */
const ROUTER_BASE = import.meta.env.DEV ? '/' : '/admin/'

const router = createRouter({
  history: createWebHistory(ROUTER_BASE),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // استعادة الجلسة مرة واحدة عند أول تنقّل، لا عند كل واحد
  if (!auth.checked) {
    await auth.fetchUser()
  }

  if (to.meta.guest) {
    return auth.isAuthenticated ? { name: 'dashboard' } : true
  }

  if (!auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (!auth.isStaff) {
    return { name: 'login' }
  }

  if (to.meta.permission && !auth.can(to.meta.permission)) {
    return { name: 'dashboard' }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | لوحة كفالة` : 'لوحة كفالة'
})

export default router

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
    meta: { title: 'الأيتام', icon: 'children', permission: 'orphans.view' },
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
    meta: { title: 'الأسر', icon: 'home', permission: 'orphans.view' },
  },
  {
    path: '/sponsors',
    name: 'sponsors',
    component: () => import('@/views/SponsorsView.vue'),
    meta: { title: 'الكفلاء', icon: 'users', permission: 'sponsors.view' },
  },
  {
    path: '/sponsorships',
    name: 'sponsorships',
    component: () => import('@/views/SponsorshipsView.vue'),
    meta: { title: 'الكفالات', icon: 'handshake', permission: 'sponsorships.view' },
  },
  {
    path: '/payments',
    name: 'payments',
    component: () => import('@/views/PaymentsView.vue'),
    meta: { title: 'المدفوعات', icon: 'receipt', permission: 'payments.view' },
  },
  {
    path: '/disbursements',
    name: 'disbursements',
    component: () => import('@/views/DisbursementsView.vue'),
    meta: { title: 'الصرف', icon: 'wallet', permission: 'disbursements.view' },
  },
  {
    path: '/reports',
    name: 'reports',
    component: () => import('@/views/ReportsView.vue'),
    meta: { title: 'التقارير', icon: 'file', permission: 'reports.view' },
  },
  {
    path: '/visits',
    name: 'visits',
    component: () => import('@/views/VisitsView.vue'),
    meta: { title: 'الزيارات', icon: 'map', permission: 'visits.view' },
  },
  {
    path: '/messages',
    name: 'messages',
    component: () => import('@/views/MessagesView.vue'),
    meta: { title: 'الرسائل', icon: 'chat', permission: 'messages.review' },
  },
  {
    path: '/users',
    name: 'users',
    component: () => import('@/views/UsersView.vue'),
    meta: { title: 'المستخدمون', icon: 'shield', permission: 'users.manage' },
  },
  {
    path: '/activity',
    name: 'activity',
    component: () => import('@/views/ActivityView.vue'),
    meta: { title: 'سجل النشاط', icon: 'history', permission: 'activity.view' },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { hidden: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
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
  document.title = to.meta.title ? `${to.meta.title} — لوحة كفالة` : 'لوحة كفالة'
})

export default router

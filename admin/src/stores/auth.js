import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import * as api from '@/api/endpoints'

/**
 * حالة المصادقة.
 *
 * لا نخزّن توكناً ولا نضع شيئاً في localStorage: الجلسة في كوكي HttpOnly
 * لا يراه JS. فمصدر الحقيقة الوحيد عن «هل أنا داخل» هو استدعاء /auth/me.
 * وهذا مقصود — أي حالة نحفظها هنا قد تكذب بعد انتهاء الجلسة على الخادم.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loading = ref(false)
  const checked = ref(false)

  /** الخادم لم يردّ أصلاً (متوقف أو بطيء جداً)، لا أن الجلسة غير موجودة */
  const offline = ref(false)
  let pending = null

  const isAuthenticated = computed(() => user.value !== null)
  const roles = computed(() => user.value?.roles ?? [])
  const permissions = computed(() => user.value?.permissions ?? [])

  const isStaff = computed(() =>
    roles.value.some((r) => ['admin', 'accountant', 'field_supervisor'].includes(r)),
  )
  const isAdmin = computed(() => roles.value.includes('admin'))

  function can(permission) {
    return permissions.value.includes(permission)
  }

  async function login(credentials) {
    loading.value = true
    try {
      const { data } = await api.auth.login(credentials)
      user.value = data.data
      checked.value = true
      return data
    } finally {
      loading.value = false
    }
  }

  /** يُستدعى مرة عند الإقلاع: يستعيد الجلسة إن كان الكوكي ما يزال صالحاً. */
  /**
   * استعادة الجلسة. يُستدعى من الموجّه ومن `App.vue` معاً عند الفتح، فيتشاركان
   * طلباً واحداً بدل طلبين على خادم يعالج طلباً واحداً في المرة.
   */
  function fetchUser() {
    if (checked.value) return Promise.resolve(user.value)

    pending ??= api.auth.me()
      .then(({ data }) => { user.value = data.data; offline.value = false })
      .catch((e) => {
        user.value = null            // 401 هنا حالة طبيعية لا خطأ
        offline.value = !e.response  // بلا استجابة: الخادم لا يردّ
      })
      .finally(() => { checked.value = true; pending = null })
      .then(() => user.value)

    return pending
  }

  async function logout() {
    try {
      await api.auth.logout()
    } finally {
      user.value = null
      checked.value = true
    }
  }

  return {
    user, loading, checked, offline,
    isAuthenticated, isStaff, isAdmin, roles, permissions,
    can, login, fetchUser, logout,
  }
})

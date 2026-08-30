<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BaseIcon from '@/components/BaseIcon.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = ref({ email: '', password: '', remember: false })
const errors = ref({})
const message = ref('')
const showPassword = ref(false)

async function submit() {
  errors.value = {}
  message.value = ''

  try {
    await auth.login(form.value)
    router.push(route.query.redirect ?? { name: 'dashboard' })
  } catch (e) {
    errors.value = e.fieldErrors ?? {}
    message.value = Object.keys(errors.value).length ? '' : (e.userMessage ?? 'تعذّر تسجيل الدخول.')
  }
}
</script>

<template>
  <div class="min-h-screen grid lg:grid-cols-2">
    <!-- النموذج -->
    <div class="flex items-center justify-center p-6">
      <div class="w-full max-w-sm">
        <div class="flex items-center gap-2.5 mb-8">
          <img src="/logo.svg" alt="" width="44" height="44" />
          <div>
            <p class="font-semibold text-lg leading-tight">كفالة</p>
            <p class="text-xs text-sand-500">لوحة الإدارة</p>
          </div>
        </div>

        <h1 class="text-2xl mb-1">أهلاً بعودتك</h1>
        <p class="text-sm text-sand-600 mb-6">ادخل لمتابعة الحالات والكفالات والتقارير.</p>

        <form novalidate @submit.prevent="submit">
          <div class="mb-4">
            <label class="block text-sm font-medium mb-1.5" for="email">البريد الإلكتروني</label>
            <input
              id="email" v-model="form.email" type="email" class="input"
              autocomplete="email" inputmode="email" required
              :aria-invalid="!!errors.email" aria-describedby="err-email"
            />
            <!-- role=alert: قارئ الشاشة يعلن الخطأ فور ظهوره -->
            <p v-if="errors.email" id="err-email" class="text-xs text-rose-700 mt-1.5" role="alert">
              {{ errors.email[0] }}
            </p>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium mb-1.5" for="password">كلمة المرور</label>
            <div class="relative">
              <input
                id="password" v-model="form.password"
                :type="showPassword ? 'text' : 'password'" class="input pe-10"
                autocomplete="current-password" required
                :aria-invalid="!!errors.password" aria-describedby="err-password"
              />
              <button
                type="button"
                class="absolute top-1/2 -translate-y-1/2 text-sand-400 hover:text-sand-700"
                style="inset-inline-end: 0.75rem"
                :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                @click="showPassword = !showPassword"
              >
                <BaseIcon name="eye" :size="17" />
              </button>
            </div>
            <p v-if="errors.password" id="err-password" class="text-xs text-rose-700 mt-1.5" role="alert">
              {{ errors.password[0] }}
            </p>
          </div>

          <label class="flex items-center gap-2 text-sm mb-5 cursor-pointer">
            <input v-model="form.remember" type="checkbox" class="accent-clay-600" />
            تذكّرني
          </label>

          <p v-if="message" class="text-sm text-rose-700 mb-3" role="alert">{{ message }}</p>

          <button class="btn btn-primary w-full justify-center" type="submit" :disabled="auth.loading">
            {{ auth.loading ? 'جارٍ التحقق…' : 'دخول' }}
          </button>
        </form>

        <div class="mt-8 p-3 rounded-xl bg-sand-100 text-xs text-sand-600 leading-relaxed">
          <p class="font-medium text-sand-700 mb-1">حسابات تجريبية — كلمة المرور <span class="num">password</span></p>
          <p class="num" dir="ltr">admin@kafala.org · accountant@kafala.org · supervisor0@kafala.org</p>
        </div>
      </div>
    </div>

    <!-- اللوح الجانبي -->
    <div class="hidden lg:flex flex-col justify-end p-10 bg-clay-700 text-clay-50 relative overflow-hidden">
      <div
        class="absolute inset-0 opacity-20"
        style="background: radial-gradient(circle at 30% 20%, #f0910c 0%, transparent 55%)"
        aria-hidden="true"
      />
      <blockquote class="relative max-w-md">
        <p class="text-xl leading-relaxed mb-4">
          «لا تبرّع مجهول المصير، بل طفلٌ باسمه وقصته وملفه الموثّق،
          وتقرير مصوّر كل ثلاثة أشهر، وميزانية مدقّقة منشورة للجميع.»
        </p>
        <footer class="text-sm text-clay-200">المبدأ الذي بُنيت عليه المنصة</footer>
      </blockquote>
    </div>
  </div>
</template>

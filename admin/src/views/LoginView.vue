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

/**
 * حساب الكافل يُرفض هنا برسالة لا بإعادة صامتة إلى الصفحة نفسها.
 * صفحة دخول الكفلاء على الموقع: نفس الأصل في الإنتاج، وخادم Laravel في التطوير.
 */
const sponsorAccount = ref(false)
const siteLogin = import.meta.env.DEV ? 'http://127.0.0.1:8000/login.html' : '/login.html'

/** الحسابات التجريبية تظهر في التطوير وحده، ولا تصل إلى النسخة المنشورة أبداً. */
const isDev = import.meta.env.DEV
const demoAccounts = [
  { email: 'admin@kafala.org', role: 'مدير' },
  { email: 'accountant@kafala.org', role: 'محاسب' },
  { email: 'supervisor0@kafala.org', role: 'مشرف ميداني' },
]

function useDemo(email) {
  form.value.email = email
  form.value.password = 'password'
}

/** عنوان الموقع العام للرابط أعلى الصفحة. */
const siteHome = import.meta.env.DEV ? 'http://127.0.0.1:8000/' : '/'

async function submit() {
  errors.value = {}
  message.value = ''
  sponsorAccount.value = false

  try {
    await auth.login(form.value)

    // الدخول نقطة واحدة لكل الحسابات؛ هذه اللوحة لفريق العمل وحده
    if (!auth.isStaff) {
      await auth.logout()
      sponsorAccount.value = true
      return
    }

    router.push(route.query.redirect ?? { name: 'dashboard' })
  } catch (e) {
    errors.value = e.fieldErrors ?? {}
    message.value = Object.keys(errors.value).length ? '' : (e.userMessage ?? 'تعذّر تسجيل الدخول.')
  }
}
</script>

<template>
  <!--
    صفحة دخول الإدارة مختلفة عمداً عن دخول الكافل: هناك صفحة منقسمة فيها
    تسويق، وهنا شاشة واحدة مركّزة على الحبر الداكن، كأدوات الإدارة المهنية.
  -->
  <div class="admin-login">
    <header class="admin-login__top">
      <span v-if="isDev" class="admin-login__env">
        <span class="admin-login__dot" aria-hidden="true" />
        بيئة التطوير
      </span>
      <a :href="siteHome" class="admin-login__site">
        موقع كفالة
        <BaseIcon name="external" :size="14" />
      </a>
    </header>

    <!-- زخرفة الخلفية: حلقتان رفيعتان ونقاط ذهبية، ثابتة ولا تُقرأ -->
    <div class="admin-login__deco" aria-hidden="true">
      <span class="admin-login__ring admin-login__ring--a" />
      <span class="admin-login__ring admin-login__ring--b" />
      <span class="admin-login__dots" />
    </div>

    <main class="admin-login__main">
      <div class="admin-login__brand">
        <span class="admin-login__mark"><img src="/logo.svg" alt="" width="34" height="34" /></span>
        <div>
          <p class="font-display font-semibold text-xl text-sand-900 leading-tight">كفالة</p>
          <p class="text-sm text-clay-600 leading-tight">لوحة الإدارة</p>
        </div>
      </div>

      <section class="admin-login__card" aria-labelledby="loginTitle">
        <h1 id="loginTitle" class="text-2xl mb-1">تسجيل الدخول</h1>
        <p class="text-sm text-sand-500 mb-7">لفريق العمل فقط: المدير والمحاسب والمشرف الميداني.</p>

        <form novalidate class="flex flex-col gap-5" @submit.prevent="submit">
          <div>
            <label class="block text-sm font-medium mb-1.5" for="email">البريد الإلكتروني</label>
            <input
              id="email" v-model="form.email" type="email" class="input admin-login__input"
              autocomplete="email" inputmode="email" required
              :aria-invalid="!!errors.email" aria-describedby="err-email"
            />
            <!-- role=alert: قارئ الشاشة يعلن الخطأ فور ظهوره -->
            <p v-if="errors.email" id="err-email" class="text-xs text-rose-700 mt-1.5" role="alert">
              {{ errors.email[0] }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1.5" for="password">كلمة المرور</label>
            <div class="relative">
              <input
                id="password" v-model="form.password"
                :type="showPassword ? 'text' : 'password'" class="input admin-login__input pe-11"
                autocomplete="current-password" required
                :aria-invalid="!!errors.password" aria-describedby="err-password"
              />
              <button
                type="button"
                class="absolute top-1/2 -translate-y-1/2 text-sand-400 hover:text-sand-700"
                style="inset-inline-end: 0.85rem"
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

          <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.remember" type="checkbox" class="accent-clay-600 size-4" />
              تذكّرني
            </label>
            <span class="text-sand-500">نسيت كلمة المرور؟ راجع مدير النظام</span>
          </div>

          <p v-if="message" class="text-sm text-rose-700" role="alert">{{ message }}</p>
          <p v-else-if="auth.offline" class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2" role="status">
            لا اتصال بالخادم الآن. تأكد من تشغيله ثم أعد المحاولة.
          </p>
          <p v-if="sponsorAccount" class="text-sm text-rose-700" role="alert">
            هذا حساب كافل، والدخول به من موقع كفالة.
            <a :href="siteLogin" class="font-semibold underline">افتح صفحة دخول الكفلاء</a>
          </p>

          <button class="btn btn-primary admin-login__submit" type="submit" :disabled="auth.loading">
            <BaseIcon v-if="auth.loading" name="loader" :size="16" class="animate-spin" />
            {{ auth.loading ? 'جارٍ التحقق…' : 'دخول' }}
          </button>
        </form>

        <!-- التطوير وحده: حسابات تجريبية تملأ النموذج بنقرة -->
        <div v-if="isDev" class="admin-login__demo">
          <p class="text-xs text-sand-500 mb-2">دخول سريع للتجربة (كلمة المرور <span class="num">password</span>)</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="a in demoAccounts" :key="a.email" type="button"
              class="admin-login__chip" :title="a.email" @click="useDemo(a.email)"
            >
              {{ a.role }}
            </button>
          </div>
        </div>
      </section>

      <p class="admin-login__note">
        <BaseIcon name="shield" :size="15" />
        كل دخول وإجراء يُسجَّل في سجل النشاط، والجلسة تنتهي بعد ساعتين بلا نشاط.
      </p>
    </main>
  </div>
</template>

<style scoped>
/*
 * خلفية فاتحة هادئة: أرضية رملية عليها شبكة خطوط شعرية كورق هندسي،
 * وحلقتان تركوازيتان رفيعتان تدخلان من الزاويتين، ونقاط ذهبية قليلة.
 * كلها ألوان مسطحة بشفافية خفيفة، بلا تدرّج لوني.
 */
.admin-login {
  position: relative;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  isolation: isolate;
  background-color: var(--color-sand-50);
  background-image:
    linear-gradient(rgb(15 92 92 / 0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgb(15 92 92 / 0.06) 1px, transparent 1px);
  background-size: 40px 40px;
  color: var(--color-sand-900);
}

.admin-login__deco {
  position: absolute;
  inset: 0;
  z-index: -1;
  pointer-events: none;
}

.admin-login__ring {
  position: absolute;
  border-radius: 50%;
  border: 1px solid rgb(15 92 92 / 0.18);
}

/* حلقة مزدوجة: إطار ثانٍ أوسع بالمركز نفسه */
.admin-login__ring::after {
  content: "";
  position: absolute;
  inset: -3.5rem;
  border-radius: 50%;
  border: 1px solid rgb(15 92 92 / 0.1);
}

.admin-login__ring--a {
  width: 34rem;
  height: 34rem;
  inset-block-start: -14rem;
  inset-inline-end: -10rem;
}

.admin-login__ring--b {
  width: 26rem;
  height: 26rem;
  inset-block-end: -12rem;
  inset-inline-start: -8rem;
}

.admin-login__dots {
  position: absolute;
  width: 6.5rem;
  height: 4.5rem;
  inset-block-start: 22%;
  inset-inline-start: 12%;
  background-image: radial-gradient(var(--color-amber-500) 1.6px, transparent 2px);
  background-size: 14px 14px;
  opacity: 0.55;
}

.admin-login__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  font-size: 0.85rem;
}

.admin-login__env {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.3rem 0.75rem;
  border: 1px solid var(--color-sand-200);
  border-radius: 99px;
  background: var(--color-sand-0);
  color: var(--color-sand-700);
}

.admin-login__dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 50%;
  background: var(--color-amber-500);
}

.admin-login__site {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin-inline-start: auto;
  color: var(--color-sand-600);
}

.admin-login__site:hover { color: var(--color-clay-600); }

.admin-login__main {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1.75rem;
  padding: 1rem 1.25rem 3rem;
}

.admin-login__brand {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.admin-login__mark {
  display: grid;
  place-items: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 0.9rem;
  background: var(--color-sand-0);
  border: 1px solid var(--color-sand-200);
}

.admin-login__card {
  width: 100%;
  max-width: 26rem;
  padding: 2rem;
  border-radius: 1rem;
  background: var(--color-sand-0);
  color: var(--color-sand-900);
  border: 1px solid var(--color-sand-200);
  border-block-start: 3px solid var(--color-clay-600);
  box-shadow: 0 24px 48px -28px rgb(31 42 46 / 0.25);
}

.admin-login__input {
  padding-block: 0.7rem;
  border-radius: 0.65rem;
  font-size: 0.95rem;
}

.admin-login__submit {
  justify-content: center;
  padding-block: 0.75rem;
  border-radius: 0.65rem;
  font-size: 1rem;
  font-weight: 600;
}

.admin-login__demo {
  margin-top: 1.5rem;
  padding-top: 1.25rem;
  border-top: 1px dashed var(--color-sand-300);
}

.admin-login__chip {
  padding: 0.35rem 0.85rem;
  border: 1px solid var(--color-sand-200);
  border-radius: 99px;
  font-size: 0.8rem;
  color: var(--color-sand-700);
  cursor: pointer;
}

.admin-login__chip:hover { border-color: var(--color-clay-400); color: var(--color-clay-600); }

.admin-login__note {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  max-width: 26rem;
  font-size: 0.8rem;
  color: var(--color-sand-500);
}

@media (max-width: 30rem) {
  .admin-login__card { padding: 1.5rem; }
  .admin-login__dots { display: none; }
}
</style>

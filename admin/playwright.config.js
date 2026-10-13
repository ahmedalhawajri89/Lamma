import { defineConfig, devices } from '@playwright/test'

/**
 * اختبارات E2E.
 *
 * تعمل على المتصفح فعلاً مقابل الـ API الحقيقي، لا مقابل بيانات وهمية:
 * الغرض كشف ما لا تكشفه اختبارات الوحدة — كوكي الجلسة عبر الأصول،
 * وحرس الموجّه، ورسم Chart.js على canvas حقيقي.
 *
 * وتعمل على بيئة خاصة بها: خادم لارافل على 8001 بقاعدة `kafala_e2e` تُبذر
 * من الصفر قبل كل تشغيل (global-setup.js)، ولوحة Vite على 5175 توجّه إليه.
 * فلا تكتب شيئاً في قاعدة التطوير، ولا تتعارض مع خادمَي التطوير القائمين.
 */
const API_PORT = 8001
const UI_PORT = 5175

// عناوين البيئة للاختبارات نفسها (الموقع العام واللوحة)، بدل عناوين التطوير مكتوبةً فيها
process.env.E2E_SITE = `http://127.0.0.1:${API_PORT}`
process.env.E2E_UI = `http://localhost:${UI_PORT}`
export default defineConfig({
  testDir: './tests/e2e',
  globalSetup: './tests/e2e/global-setup.js',
  fullyParallel: false,

  /**
   * عامل واحد.
   *
   * `fullyParallel: false` يُسلسل الاختبارات داخل الملف الواحد فقط؛ والملفات
   * تبقى متوازية على عدة عمّال. وخادم `artisan serve` أحادي الخيط، فملفّان
   * يتسابقان عليه يضاعفان زمن كل طلب حتى تتجاوز المهلات — فتسقط اختبارات
   * تمرّ كلها منفردةً.
   *
   * وإخفاقٌ يعتمد على عدد أنوية الجهاز أسوأ من بطء: يُعلّم الفريق أن السقوط
   * الأحمر قد لا يعني شيئاً، فيُتجاهل يوم يعني شيئاً فعلاً.
   */
  workers: 1,
  retries: 0,
  reporter: [['list']],

  /**
   * مهلات أطول من الافتراضي، والسبب بنيوي لا عارض:
   *
   * `php artisan serve` **أحادي الخيط**. وكل شاشة هنا تُصدر عدة طلبات
   * (ترطيب، ثم مصادقة، ثم بيانات الشاشة)، فتصطفّ خلف بعضها. والحزمة كاملةً
   * تضاعف ذلك لأن الاختبارات تتوالى على الخادم نفسه.
   *
   * وقد جرّبنا رفع المهلة في كل تأكيد على حدة فتناثرت الأرقام السحرية بلا
   * أن تُشفى العلّة. رفعها هنا مرة واحدة أصدق: هي خاصية بيئة التطوير،
   * و php-fpm في الإنتاج يخدم الطلبات متوازيةً فتسقط الحاجة إليها.
   */
  timeout: 60_000,
  expect: { timeout: 15_000 },

  use: {
    baseURL: `http://localhost:${UI_PORT}`,
    locale: 'ar',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  webServer: [
    {
      // لارافل على قاعدة الاختبارات (يقرأ .env.e2e حين APP_ENV=e2e)
      command: `php artisan serve --host=127.0.0.1 --port=${API_PORT}`,
      cwd: '../api',
      env: { APP_ENV: 'e2e' },
      url: `http://127.0.0.1:${API_PORT}/up`,
      reuseExistingServer: false,
      timeout: 60_000,
    },
    {
      command: `npx vite --port ${UI_PORT} --strictPort`,
      env: { KAFALA_API: `http://127.0.0.1:${API_PORT}`, VITE_PORT: String(UI_PORT) },
      url: `http://localhost:${UI_PORT}`,
      reuseExistingServer: false,
      timeout: 60_000,
    },
  ],

})

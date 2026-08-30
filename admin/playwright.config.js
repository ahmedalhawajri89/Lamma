import { defineConfig, devices } from '@playwright/test'

/**
 * اختبارات E2E.
 *
 * تعمل على المتصفح فعلاً مقابل الـ API الحقيقي، لا مقابل بيانات وهمية:
 * الغرض كشف ما لا تكشفه اختبارات الوحدة — كوكي الجلسة عبر الأصول،
 * وحرس الموجّه، ورسم Chart.js على canvas حقيقي.
 *
 * يفترض تشغيل خادم Laravel على 8000 (‏php artisan serve).
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  retries: 0,
  reporter: [['list']],

  use: {
    baseURL: 'http://localhost:5173',
    locale: 'ar',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5173',
    reuseExistingServer: true,
    timeout: 60_000,
  },
})

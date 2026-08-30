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

  /**
   * خادمان: لوحة Vue على 5173، والموقع العام الساكن على 5501.
   * المنفذ 5501 هو نفسه منفذ Live Server في .vscode/settings.json،
   * وهو أيضاً في قائمة أصول CORS — فالتغيير عنه يكسر الجلسة.
   */
  webServer: [
    {
      command: 'npm run dev',
      url: 'http://localhost:5173',
      reuseExistingServer: true,
      timeout: 60_000,
    },
    {
      command: 'npx --yes serve ../web -l 5501 --no-clipboard',
      url: 'http://127.0.0.1:5501/index.html',
      reuseExistingServer: true,
      timeout: 60_000,
    },
  ],
})

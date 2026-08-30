import { test, expect } from '@playwright/test'

/**
 * الموقع العام — يعمل بلا خطوة بناء، فلا يملك اختبارات خاصة به.
 * نغطّيه من هنا لأن مشغّل اختبارات واحداً للمستودع أبسط من ثلاثة.
 *
 * ما يُختبَر هنا هو ما لا يمكن التأكد منه إلا في متصفح حقيقي:
 * أن الصفحة الساكنة تُغذّى من الـ API، وأنها تبقى صحيحة حين يسقط الخادم،
 * وأن جلسة الكافل تعبر من الموقع (‏5501) إلى الـ API (‏8000).
 */

const WEB = 'http://127.0.0.1:5501'
const SPONSOR = { email: 'sponsor@kafala.org', password: 'password' }

test.describe('الموقع العام', () => {
  test('قائمة الأيتام تأتي من قاعدة البيانات لا من الملف', async ({ page }) => {
    await page.goto(`${WEB}/orphans.html`)
    await page.waitForResponse((r) => r.url().includes('/public/orphans') && r.ok())
    await page.waitForTimeout(600)

    // البذرة ثمانية أيتام؛ أكثر من ذلك يعني أن البيانات حيّة
    const cards = await page.locator('#orphansGrid > *').count()
    expect(cards).toBeGreaterThan(8)

    await expect(page.locator('#orphanCount')).not.toHaveText('8')
  })

  test('الموقع يبقى صحيحاً حين يسقط الخادم', async ({ page }) => {
    // هذا ليس احتياطاً نظرياً: الموقع منشور ملفاتٍ ساكنة، وقد يُفتح
    // والـ API متوقف. عندها يجب أن يعرض بياناته المبدئية لا شاشة فارغة.
    await page.route('**/127.0.0.1:8000/**', (route) => route.abort())

    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))

    await page.goto(`${WEB}/orphans.html`)
    await page.waitForTimeout(1500)

    await expect(page.locator('#orphansGrid > *')).toHaveCount(8)
    expect(errors).toEqual([])
  })

  test('حلقة الشفافية تعرض نسباً محسوبة', async ({ page }) => {
    await page.goto(`${WEB}/transparency.html`)
    await page.waitForResponse((r) => r.url().includes('/public/transparency') && r.ok())
    await page.waitForTimeout(800)

    await expect(page.locator('#spendingLegend')).toContainText('مصاريف إدارية')
  })
})

test.describe('بوابة الكافل', () => {
  // رحلة كاملة من خمس خطوات، كل خطوة تنتظر الخادم. المهلة الافتراضية
  // (٣٠ ثانية) تكفيها منفردةً ولا تكفيها والحزمة كلها تعمل على خادم
  // تطوير أحادي الخيط.
  test.describe.configure({ timeout: 90_000 })

  test('لوحة الكافل محروسة، وتُملأ بعد الدخول، ثم تُقفل بالخروج', async ({ page }) => {
    // ١) الحراسة
    await page.goto(`${WEB}/dashboard.html`)
    await page.waitForURL(/\/login/, { timeout: 15_000 })

    // ٢) الدخول من نموذج الموقع نفسه
    await page.fill('#email', SPONSOR.email)
    await page.fill('#password', SPONSOR.password)
    await page.click('form[data-action="login"] [type="submit"]')
    await page.waitForURL(/\/dashboard/, { timeout: 15_000 })

    /**
     * ٣) أرقام حقيقية لا نصوص مكتوبة.
     *
     * مهلة صريحة أطول من الافتراضية: `php artisan serve` أحادي الخيط،
     * فطلبات الصفحة تصطفّ خلف بعضها، وتزداد بطئاً حين تعمل الحزمة كاملة.
     * هذه خاصية خادم التطوير لا عيب في التطبيق — php-fpm يخدمها متوازيةً.
     */
    await expect(page.locator('[data-portal-greeting]')).toContainText('عبدالله', { timeout: 20_000 })
    await expect(page.locator('[data-stat="orphans"]')).not.toHaveText('—', { timeout: 15_000 })
    await expect(page.locator('[data-stat="given"]')).toContainText('$')
    await expect(page.locator('[data-portal-sponsorships] .sponsor-row').first()).toBeVisible()

    // ٤) كشف الحساب — تأكيدٌ ينتظر، فالجدول آخر ما يصل
    const rows = page.locator('[data-portal-payments] tr')
    await expect(rows.nth(1)).toBeVisible({ timeout: 15_000 })
    await expect(page.locator('[data-stat="payments-total"]')).toContainText('$')

    /**
     * ٥) الخروج يُنهي الجلسة على الخادم لا في المتصفح فقط.
     *
     * ننتظر مغادرة اللوحة لا مهلةً ثابتة: زرّ الخروج ينتظر استجابة الخادم
     * ثم ينتقل، ومهلةٌ قصيرة تجعل `goto` التالي يقطع طلب الخروج في الطريق
     * فتبقى الجلسة حيّة ويسقط الاختبار لسبب غير حقيقي.
     */
    await page.click('[data-portal-logout]')
    await page.waitForURL((url) => !url.pathname.includes('dashboard'), { timeout: 20_000 })

    await page.goto(`${WEB}/dashboard.html`)
    await page.waitForURL(/\/login/, { timeout: 20_000 })
  })
})

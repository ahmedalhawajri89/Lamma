import { test, expect } from '@playwright/test'

/**
 * الموقع العام — يعمل بلا خطوة بناء، فلا يملك اختبارات خاصة به.
 * نغطّيه من هنا لأن مشغّل اختبارات واحداً للمستودع أبسط من ثلاثة.
 *
 * ما يُختبَر هنا هو ما لا يمكن التأكد منه إلا في متصفح حقيقي:
 * أن الصفحة الساكنة تُغذّى من الـ API، وأنها تبقى صحيحة حين يسقط الخادم،
 * وأن جلسة الكافل تعبر من الموقع (‏5501) إلى الـ API (‏8000).
 */

// الموقع العام يُقدَّم من خادم لارافل نفسه بعد دمجه في public/
const WEB = 'http://127.0.0.1:8000'
const SPONSOR = { email: 'sponsor@kafala.org', password: 'password' }

test.describe('الموقع العام', () => {
  test('قائمة الأيتام تأتي من قاعدة البيانات لا من الملف', async ({ page }) => {
    await page.goto(`${WEB}/orphans.html`)

    /**
     * تأكيدٌ ينتظر لا `waitForResponse`.
     *
     * تسجيل انتظار الاستجابة **بعد** `goto` سباقٌ خاسر: قد تكون وصلت خلال
     * التنقّل فينتظر حدثاً لن يقع ثانيةً. والتأكيد هنا يعيد المحاولة حتى
     * يتحقق، فلا يهمّ متى وصلت البيانات.
     *
     * البذرة ثمانية أيتام، فأي عدد أكبر يعني أن القائمة حيّة من قاعدة البيانات.
     */
    await expect
      .poll(() => page.locator('#orphansGrid > *').count(), { timeout: 20_000 })
      .toBeGreaterThan(8)

    await expect(page.locator('#orphanCount')).not.toHaveText('8')
  })

  test('الموقع يبقى صحيحاً حين يسقط الخادم', async ({ page }) => {
    // هذا ليس احتياطاً نظرياً: الموقع منشور ملفاتٍ ساكنة، وقد يُفتح
    // والـ API متوقف. عندها يجب أن يعرض بياناته المبدئية لا شاشة فارغة.
    await page.route('**/api/v1/public/**', (route) => route.abort())

    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))

    await page.goto(`${WEB}/orphans.html`)
    await page.waitForTimeout(1500)

    await expect(page.locator('#orphansGrid > *')).toHaveCount(8)
    expect(errors).toEqual([])
  })

  test('حلقة الشفافية تعرض نسباً محسوبة', async ({ page }) => {
    await page.goto(`${WEB}/transparency.html`)

    await expect(page.locator('#spendingLegend')).toContainText('مصاريف إدارية', {
      timeout: 20_000,
    })
  })
})

test.describe('نماذج الموقع', () => {
  test('رسالة التواصل تُرسَل فعلاً وتصل لوحة الإدارة', async ({ page }) => {
    const email = `visitor${Date.now()}@example.com`

    await page.goto(`${WEB}/contact.html`)
    await page.waitForTimeout(1200)

    await page.fill('#contactName', 'زائر اختبار')
    await page.fill('#contactEmail', email)
    await page.fill('#contactMessage', 'سؤال عن بند المصاريف الإدارية وكيف حُسبت نسبته.')
    await page.check('input[name="consent"]')

    const [response] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/public/contact')),
      page.click('form[data-action="contact"] [type="submit"]'),
    ])

    expect(response.status()).toBe(201)

    // ثم نراها من الجهة الأخرى: لوحة الإدارة
    await page.goto('http://localhost:5173/login')
    await page.getByLabel('البريد الإلكتروني').fill('admin@kafala.org')
    await page.getByLabel('كلمة المرور', { exact: true }).fill('password')
    await page.getByRole('button', { name: 'دخول' }).click()
    await page.waitForURL('**/dashboard')

    await page.goto('http://localhost:5173/contacts')
    await expect(page.getByText(email)).toBeVisible({ timeout: 20_000 })
  })

  test('التحقق يمنع الإرسال قبل اكتمال الحقول', async ({ page }) => {
    await page.goto(`${WEB}/contact.html`)
    await page.waitForTimeout(1200)

    let sent = false
    page.on('request', (r) => { if (r.url().includes('/public/contact')) sent = true })

    await page.fill('#contactName', 'ا')
    await page.fill('#contactEmail', 'ليس بريداً')
    await page.click('form[data-action="contact"] [type="submit"]')
    await page.waitForTimeout(1000)

    expect(sent).toBe(false)
    expect(await page.locator('.field.is-invalid').count()).toBeGreaterThan(0)
  })

  test('الاشتراك في النشرة مربوط بنقطته الصحيحة', async ({ page }) => {
    /**
     * ما يخصّ المتصفح هنا هو **الربط**: أن الضغط يُصدر POST إلى النقطة
     * الصحيحة بالحمولة الصحيحة. أما عقد الخادم (‏201، ومنع التكرار،
     * والتأكيد المزدوج، وحدّ السبام) فمُغطّى في Pest حيث الحالة تحت
     * السيطرة — والتأكيد على الحالة هنا يجعل تشغيلين متتاليين من العنوان
     * نفسه يسقطان الاختبار بـ 429، وهو نجاحٌ للحماية لا فشلٌ للميزة.
     */
    const email = `news${Date.now()}@example.com`

    await page.goto(`${WEB}/index.html`)
    await page.waitForTimeout(1600)
    await page.fill('#footerNewsEmail', email)

    const [request] = await Promise.all([
      page.waitForRequest((r) => r.url().includes('/public/newsletter')),
      page.click('form[data-action="newsletter"] [type="submit"]'),
    ])

    expect(request.method()).toBe('POST')
    expect(JSON.parse(request.postData() ?? '{}')).toMatchObject({ email, source: 'footer' })
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

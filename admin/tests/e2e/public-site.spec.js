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
const WEB = process.env.E2E_SITE ?? 'http://127.0.0.1:8000'
const UI = process.env.E2E_UI ?? 'http://localhost:5173'
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
     * والصفحة تعرض اثنتي عشرة بطاقة، والباقي في صفحاتٍ مرقّمة: كانت تطلب أول
     * مئة طفل وتعرضهم كلهم، فمن بعد المئة لا يظهر لأحد.
     */
    await expect(page.locator('#catalogGrid > .kid')).toHaveCount(12, { timeout: 20_000 })
    const total = Number((await page.locator('#catalogCount strong').innerText()).replace(/\D/g, ''))
    expect(total).toBeGreaterThan(8)

    // الصفحة الأخيرة تُفتح، ورابطها في العنوان، وبطاقاتها ما بقي
    const pager = page.locator('#catalogPager')
    const last = Math.ceil(total / 12)
    await pager.locator(`[data-page="${last}"]`).click()
    await expect(page).toHaveURL(new RegExp(`page=${last}`))
    await expect(page.locator('#catalogGrid > .kid')).toHaveCount(total - (last - 1) * 12)

    // فلترٌ بعدده: «بنت (n)» تعرض n، وتعود للصفحة الأولى
    const girls = page.locator('aside [data-filter="gender"][data-value="female"]')
    const n = Number(await girls.locator('.facet__count').innerText())
    await girls.click()
    await expect(page.locator('#catalogCount strong')).toHaveText(String(n))
    await expect(page).not.toHaveURL(/page=/)
  })

  test('الموقع يبقى صحيحاً حين يسقط الخادم', async ({ page }) => {
    // هذا ليس احتياطاً نظرياً: الموقع منشور ملفاتٍ ساكنة، وقد يُفتح
    // والـ API متوقف. عندها يجب أن يعرض بياناته المبدئية لا شاشة فارغة.
    await page.route('**/api/v1/public/**', (route) => route.abort())

    const errors = []
    page.on('pageerror', (e) => errors.push(e.message))

    await page.goto(`${WEB}/orphans.html`)
    await page.waitForTimeout(1500)

    // الفلاتر والصفحات نفسها على البيانات المضمَّنة، لا شاشة خطأ
    await expect(page.locator('#catalogGrid > .kid')).toHaveCount(8)
    await expect(page.locator('#catalogCount strong')).toHaveText('8')
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
    await page.goto(`${UI}/login`)
    await page.getByLabel('البريد الإلكتروني').fill('admin@kafala.org')
    await page.getByLabel('كلمة المرور', { exact: true }).fill('password')
    await page.getByRole('button', { name: 'دخول' }).click()
    await page.waitForURL('**/dashboard')

    await page.goto(`${UI}/contacts?q=${encodeURIComponent(email)}`)
    // الأولى مختارة وحدها، وبريد المرسل في رأسها
    await expect(page.locator('#ctTitle')).toContainText('زائر اختبار', { timeout: 20_000 })
    await expect(page.getByRole('link', { name: email })).toBeVisible()

    // الرد من المنصة نفسها، ثم الإغلاق معه
    await page.getByRole('button', { name: 'ابدأ من قالب' }).click()
    await page.locator('#ctReply').pressSequentially('النسبة منشورة شهرياً في صفحة الشفافية.')
    await page.getByRole('button', { name: 'أرسل وأغلق' }).click()
    await expect(page.getByText('أُرسل الرد وأُغلقت الرسالة.')).toBeVisible({ timeout: 20_000 })
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
    await expect(page.locator('[data-stat="orphans"]')).not.toHaveText('…', { timeout: 15_000 })
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

/**
 * المدونة تُقرأ لا تُعرض وحسب.
 *
 * كانت عناوين البطاقات `href="#"` ولا توجد صفحة مقال — تُنشر المقالات ولا
 * تُفتح. هذا الاختبار يحرس الدائرة كاملة: بطاقة → مقال → نصّ.
 */
test.describe('المدونة وصفحة المقال', () => {
  test('من البطاقة إلى نصّ المقال', async ({ page }) => {
    await page.goto(`${WEB}/blog`)

    const first = page.locator('.post-card__title a').first()
    await expect(first).toBeVisible({ timeout: 15_000 })

    // الرابط حقيقي لا مرساة فارغة
    expect(await first.getAttribute('href')).toMatch(/^post\.html\?slug=.+/)

    const title = (await first.textContent()).trim()
    await first.click()

    // `aria-busy` يسقط حين يكتمل العرض — أدقّ من انتظار محدّد بعينه
    await expect(page.locator('#postDetail')).not.toHaveAttribute('aria-busy', 'true', { timeout: 20_000 })
    await expect(page.locator('h1')).toHaveText(title)

    /**
     * أكثر من فقرة: البذرة كانت تنسخ المقتطف في `body`، فكان «المقال»
     * جملةً واحدة. فقرة واحدة هنا تعني أن العيب عاد.
     */
    expect(await page.locator('.prose p').count()).toBeGreaterThan(2)

    // أرقام هندية في النثر العربي، وصيغة جمع صحيحة
    const meta = await page.locator('.page-hero__inner .cluster').innerText()
    expect(meta).not.toMatch(/[0-9]/)
    expect(meta).not.toMatch(/\bدقيقتان دقائق|١ دقائق|٢ دقائق/)
  })

  test('slug مجهول يعرض رسالة لا صفحة فارغة', async ({ page }) => {
    await page.goto(`${WEB}/post.html?slug=${encodeURIComponent('لا-يوجد-هذا')}`)
    await expect(page.locator('#postDetail h1')).toHaveText('لم نجد هذا المقال', { timeout: 20_000 })
  })

  test('زرّ «تحميل أقدم» يجلب المزيد ثم يختفي', async ({ page }) => {
    await page.goto(`${WEB}/blog`)
    await expect(page.locator('.post-card').first()).toBeVisible({ timeout: 15_000 })

    const button = page.locator('#loadMorePosts')
    const cards = () => page.locator('.post-card').count()

    /**
     * نختبر السلوك لا حالةً بعينها.
     *
     * عدد المقالات يتغيّر: بذرةٌ من ستة، ثم تضيف اختبارات اللوحة إليها.
     * فتأكيدٌ على «الزرّ مخفي» يمرّ اليوم ويسقط غداً بلا أن ينكسر شيء —
     * والعقد الحقيقي أنه يظهر ما بقي شيء، ويجلبه، ثم يختفي.
     *
     * و`[hidden]` وحده لا يكفي هنا: `.btn` تضبط `display` فتهزم قاعدة
     * المتصفح ذات الأولوية الصفرية — والقاعدة الصريحة في base.css هي ما
     * يجعل الاختفاء اختفاءً.
     */
    await page.waitForTimeout(1500)

    while (await button.isVisible()) {
      const before = await cards()
      await button.click()
      await expect.poll(cards, { timeout: 20_000 }).toBeGreaterThan(before)
    }

    await expect(button).toBeHidden()
  })
})

/**
 * دورية الكفالة على صفحة الدفع.
 *
 * كانت أزرار «شهرية / كل ٣ أشهر / سنوية» تُبدّل شكلها ولا شيء غير ذلك:
 * `ui.js` يُطلق `chip:change` ولم يكن أحد يستمع، وسطرا «الكفالة الشهرية»
 * و«الإجمالي الآن» يتقاسمان الخطّاف نفسه. فيختار الزائر «سنوية» ويقرأ سعر
 * شهر واحد — على صفحة الدفع، أسوأ مكان يختلف فيه ما يُعرض عمّا يُقصد.
 */
test.describe('دورية الكفالة', () => {
  test('الإجمالي يتبع الدورية، والسطر الشهري ثابت', async ({ page, request }) => {
    // طفلٌ حقيقي يقبل كفالة: الصفحة بلا طفل لا تعرض نموذج دفع
    const res = await request.get(`${WEB}/api/v1/public/orphans?per_page=100`)
    const child = (await res.json()).data.find((o) => o.funded < 100)
    await page.goto(`${WEB}/checkout.html?id=${child.id}`)

    const monthly = page.locator('[data-checkout="price"]')
    const total = page.locator('[data-checkout="total"]')
    const value = async (el) => Number((await el.textContent()).replace(/[^\d.]/g, ''))

    await expect(monthly).not.toHaveText('…', { timeout: 15_000 })
    const amount = await value(monthly)
    expect(amount).toBeGreaterThan(0)

    // شهرية: الإجمالي = المبلغ الشهري
    expect(await value(total)).toBeCloseTo(amount, 2)

    await page.getByRole('button', { name: 'كل ٣ أشهر' }).click()
    await expect.poll(() => value(total)).toBeCloseTo(amount * 3, 2)
    await expect(page.locator('[data-checkout="cycle"]')).toHaveText('عن ثلاثة أشهر')

    await page.getByRole('button', { name: 'سنوية' }).click()
    await expect.poll(() => value(total)).toBeCloseTo(amount * 12, 2)

    // والسطر الشهري لا يتحرّك: هو ما يُخصم كل شهر لا ما يُدفع الآن
    expect(await value(monthly)).toBeCloseTo(amount, 2)
  })
})

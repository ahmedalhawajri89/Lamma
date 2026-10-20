import { test, expect } from '@playwright/test'

/**
 * الكفالة من الموقع، كما يمرّ بها المتبرع.
 *
 * كانت الصفحة محاكاة: زرّ الدفع لا يصل الخادم، وصفحة الشكر تقول «تمّت
 * الكفالة» لأي رابط. هذه الاختبارات تمرّ بالخادم فعلاً على قاعدة الاختبار.
 */
const SITE = process.env.E2E_SITE ?? 'http://127.0.0.1:8000'

/** طفلٌ يقبل كفالة الآن، من الخادم نفسه */
async function sponsorableChild(request, skip = 0) {
  const res = await request.get(`${SITE}/api/v1/public/orphans?per_page=100`)
  const list = (await res.json()).data.filter((o) => o.funded < 100)
  return list[skip]
}

async function fillGuest(page, email) {
  await page.fill('#donorName', 'متبرع اختبار')
  await page.fill('#donorEmail', email)
}

async function fillCard(page, number) {
  await page.fill('#cardHolder', 'متبرع اختبار')
  await page.fill('#cardNumber', number)
  await page.fill('#cardExpiry', '1230')
  await page.fill('#cardCvc', '123')
  // الإفصاح قبل الكفالة: لا تُقبل بلا موافقة
  await page.check('#disclosure')
}

test.describe('الكفالة من الموقع', () => {
  test('زائر يكفل بالبطاقة سنوياً: حساب جديد، وصفحة شكر من بيانات الخادم', async ({ page, request }) => {
    const child = await sponsorableChild(request, 0)
    await page.goto(`${SITE}/checkout?id=${child.id}`)

    // الطفل نفسه من الخادم، ووضع البوابة التجريبية معلَن
    await expect(page.locator('[data-checkout="id"]').first()).toHaveText(child.id)
    await expect(page.locator('[data-checkout="demo"]')).toBeVisible()

    await page.locator('[data-chips] .chip[data-value="yearly"]').click()
    const monthly = Number(await page.inputValue('#monthlyAmount'))
    await expect(page.locator('[data-checkout="plan"]')).toContainText('اليوم')

    await fillGuest(page, `donor${Date.now()}@example.com`)
    await fillCard(page, '4242 4242 4242 4242')
    await page.locator('#checkoutSubmit').click()

    await page.waitForURL(/thank-you\?ref=KFS-/, { timeout: 25_000 })
    await expect(page.locator('[data-thanks-when="paid"]').first()).toBeVisible()
    await expect(page.locator('[data-thanks="cycle"]').first()).toHaveText('سنوياً')
    // القيمة لا شكلها: formatMoney يكتب المبلغ الصحيح بلا كسور («$780» لا «780.00»)
    await expect.poll(async () => Number((await page.locator('[data-thanks="amount"]').first().innerText()).replace(/[^\d.]/g, '')))
      .toBeCloseTo(monthly * 12, 2)
    await expect(page.locator('[data-thanks="receipt"]').first()).toHaveText(/RCP-/)
    await expect(page.locator('[data-thanks-when="account"]')).toBeVisible()

    // والحساب الجديد داخلٌ يرى كفالته في لوحته
    await page.goto(`${SITE}/dashboard#children`)
    const row = page.locator(`.sponsor-row[data-status="active"]`, { hasText: child.name })
    await expect(row).toBeVisible({ timeout: 20_000 })
    await expect(row).toContainText('سنوياً')

    // استراحة بدل الإنهاء: من نافذة الإنهاء نفسها، ثم استئناف
    await row.locator('[data-cancel-open]').click()
    await page.locator('[data-cancel-pause]').click()
    const paused = page.locator('.sponsor-row[data-status="paused"]', { hasText: child.name })
    await expect(paused).toBeVisible({ timeout: 20_000 })
    await expect(paused).toContainText('موقوفة مؤقتاً')

    await paused.locator('[data-resume]').click()
    await expect(page.locator('.sponsor-row[data-status="active"]', { hasText: child.name })).toBeVisible({ timeout: 20_000 })
  })

  test('رفض البطاقة يُقال فوق الزر، ولا تُحجز الكفالة', async ({ page, request }) => {
    const child = await sponsorableChild(request, 1)
    await page.goto(`${SITE}/checkout?id=${child.id}`)
    await expect(page.locator('#monthlyAmount')).not.toHaveValue('')

    await fillGuest(page, `declined${Date.now()}@example.com`)
    await fillCard(page, '4000 0000 0000 0002')
    await page.locator('#checkoutSubmit').click()

    await expect(page.locator('[data-checkout="error"]')).toContainText('رفض البنك', { timeout: 20_000 })
    await expect(page).toHaveURL(/\/checkout\?id=/)
  })

  test('التحويل البنكي: لا حقول بطاقة، ثم حجزٌ ببيانات الحساب والمرجع', async ({ page, request }) => {
    const child = await sponsorableChild(request, 2)
    await page.goto(`${SITE}/checkout?id=${child.id}`)
    await expect(page.locator('#monthlyAmount')).not.toHaveValue('')

    // الزر الدائري مخفيّ خلف بطاقته المصمّمة، فيُضغط على البطاقة كما يفعل المستخدم
    await page.locator('label.co-method', { has: page.locator('input[value="bank_transfer"]') }).click()
    await expect(page.locator('input[name="method"][value="bank_transfer"]')).toBeChecked()
    await expect(page.locator('[data-checkout="card-fields"]')).toBeHidden()
    await expect(page.locator('[data-checkout="bank-note"]')).toBeVisible()

    await fillGuest(page, `bank${Date.now()}@example.com`)
    // الإفصاح قبل الكفالة إلزامي للبطاقة والتحويل معاً
    await page.check('#disclosure')
    await page.locator('#checkoutSubmit').click()

    await page.waitForURL(/thank-you\?ref=KFS-/, { timeout: 25_000 })
    await expect(page.locator('[data-thanks-when="pending"]').first()).toBeVisible()
    await expect(page.locator('[data-thanks="bank-iban"]')).not.toHaveText('…')

    // في اللوحة: حجزٌ بانتظار التحويل، لا يُعدّ يتيماً مكفولاً، وبياناته في متناوله
    await page.goto(`${SITE}/dashboard#children`)
    const row = page.locator('.sponsor-row[data-status="pending"]', { hasText: child.name })
    await expect(row).toBeVisible({ timeout: 20_000 })
    await expect(row).toContainText('بانتظار تحويلك')
    await expect(row.locator('[data-message-open]')).toHaveCount(0)
    await expect(page.locator('[data-stat="orphans"]')).toHaveText('0')
    await expect(page.locator('[data-portal-subtitle]')).toContainText('بانتظار تحويلك')

    // النافذة تُغلق بعد اكتمال ظهورها، كما ينقر الزائر: Bootstrap يتجاهل
    // الإغلاق أثناء حركة الفتح
    const shown = page.locator('#bankModal').evaluate((el) => new Promise((r) => el.addEventListener('shown.bs.modal', r, { once: true })))
    await row.locator('[data-bank-open]').click()
    await shown
    await expect(page.locator('#bankModal [data-bank="reference"]')).toHaveText(/KFS-/)

    // إلغاء الحجز: بلا عرض استراحة ولا سؤال عن السبب
    await page.locator('#bankModal [data-bs-dismiss="modal"]').click()
    await expect(page.locator('#bankModal')).toBeHidden()
    await row.locator('[data-cancel-open]').click()
    await expect(page.locator('[data-cancel-pause]')).toBeHidden()
    await page.locator('[data-cancel-submit]').click()
    await expect(page.locator('[data-portal-sponsorships]')).toContainText('لا كفالات قائمة', { timeout: 20_000 })
  })

  test('رابط بلا طفل، وصفحة شكر بلا كفالة، لا يدّعيان شيئاً', async ({ page }) => {
    // الخادم يرسم الصفحة: طفلٌ مجهول يعني 404 برسالةٍ ورابطٍ إلى الأطفال، لا نموذج
    const res = await page.goto(`${SITE}/checkout?id=KFL-0000`)
    expect(res.status()).toBe(404)
    await expect(page.locator('.co-blocked')).toBeVisible()
    await expect(page.locator('#checkoutForm')).toHaveCount(0)

    await page.goto(`${SITE}/thank-you?ref=KFS-2026-99999`)
    await expect(page.locator('#thanksNone')).toBeVisible()
    await expect(page.locator('#thanksCard')).toBeHidden()
  })
})

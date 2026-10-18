import { test, expect } from '@playwright/test'

/**
 * شاشة الأيتام وملف الطفل.
 *
 * الحالة صارت تبويبات بأعدادها، والقائمة تُصدَّر بفلاترها، والملف يقول
 * أين هو من مساره وما خطوته التالية، وله سجلّ بمن غيّر ماذا.
 */
const ADMIN = { email: 'admin@lamma.org', password: 'password' }

async function login(page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(ADMIN.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(ADMIN.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

test.describe('الأيتام', () => {
  test('تبويب الحالة يفلتر ويُكتب في الرابط، والتصدير ينزّل CSV', async ({ page }) => {
    await login(page)
    await page.goto('/orphans')

    const tab = page.getByRole('button', { name: /بانتظار التوثيق/ })
    await expect(tab).toBeVisible({ timeout: 20_000 })
    await tab.click()
    await expect(page).toHaveURL(/status=pending_verification/)
    await expect(tab).toHaveAttribute('aria-pressed', 'true')

    // كل صف بعد التحميل الجديد بالحالة نفسها (لا صفوف التحميل السابق)
    await expect.poll(async () => {
      const badges = await page.locator('table tbody tr', { hasText: /KFL-\d+/ }).locator('td:last-child').allTextContents()
      return badges.length > 0 && badges.every((t) => t.includes('بانتظار التوثيق'))
    }, { timeout: 20_000 }).toBe(true)

    // الفلتر يظهر معه «مسح الفلاتر»، ويختفي بعد المسح
    await page.getByRole('button', { name: 'مسح الفلاتر' }).first().click()
    await expect(page).not.toHaveURL(/status=/)

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'تصدير' }).click(),
    ])
    expect(download.suggestedFilename()).toMatch(/^orphans-.*\.csv$/)
  })

  test('الملف يعرض مساره، والتعديل يظهر في سجلّه', async ({ page }) => {
    await login(page)
    await page.goto('/orphans?status=active')

    const link = page.locator('table tbody tr a[href^="/orphans/"]').first()
    await expect(link).toBeVisible({ timeout: 20_000 })
    await link.click()
    await page.waitForURL(/\/orphans\/\d+/)

    // المسار: خمس خطوات، والملف النشط تجاوز النشر
    const steps = page.locator('[aria-label="مسار الملف"] li')
    await expect(steps).toHaveCount(5, { timeout: 20_000 })
    await expect(page.locator('[aria-current="step"]')).toContainText('الكفالة')

    // تعديلٌ مسجَّل: حالة «عاجل»
    await page.getByRole('button', { name: 'تحرير' }).click()
    await page.getByLabel('حالة عاجلة تُعرض في مقدمة الموقع').click()
    await page.getByRole('button', { name: 'حفظ', exact: true }).click()
    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 20_000 })

    await page.getByRole('button', { name: 'السجل' }).click()
    await expect(page).toHaveURL(/tab=history/)
    await expect(page.locator('[data-history] > li').first()).toContainText('أحمد', { timeout: 20_000 })
  })
})

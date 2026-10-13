import { test, expect } from '@playwright/test'

/**
 * التقارير: تغطية الربع، وطابور يُراجَع تقريراً بعد تقرير.
 */
const ADMIN = { email: 'admin@kafala.org', password: 'password' }

async function login(page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(ADMIN.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(ADMIN.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

test.describe('التقارير', () => {
  test('شريط التغطية يقيس الربع، و«لم يُكتب» يفتح الأيتام الناقصين', async ({ page }) => {
    await login(page)
    await page.goto('/reports')

    const coverage = page.locator('section[aria-labelledby="coverageTitle"]')
    await expect(coverage).toContainText('تغطية الربع', { timeout: 20_000 })

    const missing = coverage.getByRole('link', { name: /لم يُكتب/ })
    // البذر يترك أطفالاً بلا تقرير للربع الحالي؛ إن لم يكن، فالشريط يقول ذلك ولا رابط
    if (await missing.count()) {
      const expected = Number((await missing.innerText()).replace(/\D/g, ''))
      await missing.click()
      await expect(page).toHaveURL(/\/orphans\?missing_report=\d{4}-[1-4]/)
      await expect(page.getByRole('button', { name: /بلا تقرير للربع/ })).toBeVisible()
      // رأس شاشة الأيتام يقول العدد نفسه
      await expect(page.getByText(new RegExp(`^${expected} حالة`))).toBeVisible({ timeout: 20_000 })
    }
  })

  test('بعد الاعتماد ينتقل الدرج إلى التقرير التالي في الطابور', async ({ page }) => {
    await login(page)
    await page.goto('/reports?status=submitted')

    const rows = page.locator('table tbody tr').filter({ hasText: /KFL-/ })
    await expect(rows.nth(1)).toBeVisible({ timeout: 20_000 })
    await rows.first().click()

    await expect(page).toHaveURL(/review=\d+/)
    const first = new URL(page.url()).searchParams.get('review')

    await page.getByRole('dialog').getByRole('button', { name: 'اعتماد' }).click()
    await expect.poll(() => new URL(page.url()).searchParams.get('review'), { timeout: 10_000 }).not.toBe(first)
    await expect(page.getByRole('dialog').getByRole('heading', { name: 'الملخص' })).toBeVisible()
  })
})

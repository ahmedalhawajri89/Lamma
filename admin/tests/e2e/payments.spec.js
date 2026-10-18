import { test, expect } from '@playwright/test'

/**
 * المدفوعات: ملخّص الفترة، و«فشلت ولم تُستدرك» من الملخّص نفسه، والتصدير.
 */
const ADMIN = { email: 'admin@lamma.org', password: 'password' }

async function login(page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(ADMIN.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(ADMIN.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

test('الملخّص يقود إلى ما فشل ولم يُستدرك، والتصدير ينزّل CSV', async ({ page }) => {
  await login(page)
  await page.goto('/payments')

  const summary = page.locator('section[aria-label="ملخّص الفترة"]')
  await expect(summary).toContainText('المحصّل', { timeout: 20_000 })
  await expect(summary).toContainText('نسبة نجاح الدفع')

  // الفترة الجاهزة تكتب في الرابط
  await page.getByLabel('الفترة', { exact: true }).selectOption('year')
  await expect(page).toHaveURL(/from=\d{4}-01-01/)

  const unrecovered = summary.getByRole('button', { name: /لم تُستدرك بعد/ })
  if (await unrecovered.count()) {
    await unrecovered.click()
    await expect(page).toHaveURL(/unrecovered=1/)
    const statuses = page.locator('table tbody tr', { hasText: /PAY-/ }).locator('td:last-child')
    await expect.poll(async () => {
      const all = await statuses.allTextContents()
      return all.length > 0 && all.every((t) => t.includes('فاشلة'))
    }, { timeout: 20_000 }).toBe(true)
  }

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('button', { name: 'تصدير' }).click(),
  ])
  expect(download.suggestedFilename()).toMatch(/^payments-.*\.csv$/)
})

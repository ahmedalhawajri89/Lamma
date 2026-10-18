import { test, expect } from '@playwright/test'

/**
 * الكفلاء: المراحل تبويباتٌ تُكتب في الرابط، وصفحة الكافل تجمع ما يحتاجه
 * من يتواصل معه، وملاحظة الإدارة تُحفظ وتبقى.
 */
const ADMIN = { email: 'admin@lamma.org', password: 'password' }

async function login(page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(ADMIN.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(ADMIN.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

test('مرحلة «نشط» تفلتر، وصفحة الكافل تحفظ ملاحظة الإدارة', async ({ page }) => {
  await login(page)
  await page.goto('/sponsors')

  const tab = page.getByRole('button', { name: /^نشط/ })
  await expect(tab).toBeVisible({ timeout: 20_000 })
  await tab.click()
  await expect(page).toHaveURL(/segment=active/)

  // كل صف في «نشط» له كفالة نشطة واحدة على الأقل
  const first = page.locator('table tbody tr', { hasText: /SPN-/ }).first()
  await expect(first).toBeVisible({ timeout: 20_000 })
  await expect(first).toContainText(/[1-9]\d* نشطة من/)

  await first.locator('a[href^="/sponsors/"]').click()
  await page.waitForURL(/\/sponsors\/\d+/)
  await expect(page.getByRole('heading', { name: 'الكفالات' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'آخر الدفعات' })).toBeVisible()

  const note = `اتصلت به ${Date.now()}`
  await page.getByLabel('ملاحظات الإدارة').fill(note)
  await page.getByRole('button', { name: 'حفظ الملاحظات' }).click()
  await expect(page.getByText('حُفظت الملاحظات.')).toBeVisible({ timeout: 20_000 })

  await page.reload()
  await expect(page.getByLabel('ملاحظات الإدارة')).toHaveValue(note, { timeout: 20_000 })
})

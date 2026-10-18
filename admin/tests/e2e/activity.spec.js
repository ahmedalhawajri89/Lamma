import { test, expect } from '@playwright/test'

/**
 * سجل النشاط: نظرة عامة يُختار منها يومٌ أو شخص، و«الحساسة» تعرض ما يستحق
 * نظرة ثانية وحده، والسجل مجمَّع بالأيام، والتصدير ينزّل CSV.
 */
const ADMIN = { email: 'admin@lamma.org', password: 'password' }

async function login(page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(ADMIN.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(ADMIN.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

test('النظرة العامة تقود الفلاتر، والحساسة وحدها، والتصدير', async ({ page }) => {
  await login(page)
  await page.goto('/activity')

  const overview = page.locator('section[aria-label="نظرة عامة على النشاط"]')
  await expect(overview).toContainText('الأكثر نشاطاً', { timeout: 20_000 })
  await expect(page.locator('section[aria-label="اليوم"], section[aria-label="أمس"]').first()).toBeVisible()

  // الحساسة: كل سطر معلَّم
  await page.getByRole('button', { name: /الحساسة/ }).click()
  await expect(page).toHaveURL(/sensitive=1/)
  const rows = page.locator('main ol > li, ol.divide-y > li')
  await expect.poll(async () => {
    const n = await rows.count()
    return n > 0 && (await rows.filter({ hasText: 'حساس' }).count()) === n
  }, { timeout: 20_000 }).toBe(true)
  await page.getByRole('button', { name: /الحساسة/ }).click()

  // الأكثر نشاطاً: الضغط على اسم يعرض نشاطه وحده
  const top = overview.locator('ul button').first()
  await top.click()
  await expect(page).toHaveURL(/causer_id=\d+/)
  await top.click()
  await expect(page).not.toHaveURL(/causer_id=/)

  // يومٌ من الإيقاع: من وإلى اليوم نفسه
  await overview.locator('[role=listitem]').last().click()
  await expect(page).toHaveURL(/from=(\d{4}-\d{2}-\d{2}).*to=\1/)

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('button', { name: /تصدير CSV/ }).click(),
  ])
  expect(download.suggestedFilename()).toMatch(/\.csv$/)
})

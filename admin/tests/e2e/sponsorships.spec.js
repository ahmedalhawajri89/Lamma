import { test, expect } from '@playwright/test'

/**
 * الكفالات: لا إلغاء من الصف؛ الكفالة تُفتح في درج بتفاصيلها ودفعاتها،
 * والإيقاف والاستئناف منه، والإنهاء في قسمٍ منفصل.
 */
const ADMIN = { email: 'admin@lamma.org', password: 'password' }

async function login(page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(ADMIN.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(ADMIN.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

test('الكفالة تُفتح في درج، وتُوقف وتُستأنف منه', async ({ page }) => {
  await login(page)
  await page.goto('/sponsorships?status=active')

  const row = page.locator('table tbody tr', { hasText: /KFS-/ }).first()
  await expect(row).toBeVisible({ timeout: 20_000 })
  // لا زرّ إلغاء في الصفوف
  await expect(page.locator('table').getByRole('button', { name: 'إلغاء' })).toHaveCount(0)

  await row.locator('td').nth(2).click()
  await expect(page).toHaveURL(/open=\d+/)
  const drawer = page.getByRole('dialog')
  await expect(drawer.getByRole('heading', { name: /الدفعات/ })).toBeVisible({ timeout: 20_000 })
  await expect(drawer.getByRole('heading', { name: 'إنهاء الكفالة' })).toBeVisible()

  await drawer.getByRole('button', { name: /إيقاف مؤقت/ }).click()
  await expect(drawer.getByText('موقوفة مؤقتاً').first()).toBeVisible({ timeout: 20_000 })

  await drawer.getByRole('button', { name: 'استئناف' }).click()
  await expect(drawer.getByRole('button', { name: /إيقاف مؤقت/ })).toBeVisible({ timeout: 20_000 })

  await page.keyboard.press('Escape')
  await expect(page).not.toHaveURL(/open=/)
})

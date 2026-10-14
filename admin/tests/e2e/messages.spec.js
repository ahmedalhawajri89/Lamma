import { test, expect } from '@playwright/test'

/**
 * مراجعة الرسائل: الرسالة تُعرض في سياقها، والقرار بلوحة المفاتيح ينتقل
 * وحده إلى التالية، والرفض بسببٍ جاهز.
 */
const ADMIN = { email: 'admin@kafala.org', password: 'password' }

async function login(page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(ADMIN.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(ADMIN.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

test('اعتماد بـA ينتقل إلى التالية، والرفض بسببٍ جاهز', async ({ page }) => {
  await login(page)
  await page.goto('/messages')

  // الأولى في الطابور مختارة وحدها، ومعروضة في سياقها
  await expect(page).toHaveURL(/m=\d+/, { timeout: 20_000 })
  await expect(page.getByRole('list', { name: 'سياق المحادثة' })).toBeVisible()
  const first = new URL(page.url()).searchParams.get('m')

  await page.keyboard.press('KeyA')
  await expect(page.getByText('اعتُمدت الرسالة.')).toBeVisible({ timeout: 20_000 })
  await expect.poll(() => new URL(page.url()).searchParams.get('m'), { timeout: 10_000 }).not.toBe(first)

  await page.keyboard.press('KeyR')
  await page.getByRole('button', { name: 'ارفض الرسالة' }).click()
  await expect(page.getByText('اكتب سبب الرفض، يقرؤه المرسل ليعدّل رسالته.')).toBeVisible()
  await page.getByRole('button', { name: 'لا تناسب عمر الطفل' }).click()
  await page.getByRole('button', { name: 'ارفض الرسالة' }).click()
  await expect(page.getByText(/رُفضت الرسالة/)).toBeVisible({ timeout: 20_000 })

  // المرفوضة في تبويبها بسببها
  await page.getByRole('button', { name: /^مرفوضة/ }).click()
  await expect(page.getByText('رُفضت: لا تناسب عمر الطفل').first()).toBeVisible({ timeout: 20_000 })
})

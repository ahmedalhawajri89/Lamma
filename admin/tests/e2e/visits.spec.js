import { test, expect } from '@playwright/test'

/**
 * خطة الزيارات: طفلٌ لم يُزر قط يُزار من صفّه مباشرة، فيخرج من «لم يُزر قط».
 * وسجل الزيارات: الاحتياج المرصود يظهر فلتراً بعدد أطفاله.
 */
const ADMIN = { email: 'admin@kafala.org', password: 'password' }

async function login(page, user) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(user.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(user.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

const count = async (tab) => Number((await tab.innerText()).replace(/\D/g, '') || 0)

test('زيارة من الخطة لطفل لم يُزر قط، واحتياجها في السجل', async ({ page }) => {
  await login(page, ADMIN)
  await page.goto('/visits?bucket=never')

  const tab = page.getByRole('button', { name: /لم يُزر قط/ })
  await expect(tab).toHaveAttribute('aria-pressed', 'true')
  const row = page.locator('table tbody tr', { hasText: /KFL-/ }).first()

  // البذر قد لا يترك طفلاً بلا زيارة؛ حينها لا شيء يُختبر هنا
  // (isVisible لا ينتظر؛ waitFor ينتظر الصفوف الحقيقية بعد التحميل)
  if (!(await row.waitFor({ timeout: 20_000 }).then(() => true).catch(() => false))) test.skip()

  const before = await count(tab)
  const name = (await row.locator('a').first().innerText()).trim()

  await row.getByRole('button', { name: /سجّل زيارة/ }).click()
  const dialog = page.locator('[role=dialog]')
  // الطفل مختار سلفاً من صفّه
  await expect(dialog.getByLabel('الطفل')).toHaveValue(new RegExp(name.split(' ')[0]))

  await dialog.getByLabel('الملاحظات').fill('زيارة أولى للبيت. الأسرة بخير، والطفل ينتظم في المدرسة.')
  await dialog.locator('button', { hasText: 'دعم نفسي' }).click()
  await page.getByRole('button', { name: 'سجّل الزيارة' }).click()
  await expect(dialog).toBeHidden({ timeout: 20_000 })

  await expect.poll(() => count(tab), { timeout: 20_000 }).toBe(before - 1)

  // ── السجل: الاحتياج فلترٌ بعدد أطفاله ──
  await page.getByRole('link', { name: 'السجل' }).click()
  await expect(page).toHaveURL(/\/visits\/log/)
  const need = page.getByRole('button', { name: /دعم نفسي/ })
  await expect(need).toBeVisible({ timeout: 20_000 })
  await need.click()
  await expect(page).toHaveURL(/need=/)
  await expect(page.locator('table tbody tr', { hasText: name.split(' ')[0] }).first()).toBeVisible({ timeout: 20_000 })
})

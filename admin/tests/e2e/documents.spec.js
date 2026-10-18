import { test, expect } from '@playwright/test'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

/**
 * طابور التحقق: مستندٌ يرفعه المشرف، ويراجعه المدير بجانب بيانات الطفل،
 * فيرفضه بسبب يقرؤه المشرف في ملف الطفل.
 */
const ADMIN = { email: 'admin@lamma.org', password: 'password' }
const SUPERVISOR = { email: 'supervisor0@lamma.org', password: 'password' }

async function login(page, user) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(user.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(user.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

function samplePdf() {
  const file = path.join(os.tmpdir(), 'kafala-e2e-reject.pdf')
  fs.writeFileSync(file, '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n')
  return file
}

test('مستند يُراجَع بجانب بيانات الطفل، ويُرفض بسبب يظهر في ملفه', async ({ page }) => {
  // ── المشرف يرفع مستنداً «آخر» لطفل في بلده ──
  await login(page, SUPERVISOR)
  await page.goto('/orphans')
  const link = page.locator('table tbody tr a[href^="/orphans/"]').first()
  await expect(link).toBeVisible({ timeout: 20_000 })
  const name = (await link.innerText()).trim()
  await link.click()
  await page.waitForURL(/\/orphans\/\d+/)
  const fileUrl = page.url().split('?')[0]

  await page.getByRole('button', { name: /^المستندات/ }).click()
  const documents = page.locator('.card', { hasText: 'مستندات التحقق' })
  await documents.getByRole('button', { name: 'رفع' }).click()
  await page.locator('[role=dialog] select').selectOption('other')
  await page.setInputFiles('#documentFile', samplePdf())
  await page.getByRole('button', { name: 'رفع', exact: true }).last().click()
  await expect(documents.getByText('kafala-e2e-reject.pdf').first()).toBeVisible({ timeout: 20_000 })
  await page.getByRole('button', { name: 'تسجيل الخروج' }).click()
  await page.waitForURL('**/login')

  // ── المدير يراجع: المستند معروض، وبيانات الطفل فوقه ──
  await login(page, ADMIN)
  await page.goto('/documents?type=other')
  const group = page.locator('aside section', { hasText: name })
  await expect(group).toBeVisible({ timeout: 20_000 })
  await group.locator('li button').first().click()

  await expect(page.locator('#reviewTitle')).toContainText(name)

  // لا عرض تلقائي، ولا تحقّق قبل فتح المستند
  await expect(page.locator('iframe')).toHaveCount(0)
  await expect(page.getByRole('button', { name: /تحقّقت منه/ })).toBeDisabled()

  // العرض بملء الشاشة بطلب (O)، وبجانبه بيانات الطفل
  await page.keyboard.press('KeyO')
  const viewer = page.locator('[data-viewer]')
  await expect(viewer.locator('iframe[src^="blob:"]')).toBeVisible({ timeout: 20_000 })
  await expect(viewer).toContainText(name)
  await expect(viewer.getByRole('button', { name: /تحقّقت منه/ })).toBeEnabled()

  // الرفض بلوحة المفاتيح، والسبب إلزامي، ثم سبب جاهز بنقرة
  await page.keyboard.press('KeyR')
  await page.getByRole('button', { name: 'ارفض المستند' }).click()
  await expect(page.getByText('اكتب سبب الرفض، يقرؤه من رفع المستند.')).toBeVisible()
  await page.getByRole('button', { name: 'المستند لا يخص هذا الطفل' }).click()
  await page.getByRole('button', { name: 'ارفض المستند' }).click()
  await expect(page.getByText(/والسبب في ملفه/)).toBeVisible({ timeout: 20_000 })
  // القرار يغلق العرض: التالي يُفتح بطلب
  await expect(viewer).toHaveCount(0)

  // ── في ملف الطفل: مرفوض بسببه ──
  await page.goto(`${fileUrl}?tab=documents`)
  await expect(page.getByText('مرفوض: المستند لا يخص هذا الطفل').first()).toBeVisible({ timeout: 20_000 })
})

import { test, expect } from '@playwright/test'

/**
 * المسار الكامل: حراسة → دخول → لوحة → تنقّل → صلاحيات → خروج.
 *
 * هذه الاختبارات تصيب الـ API الحقيقي وقاعدة البيانات المبذورة عمداً.
 * محاكاة الاستجابات هنا كانت ستخفي أهم ما نريد التأكد منه: أن كوكي جلسة
 * Sanctum يعبر من 5173 إلى 8000 فعلاً.
 */

const ADMIN = { email: 'admin@kafala.org', password: 'password' }
const SUPERVISOR = { email: 'supervisor0@kafala.org', password: 'password' }

async function login(page, user = ADMIN) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(user.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(user.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

test('الزائر غير الداخل يُحوَّل إلى صفحة الدخول', async ({ page }) => {
  await page.goto('/orphans')
  await expect(page).toHaveURL(/\/login/)
})

test('كلمة مرور خاطئة تعرض خطأ ولا تفتح الجلسة', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(ADMIN.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill('wrong-password')
  await page.getByRole('button', { name: 'دخول' }).click()

  await expect(page.getByRole('alert')).toContainText('بيانات الدخول غير صحيحة')
  await expect(page).toHaveURL(/\/login/)
})

test('اللوحة تعرض مؤشرات ومخططات من بيانات حقيقية', async ({ page }) => {
  await login(page)

  await expect(page.getByRole('heading', { name: 'نظرة عامة' })).toBeVisible()

  // المؤشرات أرقام لا شرطات — أي أنها وصلت من الخادم فعلاً
  const sponsored = page.getByText('أيتام مكفولون').first().locator('..')
  await expect(sponsored).not.toContainText('—')

  // خمسة مخططات على الأقل رُسمت على canvas
  await expect(page.locator('canvas')).toHaveCount(6)

  // الحالات العاجلة محمّلة
  await expect(page.getByRole('heading', { name: 'حالات عاجلة تنتظر كافلاً' })).toBeVisible()
})

test('الجدول البديل للمخطط الدائري يعرض النسب نفسها', async ({ page }) => {
  await login(page)

  await page.getByRole('button', { name: 'جدول' }).click()

  const table = page.getByRole('table', { name: 'توزيع الإنفاق حسب البند' })
  await expect(table).toBeVisible()
  await expect(table.getByText('مصاريف إدارية')).toBeVisible()
})

test('قائمة الأيتام تُفلتر وتفتح ملفاً كاملاً', async ({ page }) => {
  await login(page)

  await page.getByRole('link', { name: 'الأيتام' }).click()
  await page.waitForURL('**/orphans')

  await expect(page.getByRole('table')).toBeVisible()

  await page.getByPlaceholder('ابحث بالاسم أو المعرّف…').fill('KFL-1042')
  await expect(page.getByText('KFL-1042')).toBeVisible({ timeout: 10_000 })

  await page.getByRole('cell', { name: /أمنية/ }).click()
  await page.waitForURL(/\/orphans\/\d+/)

  await expect(page.getByRole('heading', { name: 'مستندات التحقق' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'الأسرة', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'التقارير الربعية' })).toBeVisible()
})

test('المشرف الميداني لا يرى شاشات المال', async ({ page }) => {
  await login(page, SUPERVISOR)

  const nav = page.getByRole('navigation', { name: 'التنقل الرئيسي' })
  await expect(nav.getByRole('link', { name: 'الأيتام' })).toBeVisible()
  await expect(nav.getByRole('link', { name: 'المدفوعات' })).toHaveCount(0)
  await expect(nav.getByRole('link', { name: 'المستخدمون' })).toHaveCount(0)

  // وإخفاء الرابط ليس الحماية: الوصول المباشر يُردّ أيضاً
  await page.goto('/payments')
  await expect(page).toHaveURL(/\/dashboard/)
})

test('تسجيل الخروج ينهي الجلسة فعلاً', async ({ page }) => {
  await login(page)

  await page.getByRole('button', { name: 'تسجيل الخروج' }).click()
  await page.waitForURL('**/login')

  await page.goto('/dashboard')
  await expect(page).toHaveURL(/\/login/)
})

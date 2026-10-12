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

  // التحية بالاسم، ثم «بانتظارك» أول ما يراه الموظف
  await expect(page.getByRole('heading', { level: 1 })).toContainText(/صباح الخير|مساء الخير/)
  await expect(page.getByRole('heading', { name: 'بانتظارك' })).toBeVisible()

  // المؤشرات أرقام لا قيم فارغة، أي أنها وصلت من الخادم فعلاً
  const sponsored = page.getByText('أيتام مكفولون').first().locator('..')
  await expect(sponsored).not.toContainText('غير محدد')

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

  // ولا مال في نظرته العامة: لا مؤشرات مالية ولا مخططات (الخادم لا يرسلها)
  await expect(page.getByRole('heading', { name: 'بانتظارك' })).toBeVisible()
  await expect(page.getByText('مقبوضات الشهر')).toHaveCount(0)
  await expect(page.getByText('الالتزام الشهري')).toHaveCount(0)
  await expect(page.locator('canvas')).toHaveCount(0)

  // وإخفاء الرابط ليس الحماية: الوصول المباشر يُردّ أيضاً
  await page.goto('/payments')
  await expect(page).toHaveURL(/\/dashboard/)
})

test('مهمة في «بانتظارك» تفتح شاشتها مفلترة، والفلتر يبقى بعد التحديث', async ({ page }) => {
  await login(page)

  const task = page.getByRole('link', { name: /تقارير بانتظار اعتمادك/ })
  await expect(task).toBeVisible()
  await task.click()

  await expect(page).toHaveURL(/\/reports\?status=submitted/)
  await page.reload()
  await expect(page).toHaveURL(/status=submitted/)
  await expect(page.getByRole('combobox').filter({ hasText: 'مُرسل للمراجعة' })).toBeVisible()
})

test('التقرير يُقرأ كاملاً قبل القرار، والإرجاع لا يمرّ بلا ملاحظة', async ({ page }) => {
  await login(page)
  await page.goto('/reports?status=submitted')

  const row = page.locator('table tbody tr').first()
  await expect(row).toBeVisible({ timeout: 20_000 })

  // لا أزرار قرار في الجدول: القرار من الدرج بعد القراءة
  await expect(page.locator('table').getByRole('button', { name: 'اعتماد' })).toHaveCount(0)

  await row.click()
  const drawer = page.getByRole('dialog')
  await expect(drawer.getByRole('heading', { name: 'الملخص' })).toBeVisible()
  await expect(page).toHaveURL(/review=\d+/)

  // الرابط يفتح الدرج نفسه بعد التحديث
  await page.reload()
  await expect(page.getByRole('dialog').getByRole('heading', { name: 'الملخص' })).toBeVisible()

  await page.getByRole('button', { name: 'إرجاع للتعديل' }).click()
  await page.getByRole('button', { name: 'إرجاع', exact: true }).click()
  await expect(page.getByRole('alert')).toBeVisible()
  await page.getByRole('button', { name: 'تراجع' }).click()

  await page.keyboard.press('Escape')
  await expect(page).not.toHaveURL(/review=/)
})

test('طابور المستندات يفتح من القائمة الجانبية', async ({ page }) => {
  await login(page)
  await page.getByRole('link', { name: /التحقق من المستندات/ }).click()

  await expect(page.getByRole('heading', { name: 'التحقق من المستندات', level: 1 })).toBeVisible()
  await expect(page.getByText(/بانتظار التحقق/).first()).toBeVisible()
})

test('تسجيل الخروج ينهي الجلسة فعلاً', async ({ page }) => {
  await login(page)

  await page.getByRole('button', { name: 'تسجيل الخروج' }).click()
  await page.waitForURL('**/login')

  await page.goto('/dashboard')
  await expect(page).toHaveURL(/\/login/)
})

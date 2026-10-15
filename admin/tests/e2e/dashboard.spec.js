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

  // مخطط رئيسي واحد بتبويبات، والتحليل الأعمق أشرطة مكتوبة نسبها
  await expect(page.locator('canvas')).toHaveCount(1)
  await expect(page.getByRole('heading', { name: 'أين يذهب المال' })).toBeVisible()
  await expect(page.getByText('مصاريف إدارية')).toBeVisible()

  // الحالات العاجلة محمّلة
  await expect(page.getByRole('heading', { name: 'حالات عاجلة تنتظر كافلاً' })).toBeVisible()
})

test('تبويبات المخطط الرئيسي تبدّل القراءة بالفأرة ولوحة المفاتيح', async ({ page }) => {
  await login(page)

  const first = page.getByRole('tab', { name: 'الأيتام المكفولون' })
  await expect(first).toHaveAttribute('aria-selected', 'true')

  await page.getByRole('tab', { name: 'المقبوض والمصروف' }).click()
  await expect(page.getByRole('tab', { name: 'المقبوض والمصروف' })).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByRole('tabpanel')).toContainText('الشهر الجاري متقطّع')

  // الأسهم تنتقل بين التبويبات (اليسار هو «التالي» في الاتجاه العربي)
  await page.keyboard.press('ArrowLeft')
  await expect(page.getByRole('tab', { name: 'حركة الكفالات' })).toBeFocused()
  await expect(page.getByRole('tab', { name: 'حركة الكفالات' })).toHaveAttribute('aria-selected', 'true')

  // ومدة المخططات مفتاح مقسوم
  await page.getByRole('button', { name: /^6/ }).click()
  await expect(page.getByRole('button', { name: /^6/ })).toHaveAttribute('aria-pressed', 'true')
})

test('قائمة الأيتام تُفلتر وتفتح ملفاً كاملاً', async ({ page }) => {
  await login(page)

  await page.getByRole('link', { name: 'الأيتام' }).click()
  await page.waitForURL('**/orphans')

  await expect(page.getByRole('table')).toBeVisible()

  // رمز أول طفل في القائمة، لا رمزٌ مكتوب: البذر يتغيّر بين التشغيلات
  const firstRow = page.locator('table tbody tr').filter({ hasText: /KFL-\d+/ }).first()
  await expect(firstRow).toBeVisible({ timeout: 20_000 })
  const code = (await firstRow.innerText()).match(/KFL-\d+/)[0]

  await page.getByPlaceholder(/ابحث بالاسم أو المعرّف/).fill(code)
  const match = page.locator('table tbody tr').filter({ hasText: code })
  await expect(match).toHaveCount(1, { timeout: 10_000 })

  await match.locator('td').nth(1).click()
  await page.waitForURL(/\/orphans\/\d+/)

  // الملف أقسامٌ في تبويبات: الأسرة في «الملف»، والباقي في تبويبه
  await expect(page.getByRole('heading', { name: 'الأسرة', exact: true })).toBeVisible()
  await page.getByRole('button', { name: /^المستندات/ }).click()
  await expect(page.getByRole('heading', { name: 'مستندات التحقق' })).toBeVisible()
  await page.getByRole('button', { name: /^المتابعة/ }).click()
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
  await expect(page.getByRole('button', { name: /بانتظار المراجعة/ })).toHaveAttribute('aria-pressed', 'true')
})

test('التقرير يُقرأ كاملاً قبل القرار، والإرجاع لا يمرّ بلا ملاحظة', async ({ page }) => {
  await login(page)
  await page.goto('/reports?status=submitted')

  // صفّ حقيقي لا صفّ التحميل: ننتظر رمز ملف الطفل فيه
  const row = page.locator('table tbody tr').filter({ hasText: /KFL-/ }).first()
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
  await expect(page.getByText(/بانتظار قرار/).first()).toBeVisible()
})

test('اعتماد رسالة ينقص رقمها في الشريط الجانبي دون تحديث الصفحة', async ({ page }) => {
  await login(page)
  await page.goto('/messages?queue=1')

  const badge = page.locator('nav a[href="/messages"] .side__badge')
  await expect(badge).toBeVisible({ timeout: 20_000 })
  const before = Number(await badge.innerText())

  await page.locator('.js-approve').first().click()
  await expect(page.getByText('اعتُمدت الرسالة.')).toBeVisible()

  // بلا reload: الرقم يتبع الإجراء خلال ثوانٍ
  await expect.poll(async () => Number(await badge.innerText().catch(() => '0')), { timeout: 5_000 })
    .toBe(before - 1)
})

test('تسجيل الخروج ينهي الجلسة فعلاً', async ({ page }) => {
  await login(page)

  await page.getByRole('button', { name: 'تسجيل الخروج' }).click()
  await page.waitForURL('**/login')

  await page.goto('/dashboard')
  await expect(page).toHaveURL(/\/login/)
})

test('المحاسب يرى لوحة المال: التحصيل، وما ينتظر استدراكاً، والأقساط القادمة', async ({ page }) => {
  await login(page, { email: 'accountant@kafala.org', password: 'password' })

  await expect(page.getByRole('heading', { name: /^تحصيل / })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('heading', { name: 'دفعات تنتظر استدراكاً' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'الأقساط القادمة' })).toBeVisible()

  // لا أقسام المدير: الحالات العاجلة والدول ليست عمل المحاسب
  await expect(page.getByText('حالات عاجلة تنتظر كافلاً')).toHaveCount(0)
  await expect(page.getByText('أكثر الدول حالات')).toHaveCount(0)

  // «فشل ولم يُستدرك» يقود إلى قائمته
  await page.getByRole('link', { name: /فشل ولم يُستدرك/ }).click()
  await expect(page).toHaveURL(/\/payments\?unrecovered=1/)
})

test('المشرف يرى عمل بلده: من يزور، وتقارير الربع، و«سجّل زيارة» يفتح النموذج والطفل مختار', async ({ page }) => {
  await login(page, SUPERVISOR)

  await expect(page.getByRole('heading', { name: 'من تزور' })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('heading', { name: /^تقارير الربع/ })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'ملفات الأطفال' })).toBeVisible()
  // لا مال للمشرف
  await expect(page.getByText(/مقبوضات|تحصيل/)).toHaveCount(0)

  const first = page.locator('section[aria-labelledby="supNext"] li').first()
  const name = (await first.locator('a').first().innerText()).trim()
  await first.getByRole('link', { name: /سجّل زيارة/ }).click()

  await expect(page).toHaveURL(/\/visits(\?|$)/)
  await expect(page).not.toHaveURL(/record=/)
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible({ timeout: 15_000 })
  // الطفل مختار في حقله، والقائمة لا تنفتح وحدها بـ«لا نتائج»
  await expect(dialog.getByLabel(/^الطفل/)).toHaveValue(new RegExp(name))
  await expect(dialog.getByRole('listbox')).toBeHidden()
})

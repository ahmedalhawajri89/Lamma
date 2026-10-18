import { test, expect } from '@playwright/test'
import path from 'node:path'
import fs from 'node:fs'
import os from 'node:os'

/**
 * إنشاء ملف يتيم ورفع مستند والتحقق منه.
 *
 * هذا المسار لم تكن له واجهة إطلاقاً، وكان يخفي عيبين حقيقيين:
 * قيمة `status` الافتراضية تعيش في الهجرة لا في الموديل فيسقط الإنشاء بـ٥٠٠،
 * وسياسة `create` معرَّفة ولا تُستدعى فيمرّ من لا يملك الصلاحية.
 *
 * يصيب الـ API الحقيقي — محاكاة الاستجابات هنا تُلغي كل قيمته.
 */

const ADMIN = { email: 'admin@kafala.org', password: 'password' }
const ACCOUNTANT = { email: 'accountant@kafala.org', password: 'password' }
const SUPERVISOR = { email: 'supervisor0@kafala.org', password: 'password' }

async function login(page, user = ADMIN) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(user.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(user.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
}

/** يقرأ خاصية من مدخلٍ يُعثر عليه بتسميته. */
function field(page, labelText, prop = 'value') {
  return page.evaluate(
    ([label, key]) => {
      const el = [...document.querySelectorAll('[role=dialog] label')]
        .find((x) => x.textContent.trim().startsWith(label))
      if (!el) return null
      const input = document.getElementById(el.getAttribute('for'))

      /*
       * حقل البحث (SearchSelect) يعدّ نتائجه كما تُعدّ خيارات القائمة، مع
       * خانة لعنصرها النائب كي يبقى «أكثر من واحد» يعني «توجد نتائج». والتركيز
       * عليه يجلب النتائج الأولى، كما يفعل المستخدم.
       */
      if (key === 'options' && input.getAttribute('role') === 'combobox') {
        if (document.activeElement !== input) input.focus()
        return document.querySelectorAll(`[id="${input.getAttribute('aria-controls')}"] [role=option]`).length + 1
      }

      return key === 'options' ? input.options.length : input[key]
    },
    [labelText, prop],
  )
}

/**
 * ينتظر انتهاء تحميل الجدول.
 *
 * `DataTable` يرسم خمسة صفوف هيكلية أثناء الجلب، وهي `<tr>` كاملة — فعدّ
 * الصفوف أثناءها يُرجع خمسة، ثم يُقارَن بعدد الصفوف الحقيقية فيسقط
 * الاختبار بفارقٍ لا معنى له.
 */
async function tableReady(page) {
  await expect(page.locator('table .animate-pulse')).toHaveCount(0, { timeout: 20_000 })
}

/** صورة WebP صغيرة صالحة — الخادم يفحص الـmime لا الامتداد. */
function sampleImage() {
  const file = path.join(os.tmpdir(), 'kafala-e2e-cover.webp')
  fs.writeFileSync(file, Buffer.from('UklGRiIAAABXRUJQVlA4TBUAAAAvAAAAAAfQ//73v/+BiOh/AAA=', 'base64'))
  return file
}

/** ملف PDF صغير حقيقي — الخادم يفحص الـmime لا الامتداد. */
function samplePdf() {
  const file = path.join(os.tmpdir(), 'kafala-e2e-document.pdf')
  fs.writeFileSync(file, '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n')
  return file
}

/** يملأ الحقل عبر تسميته — النموذج يستعمل `useId` فالمعرّفات مولَّدة. */
async function fill(page, labelText, value) {
  await page.evaluate(
    ([label, v]) => {
      const el = [...document.querySelectorAll('[role=dialog] label')]
        .find((x) => x.textContent.trim().startsWith(label))
      const input = document.getElementById(el.getAttribute('for'))
      const Proto = input.tagName === 'TEXTAREA' ? HTMLTextAreaElement : HTMLInputElement
      Object.getOwnPropertyDescriptor(Proto.prototype, 'value').set.call(input, v)
      input.dispatchEvent(new Event('input', { bubbles: true }))
    },
    [labelText, value],
  )
}

/** يختار بالقيمة لا بالترتيب — الترتيب يتغيّر مع البيانات. */
async function selectValue(page, labelText, value) {
  await page.evaluate(
    ([label, v]) => {
      const el = [...document.querySelectorAll('[role=dialog] label')]
        .find((x) => x.textContent.trim().startsWith(label))
      const select = document.getElementById(el.getAttribute('for'))
      select.value = v
      select.dispatchEvent(new Event('change', { bubbles: true }))
    },
    [labelText, value],
  )
}

async function pick(page, labelText, index) {
  // حقل البحث: يُختار الخيار بالنقر كما يفعل المستخدم (index 1 = أول نتيجة)
  const listId = await page.evaluate((label) => {
    const el = [...document.querySelectorAll('[role=dialog] label')]
      .find((x) => x.textContent.trim().startsWith(label))
    const input = document.getElementById(el.getAttribute('for'))
    return input.getAttribute('role') === 'combobox' ? input.getAttribute('aria-controls') : null
  }, labelText)

  if (listId) {
    await page.locator(`[aria-controls="${listId}"]`).focus()
    const option = page.locator(`[id="${listId}"] [role=option]`).nth(index - 1)
    await option.waitFor({ timeout: 20_000 })
    await option.dispatchEvent('mousedown')
    return
  }

  await page.evaluate(
    ([label, i]) => {
      const el = [...document.querySelectorAll('[role=dialog] label')]
        .find((x) => x.textContent.trim().startsWith(label))
      const select = document.getElementById(el.getAttribute('for'))
      select.selectedIndex = i
      select.dispatchEvent(new Event('change', { bubbles: true }))
    },
    [labelText, index],
  )
}

test.describe('نموذج اليتيم', () => {
  test('ينشئ ملفاً ويرفع مستنداً، ورافعه لا يتحقق منه', async ({ page }) => {
    await login(page)
    await page.goto('/orphans')
    await expect(page.locator('table tbody tr').first()).toBeVisible({ timeout: 20_000 })

    // القوائم المرجعية تصل بعد الجدول — والزرّ بلا أسر لا ينفع
    await expect(page.getByRole('button', { name: 'ملف جديد' })).toBeVisible({ timeout: 15_000 })
    await page.waitForTimeout(2000)
    await page.getByRole('button', { name: 'ملف جديد' }).click()

    const dialog = page.locator('[role=dialog]')
    await expect(dialog).toBeVisible()

    await expect.poll(() => field(page, 'الأسرة', 'options'), { timeout: 20_000 })
      .toBeGreaterThan(1)

    /**
     * أخطاء الخادم تحت الحقول لا في إشعار عائم.
     *
     * الإشعار يختفي بعد ثوانٍ ولا يدلّ على أي حقل يعنيه — وهذا نموذج
     * باثني عشر حقلاً.
     */
    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(dialog.locator('[role=alert]').first()).toBeVisible({ timeout: 15_000 })
    expect(await dialog.locator('[role=alert]').count()).toBeGreaterThan(3)

    const name = `ليان${Date.now().toString().slice(-5)}`
    await fill(page, 'الاسم الأول', name)
    await fill(page, 'اسم العائلة', 'الحواجري')
    await fill(page, 'تاريخ الميلاد', '2016-05-10')
    await pick(page, 'الجنس', 2)
    await pick(page, 'الأسرة', 1)
    await pick(page, 'البرنامج', 1)

    // اختيار البرنامج يقترح سعره — اقتراحاً لا فرضاً
    await expect
      .poll(async () => page.evaluate(() => {
        const el = [...document.querySelectorAll('[role=dialog] label')]
          .find((x) => x.textContent.includes('الحاجة الشهرية'))
        return document.getElementById(el.getAttribute('for')).value
      }))
      .not.toBe('')

    await page.getByRole('button', { name: 'حفظ' }).click()
    await page.waitForURL(/\/orphans\/\d+/, { timeout: 25_000 })
    await expect(page.locator('h1')).toContainText(name)

    /**
     * لا زرّ توثيق ما دامت المستندات ناقصة: مسار الملف يقول ما ينقص ويأخذ
     * إليه. الخادم يرفض التوثيق بخطأ، وزرٌّ يُعرض ليُرفض فخٌّ لا واجهة.
     */
    await expect(page.getByText('تنتظر الرفع أو التحقق قبل التوثيق')).toBeVisible()
    await expect(page.getByRole('button', { name: 'وثّق الملف' })).toHaveCount(0)
    await page.getByRole('button', { name: 'إلى المستندات' }).click()

    const documents = page.locator('.card', { hasText: 'مستندات التحقق' })

    // ── رفع ──
    await documents.getByRole('button', { name: 'رفع' }).click()
    await page.locator('[role=dialog] select').selectOption({ index: 1 })
    await page.setInputFiles('#documentFile', samplePdf())
    await page.getByRole('button', { name: 'رفع', exact: true }).last().click()

    await expect(documents.getByText('مرفوع، ينتظر التحقق')).toBeVisible({ timeout: 20_000 })

    /**
     * ── فصل المهام: من رفع المستند لا يتحقق منه ──
     *
     * كان المرفوع يُتحقَّق منه بيد رافعه، فيمرّر شخص واحد الملف كله. زرّ
     * التحقق لا يظهر لرافعه، والمستند ينتظر في طابور التحقق مع سبب واضح.
     */
    await expect(documents.locator('button[title="تحقّق من هذا المستند"]')).toHaveCount(0)
    await expect(documents.locator('button[title="حذف"]')).toHaveCount(1)

    // في الطابور: مجموعة الطفل، ولوحة المراجعة لا تعطي رافعه قراراً
    await page.goto('/documents')
    const group = page.locator('aside section', { hasText: name })
    await expect(group).toBeVisible({ timeout: 20_000 })
    await expect(group.getByText('رفعته أنت')).toBeVisible()
    await group.locator('li button').first().click()
    await expect(page.getByText('رفعت هذا المستند بنفسك، فيتحقق منه غيرك.')).toBeVisible()
    await expect(page.getByRole('button', { name: /تحقّقت منه/ })).toHaveCount(0)
  })

  test('نافذة التحرير تُملأ وتُغلق بـEscape', async ({ page }) => {
    await login(page)
    await page.goto('/orphans')
    /**
     * ننتظر صفّاً فيه معرّف حقيقي لا أيّ `tr`.
     *
     * `DataTable` يرسم خمسة صفوف هيكلية أثناء التحميل، وهي بلا `@click`.
     * فـ`tr:first` يطابق واحداً منها، والنقر عليه لا يفعل شيئاً — ويسقط
     * الاختبار بعدها بعشرين ثانية عند انتظار عنوانٍ لن يتغيّر.
     */
    const row = page.locator('table tbody tr', { hasText: /KFL-\d+/ }).first()
    await expect(row).toBeVisible({ timeout: 20_000 })

    // خلية لا صفّاً: مركز `tr` العريض قد يقع في فراغ بين الخلايا فلا يُلتقط
    await row.locator('td').first().click()

    /**
     * ننتظر العنوان لا `h1`: ترويسة صفحة القائمة `h1` أيضاً («الأيتام»).
     * فتأكيدٌ على `h1` يمرّ حتى لو لم ينتقل شيء، ثم يسقط الاختبار عند زرّ
     * لا وجود له — بسببٍ يبدو غير ذي صلة بالعطب الحقيقي.
     */
    await page.waitForURL(/\/orphans\/\d+/, { timeout: 20_000 })

    const edit = page.getByRole('button', { name: 'تحرير' })
    await expect(edit).toBeVisible({ timeout: 20_000 })
    await edit.click()

    const value = await page.evaluate(() => {
      const el = [...document.querySelectorAll('[role=dialog] label')]
        .find((x) => x.textContent.trim().startsWith('الاسم الأول'))
      return document.getElementById(el.getAttribute('for')).value
    })
    expect(value.length).toBeGreaterThan(1)

    // نموذج غير معدَّل يُغلق بلا سؤال
    await page.keyboard.press('Escape')
    await expect(page.locator('[role=dialog]')).toBeHidden()
  })
})

/**
 * الأسرة أسبق من اليتيم — ولا يُنشأ ملف بلا واحدة.
 *
 * وهذا النموذج كشف عيبين: `national_id` مستثنى من الإسناد الجماعي بقصد
 * والمتحكّم يمرّره في المُنشئ فيسقط الطلب بـ٥٠٠، و`structuredClone` على
 * كائن Vue التفاعلي يرمي `DataCloneError` فلا يغادر الطلبُ المتصفحَ أصلاً.
 */
test.describe('نموذج الأسرة', () => {
  test('ينشئ أسرة وتتبع المدنُ الدولةَ', async ({ page }) => {
    await login(page)
    await page.goto('/families')
    await expect(page.locator('table tbody tr', { hasText: /FAM-\d+/ }).first())
      .toBeVisible({ timeout: 20_000 })

    await expect(page.getByRole('button', { name: 'أسرة جديدة' })).toBeVisible({ timeout: 15_000 })
    await page.waitForTimeout(2000)
    await page.getByRole('button', { name: 'أسرة جديدة' }).click()

    const cityCount = async () => page.evaluate(() => {
      const el = [...document.querySelectorAll('[role=dialog] label')]
        .find((x) => x.textContent.trim().startsWith('المدينة'))
      return document.getElementById(el.getAttribute('for')).options.length
    })

    // قبل اختيار الدولة: خيار العنصر النائب وحده، والحقل معطّل
    expect(await cityCount()).toBe(1)

    await pick(page, 'الدولة', 1)
    await expect.poll(cityCount).toBeGreaterThan(1)

    const guardian = `أم خالد ${Date.now().toString().slice(-5)}`
    await fill(page, 'الاسم الكامل', guardian)
    await pick(page, 'صلته بالأطفال', 1)
    await fill(page, 'عدد الأفراد', '6')
    await pick(page, 'وضع السكن', 1)
    await pick(page, 'المدينة', 1)

    // رقم الهوية يُقبل ويُخزَّن بصمةً — وتمريره كان يُسقط الطلب بـ٥٠٠.
    // رقمٌ جديد في كل تشغيل: الخادم يكشف الوصيّ المسجَّل من قبل برقمه
    const nationalId = `9${Date.now().toString().slice(-8)}`
    await fill(page, 'رقم الهوية', nationalId)

    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 20_000 })
    await expect(page.getByText('أُنشئت الأسرة.')).toBeVisible()

    // ── الأسرة الجديدة تُفتح صفحتها، وخطوتها التالية إضافة أيتامها ──
    await page.waitForURL(/\/families\/\d+/, { timeout: 20_000 })
    await expect(page.locator('h1')).toContainText(guardian)
    await page.getByRole('button', { name: 'أضف أول يتيم' }).click()
    // الأسرة مختارة سلفاً في نموذج اليتيم
    await expect(page.locator('[role=dialog]').getByLabel('الأسرة')).toHaveValue(new RegExp(guardian))
    await page.keyboard.press('Escape')
    await expect(page.locator('[role=dialog]')).toHaveCount(0)

    // ── التحرير من صفحتها: النافذة مملوءة، والتعديل يظهر في سجلّها ──
    await page.getByRole('button', { name: 'تحرير' }).click()
    await expect(page.locator('[role=dialog]')).toBeVisible()
    expect(await page.evaluate(() => {
      const el = [...document.querySelectorAll('[role=dialog] label')]
        .find((x) => x.textContent.trim().startsWith('الاسم الكامل'))
      return document.getElementById(el.getAttribute('for')).value
    })).toBe(guardian)

    /**
     * حقل رقم الهوية غائب عند التحرير.
     *
     * الخادم يخزّن بصمةً لا رقماً، فلا شيء يُعاد ليُملأ به — وحقلٌ فارغ هنا
     * يوحي بأن الرقم ضاع.
     */
    await expect(page.locator('[role=dialog] label').filter({ hasText: 'رقم الهوية' })).toHaveCount(0)

    await fill(page, 'عدد الأفراد', '7')
    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 20_000 })
    await expect(page.locator('[data-history]')).toContainText('عدد الأفراد', { timeout: 20_000 })

    // ── الرقم نفسه لأسرة ثانية يُكشف تحت حقله، لا يُنشئ أسرة مكررة ──
    await page.goto('/families')
    // القوائم المرجعية حُمّلت (فلتر الدولة يظهر بها) قبل فتح النموذج
    await expect(page.getByLabel('الدولة')).toBeVisible({ timeout: 20_000 })
    await page.getByRole('button', { name: 'أسرة جديدة' }).click()
    await pick(page, 'الدولة', 1)
    await fill(page, 'الاسم الكامل', `${guardian} مكرر`)
    await pick(page, 'صلته بالأطفال', 1)
    await fill(page, 'عدد الأفراد', '6')
    await pick(page, 'وضع السكن', 1)
    await fill(page, 'رقم الهوية', nationalId)
    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.locator('[role=dialog]').getByText(/رقم الهوية هذا مسجَّل/)).toBeVisible({ timeout: 20_000 })
    // الإغلاق والنموذج معدَّل يسأل بنافذة اللوحة لا بنافذة المتصفح
    await page.keyboard.press('Escape')
    await page.getByRole('button', { name: 'أغلق دون حفظ' }).click()
    await expect(page.locator('[role=dialog]')).toHaveCount(0)
  })
})

/**
 * شاشات المال.
 *
 * كل رقم على صفحة الشفافية العامة يبدأ من نافذة تسجيل الصرف، وكانت الشاشة
 * تعرض ولا تسجّل — فالأرقام المنشورة لا سبيل لتحديثها من اللوحة أصلاً.
 */
test.describe('تسجيل الصرف', () => {
  test('يسجّل صرفاً بإيصال، والحقول تتبع البند والعملة', async ({ page }) => {
    await login(page, ACCOUNTANT)
    await page.goto('/disbursements')
    await expect(page.locator('table tbody tr td').first()).toBeVisible({ timeout: 20_000 })

    await expect(page.getByRole('button', { name: 'تسجيل صرف' })).toBeVisible({ timeout: 15_000 })
    await page.waitForTimeout(2000)
    await page.getByRole('button', { name: 'تسجيل صرف' }).click()

    /**
     * سعر الصرف يظهر لغير الدولار وحده.
     *
     * الدولار عملة الأساس، وحقلٌ يسأل عن سعر صرف الدولار إلى الدولار
     * سؤالٌ بلا معنى — ويُغري بكتابة رقم خاطئ فيه.
     */
    const rateLabel = page.locator('[role=dialog] label').filter({ hasText: 'سعر الصرف' })
    await expect(rateLabel).toHaveCount(0)

    await pick(page, 'العملة', 2)
    await expect(rateLabel).toHaveCount(1)
    await pick(page, 'العملة', 1)

    /**
     * البند التشغيلي يعطّل حقل الطفل.
     *
     * المصروف الإداري لا يخصّ طفلاً بعينه، وربطه بواحد يفسد نسبة «ما وصل
     * الطفل» في تقرير الشفافية — وهي النسبة التي تقوم عليها دعوى المنصة كلها.
     */
    /**
     * ننتظر وصول البنود قبل قراءتها.
     *
     * القوائم المرجعية تُجلب موازيةً لجدول الشاشة، وتحت حمل الحزمة كاملةً
     * تصل بعدها. وقراءةُ `<select>` قبل ذلك تُرجع العنصر النائب وحده —
     * فيسقط الاختبار بـ`-1` في سطرٍ لا علاقة له بما يُختبَر.
     */
    await expect.poll(() => field(page, 'بند الصرف', 'options'), { timeout: 20_000 })
      .toBeGreaterThan(3)

    const categories = await page.evaluate(() => {
      const el = [...document.querySelectorAll('[role=dialog] label')]
        .find((x) => x.textContent.trim().startsWith('بند'))
      return [...document.getElementById(el.getAttribute('for')).options].map((o) => o.text)
    })
    const overhead = categories.findIndex((t) => t.includes('تشغيلي'))
    expect(overhead).toBeGreaterThan(0)

    await pick(page, 'بند الصرف', overhead)
    await expect.poll(() => field(page, 'الطفل', 'disabled')).toBe(true)

    await pick(page, 'بند الصرف', 1)
    await fill(page, 'المبلغ', '125.50')
    const statement = `اختبار ${Date.now().toString().slice(-5)}`
    await fill(page, 'البيان', statement)
    await page.setInputFiles('#receiptFile', samplePdf())

    await page.getByRole('button', { name: 'سجّل الصرف' }).click()
    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })
    await expect(page.getByText(/يحتاج اعتماداً/)).toBeVisible()

    // ── مبدأ العينين: من سجّله لا يعتمده ──
    await page.goto(`/disbursements?status=pending&q=${encodeURIComponent(statement)}`)
    const mine = page.locator('table tbody tr', { hasText: '125.50' }).first()
    await expect(mine).toBeVisible({ timeout: 20_000 })
    await mine.click()
    await expect(page.getByText('سجّلته أنت، فيعتمده غيرك.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'اعتمد الصرف' })).toHaveCount(0)

    // ── والمدير يعتمده من الدرج بعد رؤية الإيصال ──
    await page.keyboard.press('Escape')
    await page.getByRole('button', { name: 'تسجيل الخروج' }).click()
    await page.waitForURL('**/login')
    await login(page, ADMIN)
    await page.goto(`/disbursements?status=pending&q=${encodeURIComponent(statement)}`)
    const row = page.locator('table tbody tr', { hasText: '125.50' }).first()
    await expect(row).toBeVisible({ timeout: 20_000 })
    await row.click()

    const drawer = page.getByRole('dialog')
    await drawer.getByRole('button', { name: 'عرض الإيصال' }).click()
    await expect(drawer.locator('iframe[src^="blob:"]')).toBeVisible({ timeout: 20_000 })
    await drawer.getByRole('button', { name: 'اعتمد الصرف' }).click()
    await expect(page.getByText(/اعتُمد الصرف/)).toBeVisible({ timeout: 20_000 })
  })
})

test.describe('الدفعة اليدوية', () => {
  test('تُسجَّل، والاسترداد في تفاصيلها لا في الصف', async ({ page }) => {
    await login(page, ACCOUNTANT)
    await page.goto('/payments')
    await expect(page.locator('table tbody tr td').first()).toBeVisible({ timeout: 20_000 })

    await expect(page.getByRole('button', { name: 'دفعة يدوية' })).toBeVisible({ timeout: 15_000 })
    await page.waitForTimeout(2000)
    await page.getByRole('button', { name: 'دفعة يدوية' }).click()

    // القوائم المرجعية تُجلب موازيةً للجدول وتصل بعده تحت الحمل
    await expect.poll(() => field(page, 'الكفالة', 'options'), { timeout: 20_000 })
      .toBeGreaterThan(1)

    await pick(page, 'الكفالة', 1)

    // مبلغ الكفالة يظهر تلميحاً — فلا يحزر المحاسب رقماً يعرفه النظام
    await expect(page.locator('[role=dialog]').getByText(/اتركه فارغاً ليُسجَّل مبلغ الكفالة \$/))
      .toBeVisible()

    await fill(page, 'رقم الحوالة', `TRX-${Date.now().toString().slice(-7)}`)
    await page.getByRole('button', { name: 'سجّل الدفعة' }).click()

    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })
    await expect(page.getByText('سُجّلت الدفعة.')).toBeVisible()

    /**
     * الاسترداد في تفاصيل الدفعة لا في الصف: إجراءٌ لا يُتراجع عنه لا يكون
     * على بعد نقرة من كل دفعة. وللناجحة وحدها (`PaymentPolicy::refund`).
     */
    await expect(page.locator('table tbody button', { hasText: 'استرداد' })).toHaveCount(0)
    await page.locator('table tbody tr').filter({ hasText: 'مكتملة' }).first().click()
    const details = page.locator('[role=dialog]')
    await expect(details.getByText(/رقم الحوالة أو/)).toBeVisible()
    await expect(details.getByRole('button', { name: 'استرداد' })).toBeVisible()
  })
})

/**
 * الزيارة الميدانية.
 *
 * كشف هذا النموذج عيباً لا يظهر أبداً لمن يطوّر على خادمٍ بتوقيته: الحقل
 * `datetime-local` يُخرج وقتاً محلياً بلا منطقة زمنية، والخادم يقرؤه
 * بتوقيته (‏UTC). فمشرفٌ شرق غرينتش يفتح النافذة ويضغط «سجّل» فوراً
 * فيُردّ بـ«لا تُسجَّل زيارة بتاريخ مستقبلي» — عن وقتٍ مضى فعلاً.
 */
test.describe('الزيارة الميدانية', () => {
  test('تُسجَّل بالوقت المعبّأ تلقائياً، وترفض ملاحظةً سطراً واحداً', async ({ page }) => {
    await login(page, SUPERVISOR)
    await page.goto('/visits')
    await expect(page.locator('table tbody tr td').first()).toBeVisible({ timeout: 20_000 })

    await expect(page.getByRole('button', { name: 'تسجيل زيارة' })).toBeVisible({ timeout: 15_000 })
    await page.waitForTimeout(2000)
    await page.getByRole('button', { name: 'تسجيل زيارة' }).click()

    // الوقت معبّأ سلفاً — والمشرف لا يكتبه بيده بعد كل زيارة
    expect((await field(page, 'وقت الزيارة'))?.length).toBeGreaterThan(10)

    await expect.poll(() => field(page, 'الطفل', 'options'), { timeout: 20_000 })
      .toBeGreaterThan(1)

    await pick(page, 'الطفل', 1)
    await fill(page, 'الملاحظات', 'قصير')
    await page.getByRole('button', { name: 'سجّل الزيارة' }).click()

    await expect(page.locator('[role=dialog]').getByText(/سطر واحد لا يوثّق/))
      .toBeVisible({ timeout: 20_000 })

    /**
     * وهذا هو الاختبار الحقيقي: الوقت الافتراضي لا يُردّ «مستقبلياً».
     * لم نلمس الحقل، فما يُرسل هو ما ملأته الواجهة.
     */
    await fill(page, 'الملاحظات', 'زرت البيت صباحاً. الأسرة بخير، والطفلة تنتظم في المدرسة.')
    await page.locator('[role=dialog] button', { hasText: 'ملابس شتوية' }).click()
    await page.getByRole('button', { name: 'سجّل الزيارة' }).click()

    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })
    await expect(page.getByText('سُجّلت الزيارة.')).toBeVisible()
  })
})

test.describe('التقرير الربعي', () => {
  test('يُحفظ مسوّدةً، ويرفض تقريراً ثانياً للربع نفسه', async ({ page }) => {
    await login(page, SUPERVISOR)
    await page.goto('/reports')
    await expect(page.locator('table tbody tr td').first()).toBeVisible({ timeout: 20_000 })

    await expect(page.getByRole('button', { name: 'تقرير جديد' })).toBeVisible({ timeout: 15_000 })
    await page.waitForTimeout(2000)
    await page.getByRole('button', { name: 'تقرير جديد' }).click()

    /**
     * الربع المقترح هو المنقضي لا الجاري.
     * التقرير الربعي يُكتب عن فترة انتهت، واقتراح الجاري يدفع إلى كتابة
     * تقرير عن شهرٍ لم يمضِ.
     */
    const quarter = Number(await field(page, 'الربع'))
    const currentQuarter = Math.floor(new Date().getMonth() / 3) + 1
    expect(quarter).not.toBe(currentQuarter)

    /**
     * نبحث عن طفلٍ بلا تقرير في هذه الفترة.
     *
     * الحزمة تصيب قاعدة بيانات حقيقية لا تُصفَّر بين التشغيلات، وقاعدةُ
     * «تقرير واحد لكل طفل في كل ربع» تجعل رقماً ثابتاً هنا يمرّ أول مرة
     * ويسقط في الثانية — فيبدو العطب في الكود وهو في الاختبار.
     */
    await pick(page, 'السنة', 4)
    await fill(page, 'العنوان', 'تقرير اختباري — تحسّن في الرياضيات')

    await expect.poll(() => field(page, 'الطفل', 'options'), { timeout: 20_000 })
      .toBeGreaterThan(1)

    const total = await field(page, 'الطفل', 'options')
    let chosen = 0

    for (let index = 1; index < Math.min(total, 12); index += 1) {
      await pick(page, 'الطفل', index)
      await page.getByRole('button', { name: 'احفظ مسوّدة' }).click()

      // النافذة تُغلق عند النجاح؛ وإلا فالخطأ ظاهر تحت الحقل
      await expect
        .poll(async () => (await page.locator('[role=dialog]').isVisible())
          ? (await page.locator('[role=dialog] [role=alert]').count()) > 0
          : 'saved', { timeout: 25_000 })
        .not.toBe(false)

      if (!(await page.locator('[role=dialog]').isVisible())) {
        chosen = index
        break
      }
    }

    expect(chosen, 'لم نجد طفلاً بلا تقرير في هذه الفترة').toBeGreaterThan(0)
    await expect(page.getByText(/مسوّدة التقرير/)).toBeVisible()

    // ثانٍ للطفل والربع نفسيهما يُرفض — والرسالة تحت الحقل لا في إشعار عابر
    await page.getByRole('button', { name: 'تقرير جديد' }).click()
    await pick(page, 'الطفل', chosen)
    await pick(page, 'السنة', 4)
    await fill(page, 'العنوان', 'تقرير مكرّر')
    await page.getByRole('button', { name: 'احفظ مسوّدة' }).click()

    await expect(page.locator('[role=dialog]').getByText(/يوجد تقرير لهذا الطفل في هذا الربع/))
      .toBeVisible({ timeout: 20_000 })
  })
})

/**
 * المستخدمون والإعدادات.
 *
 * شاشة الإعدادات لم تكن موجودة إطلاقاً رغم أن نقطتيها جاهزتان: يُغيَّر اسم
 * المنصة أو نسبة التشغيل المستهدَفة بتحرير قاعدة البيانات يدوياً.
 */
test.describe('المستخدمون', () => {
  test('حقولٌ تتبع الدور، وحسابٌ بلا كلمة مرور يكتبها المدير', async ({ page }) => {
    await login(page)
    await page.goto('/users')
    await expect(page.locator('table tbody tr td').first()).toBeVisible({ timeout: 20_000 })

    await expect(page.getByRole('button', { name: 'موظف جديد' })).toBeVisible({ timeout: 15_000 })
    await page.waitForTimeout(2000)
    await page.getByRole('button', { name: 'موظف جديد' }).click()

    const label = (text) => page.locator('[role=dialog] label').filter({ hasText: text })

    /**
     * لا حقل كلمة مرور — وهذا مقصود.
     *
     * الخادم يولّد كلمةً عشوائية لا تُعرض لأحد، والموظف يضبطها عبر رابط
     * الاستعادة. كلمةٌ مؤقتة يكتبها المدير تمرّ في بريد أو محادثة وتبقى
     * هناك إلى الأبد.
     */
    await expect(label('كلمة المرور')).toHaveCount(0)

    /**
     * البلد المُسنَد يعني شيئاً للمشرف الميداني وحده.
     * `OrphanPolicy` تحصره في بلده؛ والمدير والمحاسب بلا نطاق جغرافي،
     * وإسناد بلدٍ لهما يوحي بحدٍّ لا وجود له.
     */
    await expect(label('البلد المُسنَد')).toHaveCount(0)
    await selectValue(page, 'الدور', 'field_supervisor')
    await expect(label('البلد المُسنَد')).toHaveCount(1)
    await selectValue(page, 'الدور', 'accountant')
    await expect(label('البلد المُسنَد')).toHaveCount(0)

    await fill(page, 'الاسم', 'موظف اختباري')
    await fill(page, 'البريد', `staff${Date.now().toString().slice(-7)}@kafala.org`)
    await page.getByRole('button', { name: 'حفظ' }).click()

    await expect(page.getByRole('dialog', { name: 'موظف جديد' })).toBeHidden({ timeout: 25_000 })
    await expect(page.getByText(/رابط تعيين كلمة المرور/).first()).toBeVisible()

    // يُفتح درج الموظف الجديد: ينتظر التفعيل، وما يقدر عليه دوره ظاهر
    const drawer = page.getByRole('dialog', { name: 'موظف اختباري' })
    await expect(drawer).toContainText('لم يفعّل حسابه بعد', { timeout: 15_000 })
    await expect(drawer.getByText('تسجيل دفعة يدوية')).toBeVisible()
    await expect(drawer.getByText('تعديل ملف يتيم')).toHaveCount(0)
  })
})

test.describe('الإعدادات', () => {
  test('لوحة تُقرأ بسياقها، والتعديل في درج يُحفظ وحده ويُسجَّل', async ({ page }) => {
    await login(page)
    await page.goto('/settings')

    // تُقرأ: الهوية وحالة كل سياسة بأثرها الآن
    await expect(page.locator('#idName')).not.toHaveText('', { timeout: 20_000 })
    await expect(page.getByText(/الفعلي في \d{4}/)).toBeVisible()
    await expect(page.getByText(/رسالة تنتظر المراجعة الآن/)).toBeVisible()

    // التعديل في درج: بلا تغيير لا زرّ حفظ
    await page.getByRole('button', { name: /تعديل الهوية/ }).click()
    const drawer = page.getByRole('dialog', { name: 'هوية المنصة والتواصل' })
    const tagline = drawer.getByLabel('الوصف المختصر')
    const original = await tagline.inputValue()
    await expect(drawer.getByRole('button', { name: 'إغلاق' }).last()).toBeVisible()
    await expect(drawer.getByRole('button', { name: /حفظ التغييرات/ })).toHaveCount(0)

    /**
     * كتابة القيمة نفسها ليست تغييراً: حفظٌ بلا تعديل يكتب الصفوف ويمسح
     * ذاكرة الموقع العام بلا سبب، ويملأ السجل بتغييرات لم تقع.
     */
    await tagline.fill(original)
    await expect(drawer.getByRole('button', { name: /حفظ التغييرات/ })).toHaveCount(0)

    // تغيير فعلي يُحفظ، ويظهر في اللوحة وفي السجل
    await tagline.fill(`${original} (اختبار)`)
    await drawer.getByRole('button', { name: /حفظ التغييرات/ }).click()
    await expect(page.getByText(/حُفظت هوية المنصة/)).toBeVisible({ timeout: 20_000 })
    await expect(drawer).toHaveCount(0)
    await expect(page.locator('#idName ~ p')).toHaveText(`${original} (اختبار)`)
    await expect(page.locator('section[aria-labelledby=hTitle]')).toContainText('الوصف المختصر', { timeout: 15_000 })

    // وتُعاد القيمة
    await page.getByRole('button', { name: /تعديل الهوية/ }).click()
    await drawer.getByLabel('الوصف المختصر').fill(original)
    await drawer.getByRole('button', { name: /حفظ التغييرات/ }).click()
    await expect(drawer).toHaveCount(0, { timeout: 20_000 })
  })
})

/**
 * محتوى الموقع العام.
 *
 * هذه الشاشة لم تكن موجودة: كل ما يقرأه الزائر كان يُكتب في بذرة قاعدة
 * البيانات وحدها. وكشفت ثلاثة عيوب — أخطرها أن فتح مقال لتغيير حالته كان
 * يمحو نصّه، لأن قائمة اللوحة لا تحمل `body` فتُرسله النافذة فارغاً.
 */
test.describe('محتوى الموقع', () => {
  /**
   * «انشر الآن» بلا عنوان كان يقلب المقال «منشوراً» في النموذج قبل الفحص،
   * فيصير «حفظ» بعده نشراً بلا قصد. الحالة الآن لا تتغيّر إلا بإجراءٍ ناجح.
   */
  test('النشر بلا عنوان لا يغيّر شيئاً، والزرّ يقول ما سيفعله', async ({ page }) => {
    await login(page)
    await page.goto('/content/posts/new')
    const publish = page.getByRole('button', { name: 'انشر الآن' })
    await expect(publish).toBeVisible({ timeout: 20_000 })

    await publish.click()
    await expect(page.getByText('العنوان مطلوب.')).toBeVisible()
    await expect(page.locator('#postTitle')).toBeFocused()
    // ما زالت مسوّدة: الزرّان كما كانا، والملاحظة تقول ذلك
    await expect(publish).toBeVisible()
    await expect(page.getByRole('button', { name: /حفظ المسوّدة/ })).toBeVisible()
    await expect(page.locator('[data-status-note]')).toContainText('مسوّدة')

    // تاريخٌ قادم يجعل النشر جدولة، ويسمّيها الزرّ
    await page.fill('#postDate', '2099-01-15')
    await expect(page.getByRole('button', { name: /جدولة لـ/ })).toBeVisible()
  })

  test('مسوّدة ← نشر ← تظهر على الموقع بنصّها كاملاً', async ({ page }) => {
    const SITE = process.env.E2E_SITE ?? 'http://127.0.0.1:8000'
    await login(page)
    await page.goto('/content')
    // المقالات والأسئلة والشهادات والشركاء ووثائق الشفافية
    await expect(page.locator('[role=tab]')).toHaveCount(5)

    // المقال يُكتب في صفحة محرّر كاملة لا في نافذة
    const title = `مقال اختباري ${Date.now().toString().slice(-6)}`
    await page.getByRole('button', { name: 'مقال جديد' }).click()
    await page.waitForURL(/\/content\/posts\/new/)

    await page.locator('#postTitle').fill(title)
    await page.locator('#postExcerpt').fill('مقتطف قصير.')
    // المحرّر المنسّق: Enter يصنع فقرة، وعنوان من الشريط
    await page.locator('#postBody .ProseMirror').click()
    await page.getByRole('button', { name: 'عنوان فرعي' }).click()
    await page.keyboard.type('عنوان داخل المقال')
    for (const p of ['الفقرة الأولى.', 'الفقرة الثانية.', 'والثالثة.']) {
      await page.keyboard.press('Enter')
      await page.keyboard.type(p)
    }
    await page.locator('#postCover').setInputFiles(sampleImage())

    // العدّ والمعاينة بقواعد الموقع: ثلاث فقرات
    await expect(page.getByText(/3 فقرة/)).toBeVisible()
    await page.getByRole('tab', { name: /معاينة/ }).click()
    await expect(page.locator('article .prose-admin > p')).toHaveCount(3)
    await expect(page.locator('article .prose-admin > h2')).toHaveText('عنوان داخل المقال')

    await page.getByRole('button', { name: /^حفظ/ }).click()
    await page.waitForURL(/\/content\/posts\/\d+/, { timeout: 25_000 })
    const editorUrl = page.url()

    /**
     * المسوّدة لا تظهر على الموقع.
     *
     * وهذا نصف الاختبار: النصف الآخر أنها تظهر بعد النشر، ولو غاب أحدهما
     * لمرّ عيبٌ يجعل كل مقال ظاهراً أو كلّه مخفياً.
     */
    await page.goto(`${SITE}/blog`)
    await expect(page.locator('#blogGrid .mag-card').first()).toBeVisible({ timeout: 20_000 })
    await expect(page.locator('.mag-card, .feature', { hasText: title })).toHaveCount(0)

    // ── النشر من المحرّر، والنصّ محفوظ فيه لا يُمحى ──
    await page.goto(editorUrl)
    await expect.poll(async () => (await page.locator('#postBody .ProseMirror').innerText()).length, { timeout: 20_000 }).toBeGreaterThan(20)
    await page.getByRole('button', { name: /انشر الآن/ }).click()
    await expect(page.getByRole('link', { name: /على الموقع/ })).toBeVisible({ timeout: 25_000 })

    // ── وعلى الموقع العام ──
    await page.goto(`${SITE}/blog`)
    // في الشبكة، أو المقال المميّز فوقها إن صار الأحدث بلا مميّز غيره
    const card = page.locator('.mag-card, .feature', { hasText: title })
    await expect(card).toHaveCount(1, { timeout: 20_000 })

    // صفحة المقال يرسمها الخادم: لا انتظار لتحميلٍ بعد الفتح
    await card.locator('a').first().click()
    await expect(page.locator('h1#postTitle')).toHaveText(title, { timeout: 20_000 })

    // التنسيق يصل الموقع كما كُتب: العنوان عنوان، والفقرات ثلاث
    await expect(page.locator('.post-body h2')).toHaveText('عنوان داخل المقال')
    await expect(page.locator('.post-body > p')).toHaveCount(3)
    expect(await page.locator('.post-cover img').evaluate((e) => e.naturalWidth)).toBeGreaterThan(0)
  })

  test('وثيقة شفافية تُرفع من اللوحة فيظهر رابطها على صفحة الشفافية', async ({ page }) => {
    const SITE = process.env.E2E_SITE ?? 'http://127.0.0.1:8000'
    await login(page)
    await page.goto('/content?tab=documents')

    // التراخيص بلا سنة: رفعٌ واحد يُنشر
    const input = page.locator('input[type=file][data-kind="licenses"]')
    await expect(input).toBeAttached({ timeout: 20_000 })
    await input.setInputFiles(samplePdf())
    await expect(page.getByText('نُشرت الوثيقة على صفحة الشفافية.')).toBeVisible({ timeout: 20_000 })
    await expect(page.getByText('منشورة على الموقع: التراخيص والاعتمادات')).toBeVisible()

    // على الموقع: زرّ تحميل حقيقي، والبقية «يُنشر بعد اعتماد المدقق»
    await page.goto(`${SITE}/transparency.html`)
    const doc = page.locator('#tpDocs .tp-doc', { hasText: 'التراخيص والاعتمادات' })
    await expect(doc.getByRole('link', { name: /تحميل PDF/ })).toHaveAttribute('href', /\/storage\/transparency\/.+\.pdf$/, { timeout: 20_000 })
    await expect(page.locator('#tpDocs .tp-doc.is-pending').first()).toContainText('يُنشر بعد اعتماد المدقق')
  })

  test('سؤال شائع يُضاف، ويُخفى بمفتاح، ويُحذف من نافذته', async ({ page }) => {
    await login(page)
    await page.goto('/content?tab=faqs')
    await expect(page.getByRole('list', { name: 'الأسئلة الشائعة' })).toBeVisible({ timeout: 20_000 })

    const question = `سؤال اختباري ${Date.now().toString().slice(-5)}؟`
    await page.getByRole('button', { name: 'إضافة' }).click()
    await fill(page, 'السؤال', question)
    await fill(page, 'الإجابة', 'جواب اختباري.')
    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })

    const row = page.locator('ol li', { hasText: question })
    await expect(row).toHaveCount(1, { timeout: 20_000 })

    // الإخفاء والإظهار بمفتاح في الصف، بلا نافذة
    const toggle = row.getByRole('switch')
    const was = await toggle.getAttribute('aria-checked')
    await toggle.click()
    await expect(toggle).toHaveAttribute('aria-checked', was === 'true' ? 'false' : 'true', { timeout: 20_000 })

    // التحرير يفتح النافذة مملوءة، والحذف فيها بعد رؤية العنصر
    await row.locator('button').filter({ hasText: question }).click()
    expect(await field(page, 'السؤال')).toBe(question)
    await page.getByRole('button', { name: 'حذف هذا العنصر من الموقع' }).click()
    const confirm = page.locator('[role=dialog]', { hasText: 'حذف العنصر' })
    await confirm.getByRole('button', { name: 'احذف' }).click()

    await expect(row).toHaveCount(0, { timeout: 20_000 })
  })
})

/**
 * صورة اليتيم.
 *
 * كشف هذا الحقل مزلقين لا يظهر أيّهما بلا واجهة:
 *
 *  ١) `ProgramResource` تُرجع الـslug في حقل `id` عمداً (الموقع العام يعرف
 *     البرامج بأسمائها اللاتينية). فنافذة التحرير كانت تُسند `"comprehensive"`
 *     إلى `program_id`: لا يطابق أي خيار فيبقى الحقل فارغاً، ويردّ الخادم
 *     «يجب أن يكون عدداً صحيحاً» عند الحفظ — عن حقلٍ لم يلمسه المستخدم.
 *
 *  ٢) PHP لا يحلّل جسم `multipart` إلا في `POST`، فـ`PATCH` بـ`FormData`
 *     يصل بحقول فارغة وملفٍ مفقود. والحلّ انتحال الأسلوب بـ`_method`.
 */
test.describe('صورة اليتيم', () => {
  test('تُرفع من نافذة التحرير، والبرنامج يبقى محدَّداً', async ({ page }) => {
    await login(page)
    await page.goto('/orphans')

    const row = page.locator('table tbody tr', { hasText: /KFL-\d+/ }).first()
    await expect(row).toBeVisible({ timeout: 20_000 })
    await row.locator('td').first().click()
    await page.waitForURL(/\/orphans\/\d+/, { timeout: 20_000 })

    const edit = page.getByRole('button', { name: 'تحرير' })
    await expect(edit).toBeVisible({ timeout: 20_000 })
    await page.waitForTimeout(2000)
    await edit.click()

    /**
     * البرنامج محدَّد سلفاً.
     *
     * حقلٌ إجباري يفتح فارغاً يجعل الحفظ يسقط على شيء لم يلمسه المستخدم —
     * ولا يدلّه شيء على أن السبب في تحميل النافذة لا في إدخاله.
     */
    await expect.poll(() => field(page, 'البرنامج'), { timeout: 20_000 }).not.toBe('')

    await page.setInputFiles('#orphanPhoto', sampleImage())
    await page.getByRole('button', { name: 'حفظ' }).click()

    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })
    await expect(page.getByText('حُفظت التعديلات.')).toBeVisible()

    // الصورة وصلت القرص فعلاً لا مجرّد ردٍّ ناجح
    const photo = page.locator('img[src*="storage/orphans"]').first()
    await expect(photo).toBeVisible({ timeout: 20_000 })

    // `naturalWidth` يبقى صفراً حتى يكتمل التنزيل — فالانتظار لا القراءة مرة
    await expect.poll(() => photo.evaluate((e) => e.naturalWidth), { timeout: 20_000 })
      .toBeGreaterThan(0)
  })
})

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
  test('ينشئ ملفاً ثم يرفع مستنداً ويتحقق منه', async ({ page }) => {
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

    const documents = page.locator('.card', { hasText: 'مستندات التحقق' })

    /**
     * «توثيق الملف» معطّل ما دامت المستندات ناقصة.
     *
     * الخادم يرفضه بخطأ، وزرٌّ يُعرض ليُرفض فخٌّ لا واجهة.
     */
    await expect(page.getByRole('button', { name: 'توثيق الملف' })).toBeDisabled()

    // ── رفع ──
    await documents.getByRole('button', { name: 'رفع' }).click()
    await page.locator('[role=dialog] select').selectOption({ index: 1 })
    await page.setInputFiles('#documentFile', samplePdf())
    await page.getByRole('button', { name: 'رفع', exact: true }).last().click()

    await expect(documents.getByText('مرفوع، ينتظر التحقق')).toBeVisible({ timeout: 20_000 })

    // ── تحقّق ──
    await documents.locator('button[title="تحقّق من هذا المستند"]').first().click()
    await expect(documents.getByText('مرفوع، ينتظر التحقق')).toBeHidden({ timeout: 20_000 })

    /**
     * المُتحقَّق منه لا يُحذف: توثيق الملف قائم عليه، والخادم يردّ ٤٢٢.
     * إخفاء الزرّ يمنع نقرةً مصيرها الرفض.
     */
    await expect(documents.locator('button[title="حذف"]')).toHaveCount(0)
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

    // رقم الهوية يُقبل ويُخزَّن بصمةً — وتمريره كان يُسقط الطلب بـ٥٠٠
    await fill(page, 'رقم الهوية', '900112233')

    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 20_000 })
    await expect(page.getByText('أُنشئت الأسرة.')).toBeVisible()

    // ── التحرير: الصفّ يفتح النافذة مملوءة ──
    const row = page.locator('table tbody tr', { hasText: guardian }).first()
    await expect(row).toBeVisible({ timeout: 20_000 })
    await row.locator('td').first().click()

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
    await fill(page, 'البيان', `اختبار ${Date.now().toString().slice(-5)}`)
    await page.setInputFiles('#receiptFile', samplePdf())

    await page.getByRole('button', { name: 'سجّل الصرف' }).click()
    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })
    await expect(page.getByText(/يحتاج اعتماداً/)).toBeVisible()
  })
})

test.describe('الدفعة اليدوية', () => {
  test('تُسجَّل، ويظهر لها زرّ استرداد', async ({ page }) => {
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

    // الاسترداد للناجحة وحدها — `PaymentPolicy::refund` تشترط ذلك
    await expect(page.locator('table tbody button', { hasText: 'استرداد' }).first())
      .toBeVisible({ timeout: 20_000 })
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

    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })
    await expect(page.getByText(/رابط تعيين كلمة المرور/)).toBeVisible()
  })
})

test.describe('الإعدادات', () => {
  test('تُحمَّل من الخادم وتُحفظ، والزرّ صامت حتى يتغيّر شيء', async ({ page }) => {
    await login(page)
    await page.goto('/settings')

    await expect(page.locator('section.card')).toHaveCount(3, { timeout: 20_000 })

    // القيم من قاعدة البيانات لا فارغة
    await expect
      .poll(() => page.evaluate(() => {
        const el = [...document.querySelectorAll('label')]
          .find((x) => x.textContent.trim().startsWith('اسم المنصة'))
        return document.getElementById(el.getAttribute('for')).value
      }), { timeout: 20_000 })
      .not.toBe('')

    /**
     * الزرّ معطّل حتى يتغيّر شيء.
     *
     * حفظٌ بلا تعديل يكتب الصفوف كلها ويمسح ذاكرة الموقع العام بلا سبب —
     * ويجعل سجل النشاط يمتلئ بتغييرات لم تقع.
     */
    const save = page.getByRole('button', { name: 'حفظ الإعدادات' })
    await expect(save).toBeDisabled()

    await page.evaluate(() => {
      const el = [...document.querySelectorAll('label')]
        .find((x) => x.textContent.trim().startsWith('الوصف المختصر'))
      const input = document.getElementById(el.getAttribute('for'))
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set
        .call(input, 'منصة كفالة الأيتام')
      input.dispatchEvent(new Event('input', { bubbles: true }))
    })

    await expect(save).toBeEnabled()
    await save.click()
    await expect(page.getByText('حُفظت الإعدادات.')).toBeVisible({ timeout: 20_000 })
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
  test('مسوّدة ← نشر ← تظهر على الموقع بنصّها كاملاً', async ({ page }) => {
    await login(page)
    await page.goto('/content')

    await expect(page.locator('[role=tab]')).toHaveCount(4)
    await tableReady(page)
    await page.waitForTimeout(1000)

    const title = `مقال اختباري ${Date.now().toString().slice(-6)}`
    await page.getByRole('button', { name: 'مقال جديد' }).click()

    await fill(page, 'العنوان', title)
    await fill(page, 'المقتطف', 'مقتطف قصير.')
    await fill(page, 'النصّ', ['الفقرة الأولى.', 'الفقرة الثانية.', 'والثالثة.'].join('\n\n'))
    await page.setInputFiles('#coverFile', sampleImage())

    // زمن القراءة يُقترح من طول النصّ — رقمٌ يُكتب باليد يكذب أول تحرير
    await expect.poll(() => field(page, 'زمن القراءة')).not.toBe('')

    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })

    await expect(page.locator('table tbody tr', { hasText: title }).first())
      .toBeVisible({ timeout: 20_000 })

    /**
     * المسوّدة لا تظهر على الموقع.
     *
     * وهذا نصف الاختبار: النصف الآخر أنها تظهر بعد النشر — ولو غاب أحدهما
     * لمرّ عيبٌ يجعل كل مقال ظاهراً أو كلّه مخفياً.
     */
    await page.goto('http://127.0.0.1:8000/blog')
    await expect(page.locator('.post-card').first()).toBeVisible({ timeout: 20_000 })
    await expect(page.locator('.post-card', { hasText: title })).toHaveCount(0)

    // ── النشر من نافذة التحرير ──
    await page.goto('/content')
    await expect(page.locator('table tbody tr', { hasText: title }).first())
      .toBeVisible({ timeout: 20_000 })
    await page.locator('table tbody tr', { hasText: title }).first().locator('td').first().click()

    /**
     * النصّ يصل نافذة التحرير.
     *
     * كان `PostResource` يحجب `body` عن كل ما ليس `*.show`، فتفتح النافذة
     * بحقلٍ فارغ وتحفظه فارغاً: من فتح مقالاً ليغيّر حالته فقد نصّه كلّه.
     */
    await expect.poll(async () => ((await field(page, 'النصّ')) ?? '').length)
      .toBeGreaterThan(20)

    await selectValue(page, 'الحالة', 'published')
    await page.getByRole('button', { name: 'حفظ' }).click()
    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })

    // ── وعلى الموقع العام ──
    await page.goto('http://127.0.0.1:8000/blog')
    const card = page.locator('.post-card', { hasText: title })
    await expect(card).toHaveCount(1, { timeout: 20_000 })

    await card.locator('a').first().click()
    await page.waitForSelector('#postDetail:not([aria-busy])', { timeout: 20_000 })
    await expect(page.locator('h1')).toHaveText(title)

    /**
     * ثلاث فقرات لا واحدة.
     *
     * مربّع النصّ يُطبّع أسطره إلى CRLF، فيصير فاصل الفقرات سطرين غير
     * متجاورين — ولا يطابقهما تعبيرٌ يبحث عن `\n` مرتين متتاليتين، فتنهار
     * المقالة إلى فقرة واحدة طويلة بلا سبب ظاهر.
     */
    await expect(page.locator('.prose p')).toHaveCount(3)
    expect(await page.locator('.frame__img').evaluate((e) => e.naturalWidth)).toBeGreaterThan(0)
  })

  test('سؤال شائع يُضاف ويُحرَّر ويُحذف', async ({ page }) => {
    await login(page)
    await page.goto('/content')
    await page.locator('[role=tab]', { hasText: 'الأسئلة' }).click()

    /**
     * ننتظر جدول الأسئلة نفسه لا أي جدول.
     *
     * تبديل اللسان يُفرغ الصفوف ثم يجلب، وبين النقرة وإعادة الرسم تبقى
     * صفوف اللسان السابق معروضة بلا أي مؤشّر تحميل. فالانتظار العام يمرّ
     * فوق بيانات لسانٍ آخر.
     */
    await expect(page.locator('table thead')).toContainText('السؤال', { timeout: 20_000 })
    await tableReady(page)

    const question = `سؤال اختباري ${Date.now().toString().slice(-5)}؟`

    await page.getByRole('button', { name: 'إضافة' }).click()
    await fill(page, 'السؤال', question)
    await fill(page, 'الإجابة', 'جواب اختباري.')
    await page.getByRole('button', { name: 'حفظ' }).click()

    await expect(page.locator('[role=dialog]')).toBeHidden({ timeout: 25_000 })

    // الصفّ نفسه لا عددُ الصفوف: العدد يتغيّر بين التشغيلات، والصفّ لا
    const row = page.locator('table tbody tr', { hasText: question })
    await expect(row).toHaveCount(1, { timeout: 20_000 })

    // التحرير يفتح النافذة مملوءة
    await row.first().locator('td').first().click()
    expect(await field(page, 'السؤال')).toBe(question)
    await page.keyboard.press('Escape')
    await expect(page.locator('[role=dialog]')).toBeHidden()

    page.once('dialog', (d) => d.accept())
    await row.first().locator('button[title="حذف"]').click()

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

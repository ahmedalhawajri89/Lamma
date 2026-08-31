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

async function login(page) {
  await page.goto('/login')
  await page.getByLabel('البريد الإلكتروني').fill(ADMIN.email)
  await page.getByLabel('كلمة المرور', { exact: true }).fill(ADMIN.password)
  await page.getByRole('button', { name: 'دخول' }).click()
  await page.waitForURL('**/dashboard')
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

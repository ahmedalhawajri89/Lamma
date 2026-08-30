// جولة على شاشات اللوحة — لقطات حيّة من بيانات حقيقية.
import { chromium } from 'playwright'

const APP = 'http://localhost:5173'
const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1500, height: 1000 }, locale: 'ar' })

await page.goto(`${APP}/login`)
await page.waitForTimeout(800)
await page.screenshot({ path: 'shots/t1-login.png' })

await page.getByLabel('البريد الإلكتروني').fill('admin@kafala.org')
await page.getByLabel('كلمة المرور', { exact: true }).fill('password')
await page.getByRole('button', { name: 'دخول' }).click()
await page.waitForURL('**/dashboard')
await page.waitForTimeout(4000)
await page.screenshot({ path: 'shots/t2-dashboard.png', fullPage: true })

const tour = [
  ['/orphans', 't3-orphans'],
  ['/orphans/2', 't4-orphan-detail'],
  ['/sponsorships', 't5-sponsorships'],
  ['/payments', 't6-payments'],
  ['/disbursements', 't7-disbursements'],
  ['/reports', 't8-reports'],
  ['/messages', 't9-messages'],
  ['/families', 't10-families'],
  ['/sponsors', 't11-sponsors'],
  ['/users', 't12-users'],
]

for (const [path, name] of tour) {
  await page.goto(`${APP}${path}`)
  await page.waitForTimeout(2600)
  await page.screenshot({ path: `shots/${name}.png`, fullPage: path.includes('/orphans/') })
  console.log('✓', path)
}

await browser.close()
console.log('done')

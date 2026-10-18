import { execSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

/**
 * قاعدة اختبارات نظيفة قبل كل تشغيل.
 *
 * كانت الاختبارات تكتب في قاعدة التطوير: أيتام «ليان12345»، و«موظف اختباري»،
 * و«مقال اختباري» منشور على الموقع، ودفعات مكررة. وتراكمها جعل اختباراتٍ
 * تسقط لأن البيانات نفدت (كل أيتام البلد لهم تقرير هذا الربع). هنا تُبنى
 * قاعدة `kafala_e2e` من الصفر، والتطوير لا يُمسّ.
 */
const API_DIR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../api')
const env = { ...process.env, APP_ENV: 'e2e' }
const run = (cmd) => execSync(cmd, { cwd: API_DIR, env, stdio: 'inherit' })

export default function globalSetup() {
  // القاعدة نفسها، إن لم تكن موجودة
  run(`php "${path.join(path.dirname(fileURLToPath(import.meta.url)), 'create-database.php')}"`)
  run('php artisan migrate:fresh --seed --force')
  run('php artisan metrics:rollup --all')
}

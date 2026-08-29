# حالة العمل — نقطة الاستئناف

> آخر تحديث: **٢٩ أغسطس ٢٠٢٦**
> الفرع: `main` · آخر عمل مثبَّت: طبقة النطاق كاملة + بداية طبقة الـ API

---

## القرار المعماري (مُتَّفق عليه ومُنفَّذ)

**Laravel 12 API + MySQL ← → Vue 3 SPA، والموقع العام يبقى كما هو ويُغذّى من الـ API.**

رُفض المونوليث (Blade/Inertia) لأنه يعني إعادة كتابة ٤٥٥٦ سطر CSS و١٥ صفحة مصمّمة،
ورُفض Next.js لأنه يُلغي Laravel و Eloquent و Sanctum من المشروع وهي أقوى ما في المهارات.

نطاق اللوحة المطلوب: **لوحة إدارية + تحليلات ومخططات + بوابة الكافل + مصادقة وصلاحيات** — الأربعة.

---

## البيئة (متحقَّق منها وتعمل)

| | |
|---|---|
| PHP | 8.2.12 — من `D:\xampp\php` |
| Composer | 2.9.3 |
| قاعدة البيانات | MariaDB 10.4.32 عبر XAMPP على `D:\xampp` · مستخدم `root` بلا كلمة مرور |
| القواعد | `kafala` و `kafala_test` — مُنشأتان بـ `utf8mb4_unicode_ci` |
| Node / npm | 24.13.0 / 11.6.2 |
| عميل mysql | `/d/xampp/mysql/bin/mysql.exe` (أضف `--default-character-set=utf8mb4` وإلا ظهرت العربية `????`) |

---

## ✅ ما اكتمل

### 1. إعادة الهيكلة إلى monorepo
الموقع الساكن انتقل بـ `git mv` إلى `web/` — **بلا تعديل سطر واحد** من HTML أو CSS أو JS.

```
kafala-orphan-sponsorship/
├── api/     Laravel 12 + MySQL
├── web/     الموقع العام كما كان
├── docs/    DATABASE.md · ARCHITECTURE.md · PROGRESS.md
└── admin/   ← لم يُنشأ بعد
```

### 2. قاعدة البيانات — ٤٤ جدولاً، مُهاجَرة ومبذورة وتعمل
كل التفاصيل في [`docs/DATABASE.md`](DATABASE.md). الأبرز، ومُتحقَّق منه في المحرّك:

- **العمود المُولَّد `sponsorships.active_key`** يعطي قيداً جزئياً حقيقياً: يمنع كفالتين
  حيّتين لنفس الطفل ويسمح بالعودة بعد الإلغاء. مُتحقَّق منه بـ `SHOW CREATE TABLE`.
- `payments.idempotency_key` فريد — الخصم المزدوج مستحيل في المحرّك.
- `UNIQUE(orphan_id, period_year, period_quarter)` على `reports`.
- فهرس `FULLTEXT` على `orphans` يعمل على MariaDB.
- `date_of_birth` لا `age` · كل مبلغ `DECIMAL` بعملته وسعر صرفه.

### 3. طبقة النطاق
- **١٥ enum** في `api/app/Enums` بتسميات عربية عبر `HasLabel`.
- **٢٩ موديل** في `api/app/Models` بعلاقاتها ونطاقاتها.
- `SponsorshipObserver` يبقي `orphans.funded_percent` صادقاً.
- `Model::shouldBeStrict()` خارج الإنتاج.
- `config/kafala.php` — العملة الأساس، توقيت العرض، الفلفل، حدود الرفع، التصفيح.

### 4. البذور — تعمل بالكامل (‏`php artisan migrate:fresh --seed` ≈ ٦٠ ثانية)

| | |
|---|---|
| أيتام | 148 — **الثمانية الأصليون من `data.js` بأسمائهم وقصصهم ونسب تمويلهم حرفياً** |
| كفلاء | 180 · كفالات 166 · مدفوعات 1741 |
| صرف | 5127 · تقارير 263 · زيارات 332 · رسائل 120 |

**حسابات الدخول** (كلمة المرور للجميع `password`):
`admin@kafala.org` · `accountant@kafala.org` · `supervisor0..3@kafala.org` · `sponsor@kafala.org`

**التحقق الذي يثبت أن الأرقام صارت مُشتقّة لا مكتوبة:**

```sql
SELECT c.slug, ROUND(SUM(d.amount_base)*100/(SELECT SUM(amount_base) FROM disbursements
       WHERE approved_at IS NOT NULL),1) pct
FROM disbursements d JOIN disbursement_categories c ON c.id=d.category_id
WHERE d.approved_at IS NOT NULL GROUP BY c.id, c.slug ORDER BY pct DESC;
```

يخرج: **38 · 26.9 · 19.1 · 8.9 · 7.0** ونسبة الوصول للطفل **93٪** — مطابقاً تماماً
لمصفوفة `SPENDING` و `PROOF.toChild` المكتوبتين يدوياً في `data.js`، لكن محسوباً
من خمسة آلاف صف صرف معتمَد.

### 5. إعداد الـ API (مكتمل)
- `config/cors.php` — `supports_credentials: true` وأصول صريحة (الكوكيز تمنع `*`).
- `bootstrap/app.php` — `statefulApi()` و aliases الأدوار، واستجابة JSON عربية موحّدة
  لأخطاء 401/403/404/422/429 بدل صفحات HTML التي تكسر الواجهة.
- **التوقيت:** ‏`config/app.php` في Laravel 12 يثبّت `'UTC'` ولا يقرأ `APP_TIMEZONE` —
  أُصلح ليقرأ من البيئة. القرار: **التخزين بـ UTC والعرض بـ `kafala.display_timezone`
  (‏`Asia/Gaza`)**.

---

## 🔨 نقطة التوقف بالضبط

كُتب `api/app/Http/Controllers/Api/V1/Auth/AuthController.php` كاملاً
(‏`login` / `register` / `me` / `logout`) وهو سليم نحوياً، **لكنه يشير إلى ثلاثة أصناف
لم تُنشأ بعد**، وهذا أول ما يُكتب غداً:

1. `app/Http/Requests/Auth/LoginRequest.php` — ويلزمه ثلاث دوال يستدعيها المتحكّم:
   `credentials()` و `ensureIsNotRateLimited()` و `hitRateLimiter()` و `clearRateLimiter()`
   (تحديد المعدّل بمفتاح `email|ip`).
2. `app/Http/Requests/Auth/RegisterRequest.php`
3. `app/Http/Resources/UserResource.php` — يجب أن يُخرج الأدوار والصلاحيات ليقرأها
   الموجّه في Vue، وألا يُسرّب `sponsors.internal_notes`.

`routes/api.php` **لم يُلمس بعد** (ما يزال المسار الافتراضي `api/user` وحده)، فلا شيء
مكسور الآن — المشروع يقلع ويعمل.

---

## 📋 الخطة المتبقية بالترتيب

### المرحلة أ — إكمال الـ API
1. الثلاثة أعلاه، ثم `routes/api.php` بمجموعاته الأربع:
   `public` (بلا مصادقة) · `auth` · `portal` (الكافل) · `admin` (الموظفون).
2. **Policies**: `OrphanPolicy` (تقيّد المشرف الميداني بـ `staff_profiles.assigned_country_id`)
   و `SponsorshipPolicy` و `ReportPolicy` و `PaymentPolicy`.
3. **Actions**: `CreateSponsorship` (تنسخ سعر البرنامج) · `CancelSponsorship` ·
   `RecordPayment` (بمفتاح idempotency) · `ApproveDisbursement` · `PublishReport` · `VerifyOrphan`.
4. **Resources** لكل موديل يُعاد في استجابة.
5. **`MetricsService`** — استعلامات التحليلات، تقرأ من `daily_metrics` لا من `payments`.
6. **أوامر مجدولة**: `metrics:rollup` (ليلي) و `billing:run` (يومي، يقرأ `idx_sponsorships_due`).

### المرحلة ب — لوحة Vue 3
`admin/` بـ Vite + Vue 3 + Pinia + Vue Router + Tailwind 4 + Chart.js، RTL،
وبنفس توكنات `web/assets/css/tokens.css` حتى لا تبدو اللوحة من مشروع آخر.
الشاشات: نظرة عامة · الأيتام · الأسر · الكفلاء · الكفالات · المدفوعات · الصرف ·
التقارير · الزيارات · الرسائل · المحتوى · المستخدمون · سجل النشاط · الإعدادات.

### المرحلة ج — ربط الموقع العام
استبدال `web/assets/js/modules/data.js` بطبقة `fetch` **بنفس أسماء الصادرات**
(‏`ORPHANS`, `PROGRAMS`, `SPENDING`, `PROOF`…) فلا تتغيّر أي وحدة أخرى ولا سطر CSS.
وإعادة بناء `web/dashboard.html` — وهي أضعف صفحة في المشروع اليوم: كل صفوفها HTML ثابت.

### المرحلة د — الجودة والنشر
Pest · Larastan مستوى 6 · Vitest · Playwright · GitHub Actions ·
و`vercel.json` (‏`web/` صار في مجلد فرعي — **يلزم تعديل Root Directory في لوحة Vercel**).

---

## ⚠️ ما يجب الانتباه له

- **النشر على Vercel سيتعطّل** حتى يُضبط Root Directory على `web` أو يُضاف `vercel.json`.
  الرابط الحيّ `kafala-orphan-sponsorship.vercel.app` لم يُعالج بعد.
- `GUARDIAN_ID_PEPPER` في `.env` ما يزال `change-me-in-production`.
- أداة Bash في هذه الجلسة **تقصّ الأوامر الطويلة** — الـ heredoc الكبير يفشل بـ
  `unexpected EOF`. الحل المستعمَل: كتابة سكربت مولّد في مجلد scratchpad ثم تشغيله.
- لا سطر `Co-Authored-By` في أي commit.

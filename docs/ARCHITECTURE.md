# معمارية المشروع — كفالة

## 1. الشكل العام

```
kafala-orphan-sponsorship/
│
├── api/                        Laravel 12 · PHP 8.2 · MySQL · Sanctum
│   ├── app/
│   │   ├── Models/             Eloquent + العلاقات + الـ scopes
│   │   ├── Http/
│   │   │   ├── Controllers/Api/    نحيفة — لا منطق أعمال
│   │   │   ├── Requests/           كل تحقق من المدخلات هنا
│   │   │   ├── Resources/          شكل الاستجابة (لا نُسرّب أعمدة)
│   │   │   └── Middleware/
│   │   ├── Actions/            منطق الأعمال — كل صنف فعل واحد
│   │   ├── Policies/           الصلاحيات على مستوى السجل
│   │   ├── Observers/          تحديث funded_percent وسجل النشاط
│   │   ├── Enums/              backed enums بدل النصوص السحرية
│   │   └── Console/Commands/   billing:run · metrics:rollup · kafala:check
│   ├── database/{migrations,factories,seeders}
│   ├── routes/{api,web,console}.php
│   ├── tests/{Feature,Unit}    Pest — على MySQL لا SQLite
│   │
│   └── public/                 ← كل ما يصل المتصفح
│       ├── index.php           واجهة لارافل الأمامية
│       ├── *.html              الموقع العام: ١٧ صفحة بلا إطار وبلا بناء
│       ├── assets/             CSS و JS و صور الموقع
│       │   └── js/modules/data.js   بذرة + طبقة ترطيب من الـ API
│       └── admin-app/          ناتج بناء اللوحة — مسارها في المتصفح /admin
│
├── admin/                      مصدر اللوحة — Vue 3 · Vite · Pinia · Chart.js
│   ├── src/{api,stores,views,components,composables,utils}
│   └── tests/{unit,e2e}        Vitest · Playwright
│
└── docs/                       DATABASE.md · ARCHITECTURE.md · PROGRESS.md
```

## 2. لماذا هذا التقسيم

**تطبيق واحد يُنشر، وثلاث واجهات تقرأ من API واحد.**

| البديل | لماذا رُفض |
|---|---|
| مونوليث Blade | يعني إعادة كتابة 4,556 سطر CSS و15 صفحة مصمّمة بعناية. خسارة صافية بلا مكسب. |
| Inertia | يلغي إمكانية تطبيق جوال لاحقاً على الـ API نفسه، ويربط كل شاشة بجلسة الخادم. |
| Next.js Full-stack | يُلغي Laravel و Eloquent و Sanctum — أقوى ما في المهارات — من المشروع. |

والموقع العام يبقى **HTML ساكناً** لا Blade: لا يمرّ بمحرّك قوالب، ويُخدَم من
القرص مباشرةً على خادم حقيقي، ويبقى مفهرساً في محركات البحث كما هو.

**ولماذا كل شيء تحت `api/public` وليس في مجلدات متجاورة؟**
لأن الأصل الواحد يُسقط طبقةً كاملة من التعقيد: لا CORS، ولا كوكيز عابرة
للأصول، و`connect-src 'self'` في سياسة أمن المحتوى. وثمنه أن الموقع لم يعد
يُنشر وحده على CDN — وهو ثمن مقبول ما دام النشر واحداً على أي حال.

## 3. المصادقة

**Sanctum بنمط SPA (كوكيز، لا توكنات في `localStorage`).**

```
المتصفح  ──GET /sanctum/csrf-cookie──>  الخادم
         ──POST /api/v1/auth/login ──>
         <──── كوكي جلسة HttpOnly ────
```

وبعد الدمج صار كل ذلك **على الأصل نفسه**، فالكوكي same-site لا cross-site
ولا يعتمد على سياسة المتصفح في كوكيز الطرف الثالث. ويبقى CORS مضبوطاً
لخادم Vite في التطوير وحده.

لماذا الكوكيز لا التوكنات: توكن في `localStorage` يُقرأ بأي ثغرة XSS. الكوكي `HttpOnly` + `SameSite=Lax` لا يُقرأ من JS إطلاقاً. `stateful` domains مضبوطة في `config/sanctum.php`.

**الأدوار:** `admin` · `accountant` · `field_supervisor` · `sponsor` — عبر `spatie/laravel-permission`، مع Policies لكل موديل تُطبَّق على مستوى السجل (المشرف الميداني يرى أيتام بلده فقط).

## 4. طبقات الطلب في الـ API

```
Route  →  Middleware (auth:sanctum, role)  →  FormRequest (تحقق)
       →  Controller (نحيف)  →  Action (منطق الأعمال)
       →  Model / Query      →  Resource (شكل الاستجابة)
```

**القواعد:**
- المتحكّم لا يحوي `if` لمنطق أعمال — يستدعي Action ويعيد Resource.
- لا `$request->all()` أبداً — فقط `$request->validated()`.
- لا استعلام في Blade/Resource — كل ما يُحتاج يُحمّل بـ `with()` مسبقاً.
- كل نقطة نهاية تُرجع بيانات قائمة تكون مُصفّحة (paginated) إجبارياً — لا `->get()` مفتوحة.

## 5. معايير البناء المطبَّقة

| المعيار | الأداة |
|---|---|
| تنسيق PHP | Laravel Pint (PSR-12) |
| تحليل ساكن | PHPStan / Larastan — المستوى 5، نظيف |
| اختبارات الخادم | Pest — Feature + Unit |
| تنسيق JS/Vue | ESLint + Prettier |
| اختبارات الواجهة | Vitest (وحدات) + Playwright (E2E) |
| CI | GitHub Actions: pint → phpstan → pest → vitest → build |
| الهجرات | لا تعديل هجرة منشورة — هجرة جديدة دائماً |
| البذور | Factories + Seeders تنتج بيانات واقعية بالعربية |

## 6. الأمان

- `HttpOnly` + `Secure` + `SameSite` على كوكي الجلسة.
- CSRF عبر Sanctum على كل طلب مُغيِّر للحالة.
- تحديد المعدّل (`throttle`) على `/login` و`/register` و`/forgot-password`.
- Policies على كل موديل — لا اعتماد على إخفاء الزر في الواجهة.
- رفع الملفات: تحقق من `mime` الحقيقي لا الامتداد، وتخزين خارج `public/`، والتقديم عبر مسار محمي بـ Policy.
- لا بيانات بطاقة تُخزَّن (انظر `DATABASE.md` §7).
- `activity_log` يسجّل كل تغيير على السجلات الحساسة.
- ترويسات الأمان (‏`frame-ancestors`, `HSTS`) من الخادم — لا من `<meta>`، كما هو موثّق في README الحالي.

## 7. الموقع العام بعد الربط

`public/assets/js/modules/data.js` يحتفظ **بنفس أسماء الصادرات** (`ORPHANS`,
`PROGRAMS`, `SPENDING`, `PROOF`…) لكنها تُملأ من الـ API بدل أن تكون ثابتة —
والصادرات `let` لا `const` لأن ارتباطات ES modules حيّة. لا وحدة أخرى تتغيّر،
ولا سطر CSS واحد يُلمس؛ وهذا بالضبط ما كان تعليق الملف يَعِد به منذ اليوم الأول.

والقيم المكتوبة تبقى بذرةً تعمل حين يخفق الـ API، فتعرض الصفحة محتوى صحيحاً
بدل شاشة فارغة.

## 8. توجيه المتصفح

| المسار | من يخدمه |
|---|---|
| `/assets/…` · `/admin-app/…` · `/storage/…` | ملفات ساكنة من القرص |
| `/` · `/about` · `/orphans` … | `routes/web.php` → ملف HTML بعنوان نظيف |
| `/admin` وما تحته | `routes/web.php` → مدخل الـ SPA، ثم يتولّى موجّه Vue |
| `/api/v1/…` | `routes/api.php` |
| أي شيء آخر | `404.html` بحالة 404 |

> `post.html` و`reset-password.html` تقرآن حالتهما من سلسلة الاستعلام
> (`?slug=` و`?token=&email=`) لا من المسار: الموقع ملفات ساكنة، وكل مسار
> جديد يعني قاعدة توجيه جديدة على كل خادم يُنشَر عليه.

**ثلاثة مزالق موثّقة في الكود** لأن أثرها صامت:

1. **`response()->file()` لا يرسل جسماً مع حالة غير ناجحة** — فتخرج صفحة ٤٠٤
   برأس صحيح وجسم فارغ. نقرأ الملف بـ `file_get_contents` بدلاً منه.
2. **مجلد بناء اللوحة اسمه `admin-app` لا `admin`** — لو تطابق مع المسار
   لخدمته خوادم الويب ساكناً قبل لارافل، وعُرضت صفحة الموقع العام مكان اللوحة.
3. **`[hidden]` لا يعمل بلا `!important`** — قاعدة المتصفح أولويتها صفر، وأي
   `display` نكتبه (‏`.btn` مثلاً) يهزمها، فيبقى العنصر ظاهراً و`el.hidden`
   بلا أثر. القاعدة الصريحة في `base.css`.

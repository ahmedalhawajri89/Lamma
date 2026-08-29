# معمارية المشروع — كفالة

## 1. الشكل العام

```
kafala-orphan-sponsorship/
│
├── api/                Laravel 12 · PHP 8.2 · MySQL · Sanctum
│   ├── app/
│   │   ├── Models/                 Eloquent + العلاقات + الـ scopes
│   │   ├── Http/
│   │   │   ├── Controllers/Api/    نحيفة — لا منطق أعمال
│   │   │   ├── Requests/           كل تحقق من المدخلات هنا
│   │   │   ├── Resources/          شكل الاستجابة (لا نُسرّب أعمدة)
│   │   │   └── Middleware/
│   │   ├── Actions/                منطق الأعمال — كل صنف فعل واحد
│   │   ├── Policies/               الصلاحيات على مستوى السجل
│   │   ├── Observers/              تحديث funded_percent وسجل النشاط
│   │   ├── Enums/                  PHP 8.1 backed enums بدل النصوص السحرية
│   │   └── Console/Commands/       billing:run · metrics:rollup
│   ├── database/{migrations,factories,seeders}
│   ├── routes/api.php
│   └── tests/{Feature,Unit}        Pest
│
├── admin/              Vue 3 · Vite · Pinia · Vue Router · Tailwind 4 · Chart.js
│   ├── src/
│   │   ├── api/                    طبقة HTTP واحدة (axios + interceptors)
│   │   ├── stores/                 Pinia — auth, orphans, sponsorships…
│   │   ├── views/                  صفحات الموجّه
│   │   ├── components/             مكونات قابلة لإعادة الاستخدام
│   │   ├── composables/            useTable, usePagination, useToast
│   │   └── charts/                 أغلفة Chart.js
│   ├── tests/unit/                 Vitest
│   └── tests/e2e/                  Playwright
│
├── web/                الموقع العام — HTML/CSS/JS كما هو، بلا إطار
│   └── assets/js/modules/data.js → يصبح طبقة fetch
│
└── docs/               DATABASE.md · ARCHITECTURE.md · API.md
```

## 2. لماذا ثلاثة تطبيقات لا واحد

| البديل | لماذا رُفض |
|---|---|
| مونوليث Blade | يعني إعادة كتابة 4,556 سطر CSS و15 صفحة مصمّمة بعناية. خسارة صافية بلا مكسب. |
| Inertia | يربط الواجهة بالخادم في نفس النشر، ويلغي إمكانية تطبيق جوال لاحقاً على نفس الـ API. |
| Next.js Full-stack | يُلغي Laravel و Eloquent و Sanctum — أقوى ما لديك — من المشروع. |

الفصل يعطي: نشراً مستقلاً لكل جزء، و API واحداً يخدم الموقع واللوحة وأي تطبيق جوال مستقبلاً، وموقعاً عاماً يبقى ساكناً سريعاً مفهرساً في محركات البحث.

## 3. المصادقة

**Sanctum بنمط SPA (كوكيز، لا توكنات في `localStorage`).**

```
admin.kafala.test  ──GET /sanctum/csrf-cookie──>  api.kafala.test
                   ──POST /login (credentials)──>
                   <──── HttpOnly session cookie ────
```

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
| تحليل ساكن | PHPStan / Larastan — المستوى 6 |
| اختبارات الخادم | Pest — Feature + Unit |
| تنسيق JS/Vue | ESLint + Prettier |
| اختبارات الواجهة | Vitest (وحدات) + Playwright (E2E) |
| فحص الأنواع | `vue-tsc` على ملفات `.ts` |
| CI | GitHub Actions: pint → phpstan → pest → eslint → vitest → build |
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

`web/assets/js/modules/data.js` يحتفظ **بنفس أسماء الصادرات** (`ORPHANS`, `PROGRAMS`, `SPENDING`, `PROOF`…) لكنها تُملأ من الـ API بدل أن تكون ثابتة. لا وحدة أخرى تتغيّر، ولا سطر CSS واحد يُلمس — وهذا بالضبط ما كان تعليق الملف يَعِد به منذ اليوم الأول.

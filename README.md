# كفالة — Kafala

<div dir="rtl">

منصة كفالة أيتام بالعربية (RTL أولاً)، مبنية بـ HTML و CSS و JavaScript خالصة —
بلا إطارات عمل، وبلا مكتبات خارجية، وبلا خطوة بناء.

الفكرة التي بُني حولها المشروع: **الشفافية**. لا تبرع مجهول المصير، بل طفل باسمه
وقصته وملفه الموثّق، وتقرير مصوّر كل ثلاثة أشهر، وميزانية مدقّقة منشورة للجميع.

</div>

---

## 🎨 The design

A **warm, humane identity** built around clay and amber on a sand-cream canvas —
deliberately away from the medical-green that most charity sites default to.

| Role | Token | Value |
|---|---|---|
| Primary | `--color-brand` | `#c2410c` — terracotta |
| Secondary | `--color-accent` | `#d97706` — amber |
| Growth | `--color-growth` | `#4d7c0f` — olive |
| Canvas | `--color-canvas` | `#fbf7f0` — warm sand |
| Ink | `--color-text` | `#1c1917` |

**The logo** is a custom SVG: two hands — one clay, one amber — cradling a child in
olive. It draws the literal meaning of *kafala* (to take someone under your hand)
using all three brand colours, and stays legible from 120px down to 16px. Shipped as
`logo.svg` (716 B), `favicon.svg`, and a PNG touch icon.

**The offset frame** is the signature: generous corner radii with a thin brand-coloured
outline shifted diagonally behind the image. Editorial, contemporary, and quiet —
it adds depth without ornament.

**The hero is a cinematic slider**: full-bleed imagery with a directional scrim,
slow Ken Burns zoom, per-slide copy, thumbnail navigation and an autoplay progress
bar — with a visible pause control, because WCAG 2.2.2 requires any auto-advancing
content to be stoppable.

**Typography** pairs *Readex Pro* (display) with *IBM Plex Sans Arabic* (body) and
*Aref Ruqaa* for scriptural quotations — a distinctly Arabic pairing rather than the
ubiquitous Cairo/Tajawal default. All sizes are fluid (`clamp`), so nothing snaps at
breakpoints.

<div dir="rtl">

## ✨ ما الذي يميّز هذا المشروع

- **حاسبة الأثر** — يسحب الزائر شريطاً فيرى فوراً ما الذي يوفّره مبلغه شهرياً.
- **حلقة الشفافية** — رسم SVG خالص لتوزيع الإنفاق، يُرسم متحركاً عند ظهوره، بلا مكتبة رسم.
- **هيرو سينمائي** — أربع شرائح بصور ملء العرض، تقريب بطيء، مصغّرات،
  شريط تقدّم، وزر إيقاف ظاهر (معيار WCAG 2.2.2).
- **سلايدر مكتوب من الصفر** — مبني على `scroll-snap` الأصلي مع سحب بالفأرة وأزرار
  ونقاط وشريط تقدم ودعم كامل للكيبورد. لا Swiper ولا غيره.
- **فلترة حيّة للأيتام** — بحث وترتيب وفلترة حسب البرنامج والدولة، وتتصل بالرابط
  (`orphans.html?program=education`) فتصل من صفحة البرامج محمّلة مسبقاً.
- **أنيميشن احترافي** — كشف عند التمرير، عدّادات، كشف العنوان كلمةً كلمة، ميلان
  ثلاثي الأبعاد مع الفأرة، شريط لانهائي — كله يحترم `prefers-reduced-motion`.
- **١٥ صفحة** متسقة، تُبنى كلها من مصدر بيانات واحد.

## 🔒 الأمان والخصوصية

مشروع واجهة أمامية، لذا الأمان هنا يعني ما يمكن ضبطه في المتصفح:

- **CSP** في كل صفحة: `script-src 'self'` و `object-src 'none'` — لا سكربت خارجي يعمل.
- **صفر معالجات inline** (`onclick`) — كل الأحداث مربوطة من JS، فتبقى السياسة صارمة.
- **لا `innerHTML` مع بيانات متغيّرة** — كل نص يمرّ بـ `escapeHtml` أو `textContent`.
- **تحقق من البطاقة بخوارزمية Luhn** وتحقق من صلاحية التاريخ قبل الإرسال.
- **لا تخزين لبيانات الدفع** إطلاقاً، ولا إرسال عبر `GET`، مع `autocomplete` الصحيح.
- **`rel="noopener noreferrer"`** على كل رابط خارجي، و`referrer-policy` مضبوطة.

> ملاحظة: `frame-ancestors` تعمل كترويسة HTTP فقط ولا تُقبل في `<meta>`،
> لذا تُضاف عند النشر من إعدادات الاستضافة لا من الصفحة.

## ♿ الوصولية

- رابط «تخطَّ إلى المحتوى» في كل صفحة.
- بنية عناوين صحيحة، و`aria-current` للتنقل، و`aria-expanded` للقوائم والأكورديون.
- حبس التركيز داخل النوافذ المنبثقة وإعادته لمصدره عند الإغلاق.
- تبويبات تعمل بالأسهم وفق نمط ARIA القياسي.
- `:focus-visible` واضح على كل عنصر تفاعلي.
- رسائل النماذج عبر `role="alert"` و`aria-live` — لا `alert()` معطِّلة.
- احترام كامل لـ `prefers-reduced-motion`.
- بلا تمرير أفقي من 320px إلى 1440px (مُختبَر آلياً).

</div>

## 🗂 Structure

```
kafala-orphan-sponsorship/
├── index.html              الرئيسية
├── about.html              من نحن
├── programs.html           برامج الكفالة
├── orphans.html            قائمة الأيتام + فلترة
├── orphan.html             تفاصيل حالة  (?id=KFL-1042)
├── transparency.html       الشفافية والتقارير
├── checkout.html           إتمام الكفالة
├── thank-you.html          التأكيد
├── dashboard.html          لوحة الكافل
├── login.html · register.html
├── blog.html · faq.html · contact.html · 404.html
│
└── assets/
    ├── css/                طبقات مرتّبة — تُحمَّل بهذا الترتيب
    │   ├── tokens.css      المتغيرات: لون، طباعة، مسافات، ظلال، حركة
    │   ├── base.css        إعادة ضبط + طباعة + وصولية
    │   ├── layout.css      الحاويات والشبكات والأقسام
    │   ├── site.css        الهيدر والتنقل والفوتر
    │   ├── components.css  المكونات القابلة لإعادة الاستخدام
    │   ├── sections.css    الأقسام الكبيرة (هيرو، رحلة، حاسبة، شفافية)
    │   └── pages.css       أنماط خاصة بصفحات بعينها
    │
    ├── js/
    │   ├── main.js         نقطة الدخول الوحيدة
    │   └── modules/
    │       ├── data.js     مصدر البيانات الوحيد
    │       ├── layout.js   الهيدر والفوتر لكل الصفحات
    │       ├── render.js   قوالب البطاقات
    │       ├── home.js     تعبئة الأقسام من البيانات
    │       ├── features.js الحاسبة، الفلترة، صفحات التفاصيل
    │       ├── hero.js     سلايدر الهيرو السينمائي
    │       ├── slider.js   السلايدر والشريط المتحرك
    │       ├── motion.js   الكشف، العدّادات، الميلان
    │       ├── ui.js       أكورديون، تبويبات، نوافذ، تنبيهات
    │       ├── forms.js    التحقق ومعالجة المدخلات الحساسة
    │       └── utils.js    أدوات مشتركة
    │
    ├── img/
    └── vendor/fontawesome/
```

<div dir="rtl">

### قواعد الهيكلة

- **لا لون ولا مقاس ثابت خارج `tokens.css`.** كل قيمة تصميمية تبدأ من هناك.
- **البيانات في `data.js` فقط.** لا يتكرر اسم يتيم ولا سعر برنامج في أي صفحة.
- **الهيدر والفوتر يُبنيان من `layout.js`** — مصدر واحد بدل تكرارهما في ١٥ ملفاً.
- **خصائص منطقية الاتجاه** (`inset-inline`, `padding-block`) في كل مكان، فيعمل
  التخطيط في RTL و LTR دون تعديل.

</div>

<div dir="rtl">

## 🖼 الصور

كل الصور من [Pexels](https://www.pexels.com/license/) — رخصة تجارية حرة بلا نسب مطلوب.
مقصوصة على الوجوه عبر معاملات CDN، ومحفوظة **WebP بجودة 82** بالمقاسات النهائية
لكل خانة (‏@2x)، فلا يُحمَّل بكسل واحد زائد. مجلد الصور كله **700KB**.

اختيار الصور محكوم بسياسة الموقع نفسها: أطفال **يبتسمون ويدرسون ويلعبون** —
لا صور بؤس ولا دموع. وصور الكفلاء ليست صوراً فوتوغرافية بل **أفاتار حرفية**،
لأن استعمال صورة شخص حقيقي كـ«كافل» في بيانات تجريبية إيهامٌ لا داعي له.

</div>

## 🚀 Getting started

```bash
npx serve .
# ثم افتح http://localhost:3000
```

<div dir="rtl">

يحتاج المشروع خادماً محلياً لأن JavaScript مكتوب بوحدات ES Modules،
والمتصفح يمنع تحميلها عبر `file://` لأسباب أمنية. أي خادم ساكن يكفي —
أو إضافة **Live Server** في VS Code.

عند النشر على GitHub Pages أو Netlify يعمل مباشرة بلا أي إعداد.

</div>

## 🛠 Stack

**HTML5 · CSS3 · JavaScript (ES Modules)** — no framework, no build step, no runtime
dependency. Font Awesome is vendored locally; only the web fonts come from a CDN.

---

<div dir="rtl">

## 👤 المطوّر

**أحمد الحواجري** — مطوّر ومصمم واجهات · أتمتة بيانات
الخليل، فلسطين

</div>

## 📄 License

[MIT](LICENSE)

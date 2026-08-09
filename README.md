# Kafala — Orphan Sponsorship Platform

An Arabic, RTL-first platform for orphan sponsorship and donations. Twelve complete pages covering the whole journey — from reading a child's profile to a confirmed monthly sponsorship — built with nothing but HTML, CSS and JavaScript.

**No framework was used on purpose.** Every layout, component, state change and interaction is hand-written. That makes this the clearest evidence of the fundamentals underneath everything else in my portfolio.

---

## What it does

A visitor arrives, browses the sponsorship programmes, opens an individual orphan's profile, chooses a plan, goes through checkout and lands on a confirmation page. A registered sponsor gets a dashboard showing their active sponsorships. Around that core sit the pages a real organisation needs: about, blog, FAQ, contact, sign-in and registration.

---

## The twelve pages

| Page | Purpose |
|---|---|
| `index.html` | Home — the case, the programmes, the call to sponsor |
| `sponsorships.html` | Sponsorship programmes and plans |
| `orphan-details.html` | An individual profile: story, needs, sponsorship options |
| `checkout.html` | Sponsorship and donation checkout |
| `thank-you.html` | Confirmation after a completed sponsorship |
| `user-dashboard.html` | Sponsor area — active sponsorships and history |
| `login.html` | Sign in |
| `register.html` | Create an account |
| `about.html` | Vision, mission, values, goals |
| `blog.html` | News and stories |
| `faq.html` | Frequently asked questions |
| `contact.html` | Contact form and details |

---

## How it is built

**A real design system, in plain CSS.** `variables.css` holds the whole visual language as custom properties: an emerald brand ramp from 50 to 900, accent colours, a neutral scale, spacing, radii, shadows and motion. Nothing downstream hard-codes a colour. Changing the brand is one file.

**Stylesheets split by responsibility**, not by page:

```
variables.css     design tokens — the single source of truth
base.css          reset and element defaults
layouts.css       page scaffolding and grids
components.css    buttons, cards, badges, forms
main-ui.css       shared interface patterns
auth.css          sign-in and registration
dashboard.css     the sponsor area
style.css         page-level composition
```

**Shared partials, loaded at runtime.** The header, footer and modals live once in `assests/components/` and are injected into every page by `main.js` on load — so a change to the navigation happens in one file, not twelve. This is the same instinct a component framework encodes, implemented by hand.

**RTL from the start.** The document direction is Arabic; the layout was designed for it rather than mirrored into it afterwards.

---

## Tech stack

**HTML5** · **CSS3** (custom properties, Flexbox, Grid) · **Vanilla JavaScript** · **Font Awesome** for icons

No build step, no dependencies, no bundler.

---

## Getting started

Open `index.html` directly in a browser, or serve the folder with any static server:

```bash
npx serve .
```

A server is recommended over opening the file directly, because the shared header and footer are fetched at runtime and browsers block `fetch` on `file://` URLs.

---

## Project structure

```
*.html                       the twelve pages
assests/
  components/                header, footer and modals — injected at runtime
  css/                       tokens, base, layouts, components, pages
  js/main.js                 component loading and all interactions
  img/                       photography and illustrations
  font/ · webfonts/          Font Awesome
```

---

## Author

**Ahmed Al-Hawajiri** — Full-Stack Developer
[GitHub](https://github.com/ahmedalhawajri89) · [LinkedIn](https://www.linkedin.com/in/ahmedalhawajri)

Licensed under the MIT License.

---

<div dir="rtl">

## نبذة بالعربية

منصّة عربية لكفالة الأيتام والتبرّع، اثنتا عشرة صفحة كاملة تغطّي الرحلة من قراءة ملف الطفل حتى كفالة شهرية مؤكَّدة — مبنية بـ HTML و CSS و JavaScript فقط.

**عدم استخدام أي إطار عمل كان قراراً مقصوداً.** كل تخطيط ومكوّن وحالة وتفاعل مكتوب باليد، وهذا ما يجعل المشروع أوضح دليل على الأساسيات التي تقوم عليها بقية مشاريعي.

**نظام تصميم حقيقي بـ CSS خام:** ملف `variables.css` يحمل اللغة البصرية كاملة كمتغيّرات — تدرّج لوني كامل للهوية من 50 إلى 900، وألوان مساعدة، وسلّم رمادي، ومسافات وزوايا وظلال وحركة. لا يوجد لون مكتوب يدوياً في أي ملف آخر، وتغيير الهوية يعني تعديل ملف واحد.

**والملفات مقسّمة حسب المسؤولية لا حسب الصفحة:** توكنز، وأساسيات، وتخطيطات، ومكوّنات، وواجهة مشتركة، ولوحة المستخدم.

**الترويسة والتذييل والنوافذ المنبثقة مكتوبة مرة واحدة** في مجلد مستقل، ويحقنها `main.js` في كل صفحة عند التحميل — فتعديل القائمة يحدث في ملف واحد لا في اثني عشر. وهذه نفس الفكرة التي تقوم عليها أطر المكوّنات، منفَّذة يدوياً.

**والاتجاه من اليمين لليسار أصلي في التصميم** لا معكوس عنه لاحقاً.

**للتشغيل:** افتح `index.html` مباشرة، أو الأفضل شغّل خادماً بسيطاً بأمر `npx serve .` — لأن المتصفحات تمنع جلب الملفات المشتركة عند فتح الصفحة من القرص مباشرة.

</div>

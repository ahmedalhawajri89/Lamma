<p align="center">
  <img src="docs/cover.png" alt="Lamma, لمّة" width="900">
</p>

# Lamma · لمّة

[![Live Demo](https://img.shields.io/badge/Live%20Demo-Open%20the%20site-0f5c5c?style=for-the-badge&logo=vercel&logoColor=white)](https://lamma-sponsorship.vercel.app/)
[![CI](https://github.com/ahmedalhawajri89/Lamma/actions/workflows/ci.yml/badge.svg)](https://github.com/ahmedalhawajri89/Lamma/actions/workflows/ci.yml)

*Every child in the circle of their family.*

An orphan sponsorship platform built end to end: a bilingual public site (Arabic first, full English under `/en`), a Laravel 12 API on MySQL, a Vue 3 admin console, and a portal where each sponsor follows the child they support and sees where every dollar went.

*Arabic README: [README.ar.md](README.ar.md)*

---

## Try it

**[lamma-sponsorship.vercel.app](https://lamma-sponsorship.vercel.app/)**, in [Arabic](https://lamma-sponsorship.vercel.app/) or [English](https://lamma-sponsorship.vercel.app/en). Payments run against a demo gateway: no money moves.

| Role | Email | What to look at |
|---|---|---|
| Sponsor | `sponsor@lamma.org` | `/dashboard`: the children, «Where your money went», reports, receipts |
| Administrator | `admin@lamma.org` | `/admin`: everything, including partner associations and settings |
| Accountant | `accountant@lamma.org` | `/admin`: payments, monthly transfers, the continuity fund |
| Field supervisor | `supervisor1@lamma.org` | `/admin`: only the families in their own country |

Password for all: `password`. To sponsor as a visitor, use the card `4242 4242 4242 4242` (or `4000 0000 0000 0002` to see a decline).

---

## The idea

Most donation sites ask you to trust them. This one is built so you don't have to.

You don't give to an anonymous cause. You sponsor a named child who lives with their mother or relatives, through a verified local association, and you can follow every month of your money: what arrived, what was kept for running costs, what was transferred to the association, and what was spent on the child, item by item.

**That promise is enforced by the data model, not written in the marketing copy.** Every figure the site shows is computed from the payment, transfer and disbursement rows themselves. If the data changes, the claim changes with it.

---

## What it does

**The visitor** browses children and programmes, reads the transparency report, and sponsors in their own currency. Before paying they see exactly how the money will be split.

**The sponsor** gets a portal with each child's quarterly photo reports, a month-by-month ledger of where their money went, receipts by email in their language, messages to the child (moderated), and the controls to pause, change card or stop.

**The staff** work in a 23-screen console. A field supervisor sees only their country, an association supervisor only their association. The accountant prepares and sends the monthly transfers to partner associations, records local spending, and follows sponsorships whose card was declined.

---

## The hard parts

### 1. «Where your money went», per child, per month

Each payment keeps a snapshot of the running-cost percentage in force when it was made, so changing the setting never rewrites the past. On the first of every month, `kafala:transfers-prepare` builds one transfer per partner association from that month's payments, with one line per child. A transfer is a draft until the accountant sends it with a bank reference. From then on it's locked and the sponsor can see it.

The ledger the sponsor reads (`ChildLedgerService`) is assembled from those same rows: received, the sponsor's own share, running costs, transferred, spent by category, and the balance still held by the association for that child.

### 2. Keeping the child with their family

Children are cared for through **partner associations**, and each family records its care arrangement (with the mother, a grandparent, an uncle…), attested by a field visit that expires after 12 months. A child can't be published unless the family is family-based care, the attestation is current, and the association is verified (`Orphan::publishBlockers()`). Suspending an association hides its children from the site at once.

Staff access is scoped in one place (`StaffScope`): everything, one country, or one association. That replaced about twenty scattered country checks.

### 3. When a card is declined: the continuity fund

A declined monthly payment is retried on day 0, 3 and 7, never every day. The sponsor gets one email in their language and can pay with a new card from the portal. If it still isn't resolved 14 days after the due date, the **continuity fund** covers the child's month, so the money keeps arriving. The fund is fed by 1% taken from the existing running costs (not added on top) plus donations. After three covered months without a payment, the sponsorship ends and the child returns to the site.

A test walks a sponsorship day by day for four months: retries, three covered months, then the end.

### 4. Sponsoring in your own currency

A sponsor in London pays a fixed £40 every month, the same amount every month. Its dollar value changes with the rate, and it's the dollar value that funds the child. `monthly_amount` stays in the base currency, so the thirty places that compute funding and reports didn't change. A daily job refreshes the rates, rejects any jump over 25% in a day as a likely source error, and reprices live sponsorships.

One edge case needed a rule. When the pound dips, a fully funded child could reappear on the site needing 50 cents. A gap under one dollar counts as fully funded (`Orphan::FX_TOLERANCE`).

### 5. Two languages, one codebase

Arabic is the default and the keys are the Arabic text itself (`__('…')` in Blade, `t('…')` in JS), with 1,000+ English translations. A test scans every template, controller, email and JS module, and **fails the build if an Arabic string has no English**, so an untranslated string can't ship. Mixed-direction text uses `<bdi>` and Unicode isolates so «£40» never flips to «40£» inside an Arabic sentence. Each account stores its language, and every email (receipt, welcome, reset, payment issue) goes out in it.

---

## Bugs worth telling

- **A late payment swallowed the next one.** The idempotency key for a monthly charge was built from the month the job *ran*, so collecting September's late payment in October took October's key. October's own payment was then skipped as "already paid". The key now comes from the due month, and a test pins it.
- **Password-reset links could point to another site.** Building the link with `url()` trusts the request's `Host` header, so a forged header put an attacker's domain in a real reset email. Email links are now built from `app.url` only (`Locale::absolute()`), with a test that sends a forged host.
- **The thank-you page promised emails nobody sent.** It said "we've emailed your receipt" and "we've sent you a link to set your password", and nothing did. Both emails now exist and are sent after the database commit, so a rolled-back payment never gets a receipt.
- **Exchange-rate drift reopened funded children.** See the tolerance rule above.

---

## Decisions in the schema

- **A partial unique constraint on an engine without partial indexes.** A sponsor can't sponsor the same child twice at the same time, but can come back after cancelling. A generated column is `NULL` for ended sponsorships, and MySQL ignores `NULL` in `UNIQUE`:
  ```sql
  active_key TINYINT GENERATED ALWAYS AS (
      CASE WHEN status IN ('pending','active','paused') THEN 1 ELSE NULL END
  ) STORED,
  UNIQUE KEY uniq_active_sponsorship (sponsor_id, orphan_id, active_key)
  ```
- **`idempotency_key` is unique on payments**, so a double click or a retried webhook can't charge twice. The engine refuses it.
- **Money is `DECIMAL` with its currency, rate and base equivalent**, and the rate used is stored on each payment.
- **`date_of_birth`, never `age`**, and **`UNIQUE(orphan_id, year, quarter)` on reports**, so "a report every three months" is a constraint, not a policy.

---

## By the numbers

| | |
|---|---|
| Database tables | **51**, 42 migrations |
| API routes | **136** (public, auth, portal, admin) |
| Models / enums | 36 / 18 |
| Admin console | 23 screens |
| Scheduled jobs | billing, exchange rates, continuity fund, monthly transfers, metrics |
| Tests | **331 Pest** · 12 Vitest · 53 Playwright |
| Translations | 1,064 English strings, guarded by a coverage test |

---

## Security

- **No card data is stored**: a gateway token, the brand and the last four digits.
- **Guardian ID numbers are hashed** with SHA-256 and a pepper: duplicates are detectable, the number itself is never kept.
- **A policy on every model**, plus `StaffScope` for row-level access by country or association.
- **One origin** for the site, portal, console and API: no CORS and no cross-origin cookies, and the CSP stays at `connect-src 'self'`.
- **Two people for money out:** the person who records a disbursement can't approve it.

---

## Design

Teal `#0f5c5c`, gold `#e0a63b` and a warm sand ground `#f7f5f0`, with dark mode. Readex Pro for headings and IBM Plex Sans Arabic for text, chosen over the usual Cairo/Tajawal. No gradients, logical CSS properties throughout so RTL and LTR share one stylesheet, and reduced motion respected.

---

## Tech stack

**Back end:** Laravel 12 · PHP 8.2 · MySQL (TiDB in production) · Sanctum
**Public site and portal:** Blade, Bootstrap 5 and vanilla JS modules, no build step
**Admin console:** Vue 3 · Vite · Pinia · Tailwind · Chart.js
**Quality:** Pest · Vitest · Playwright · PHPStan (Larastan) · Pint · GitHub Actions

---

## Structure

```
.
├── api/                    Laravel 12 API, and the public site and portal it serves
│   ├── app/                Actions, Services, Policies, Support (Fx, Locale, StaffScope)
│   ├── database/           42 migrations, demo seeder (two years of data)
│   ├── lang/               en.json and en/js.php (Arabic is the key)
│   ├── resources/views/    Blade: site pages, portal, emails
│   └── public/assets/      CSS and JS modules for the site and portal
├── admin/                  Vue 3 admin console (built into api/public/admin-app)
└── docs/                   Architecture and database notes
```

---

## Running it

**Requirements:** PHP 8.2+ · Composer · MySQL 8 · Node 20+

```bash
# 1) The API, which also serves the public site and the portal
cd api
composer install
cp .env.example .env && php artisan key:generate
mysql -u root -e "CREATE DATABASE kafala CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed       # about a minute: children, sponsors, two years of payments
php artisan serve

# 2) The admin console
cd ../admin
npm install
npm run dev
```

The scheduled jobs (`billing:run`, `kafala:rates-refresh`, `kafala:continuity-cover`, `kafala:transfers-prepare`, `metrics:rollup`) run through `php artisan schedule:work` locally.

---

## Licence

MIT. See [LICENSE](LICENSE).

Built by [Ahmed Al-Hawajiri](https://github.com/ahmedalhawajri89), Gaza.

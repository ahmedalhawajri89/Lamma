<p align="center">
  <img src="docs/cover.png" alt="Lamma — لمّة" width="900">
</p>

# Lamma — لمّة

[![Live Demo](https://img.shields.io/badge/Live%20Demo-Open%20the%20site-c2410c?style=for-the-badge&logo=vercel&logoColor=white)](https://lamma-sponsorship.vercel.app/)

**Live demo: [lamma-sponsorship.vercel.app](https://lamma-sponsorship.vercel.app/)**
The whole platform runs there: the public site, the admin console at `/admin` and the sponsor portal, on Vercel with a TiDB database. Sign in with `admin@lamma.org` (console) or `sponsor@lamma.org` (portal), password `password`.

*Arabic README: [README.ar.md](README.ar.md)*

*Every child in the circle of their family.* Formerly “Kafala”; renamed because that name is shared by many charities and projects (technical identifiers such as `config/kafala.php` keep the old name).

An orphan sponsorship platform, built end to end: an Arabic-first public site, a Laravel 12 API on MySQL, a Vue 3 admin console, and a portal where a sponsor follows the child they support.

---

## The idea

Most donation sites ask you to trust them. This one is built so you do not have to.

You do not give to an anonymous cause — you sponsor a named child with a story and a verified file, you receive a photo report every three months, and you can read the audited budget that anyone else can read too.

**That principle is enforced in the schema, not written in the marketing copy.** Every number the site publishes is derived from the database rather than typed into the page: the spending ring is aggregated from approved disbursements, the "93% reaches the child" figure is a division, and the field-visit count is a `COUNT(*)`. If the data changes, the claim changes with it. It cannot drift out of sync, because there is no second copy of it.

---

## What it does

**For the visitor.** Browses sponsorship programmes and orphan profiles, filters and searches, reads the transparency page and the quarterly reports, and completes a sponsorship from first read to confirmed monthly commitment.

**For the sponsor.** Signs in to a portal that shows the children they sponsor, the payment history, and the reports published about each child.

**For the administrator.** Sixteen screens covering orphans and their guardians, sponsors and sponsorships, payments and disbursements, quarterly reports, field visits, and the published budget — each behind a policy that decides what this user may see and do.

---

## The part that matters

**One deployment serves everything, from one origin.**

| Path | What is served |
|---|---|
| `/`, `/about`, `/orphans`, … | The public site — static HTML, clean URLs |
| `/dashboard` | Sponsor portal |
| `/admin/**` | Admin console — Vue SPA |
| `/api/v1/…` | The API |

Because there is a single origin there is no CORS, no cross-origin cookies, and the content security policy can stay at `connect-src 'self'` — the narrowest surface the application can have. That is a deployment decision that removes a whole class of problems instead of configuring around them.

**Five decisions in the schema**

**1. A partial unique constraint on an engine that has none.**
A sponsor must not sponsor the same child twice *at the same time*, but must be free to come back after cancelling. A plain `UNIQUE(sponsor_id, orphan_id)` forbids the return. MySQL has no partial indexes, so the constraint is built with a generated column:

```sql
active_key TINYINT GENERATED ALWAYS AS (
    CASE WHEN status IN ('pending','active','paused') THEN 1 ELSE NULL END
) STORED,
UNIQUE KEY uniq_active_sponsorship (sponsor_id, orphan_id, active_key)
```

`NULL` does not participate in `UNIQUE` in MySQL, so cancelled rows drop out of the constraint automatically. A real partial index, on an engine that does not support them.

**2. `idempotency_key` is unique on payments.**
A sponsor double-clicks "pay", or the gateway retries its webhook. The duplicate charge is made impossible *by the engine* rather than by code discipline.

**3. `date_of_birth`, never `age`.**
An age column is wrong the day after you write it. The date is the fact; the age is derived.

**4. Money is `DECIMAL`, with its currency and exchange rate.**
`FLOAT` loses cents once you start summing.

**5. `UNIQUE(orphan_id, year, quarter)` on reports.**
The promise of "a report every three months" becomes a constraint that cannot be broken, not a policy someone has to remember.

---

## By the numbers

| | |
|---|---|
| Database tables | **44** across nine domains |
| API routes | **88** in four groups |
| Eloquent models | 29, with 15 enums |
| Admin screens | 16 |
| Public site | 17 pages, ~10,900 lines |
| Tests | 80 Pest · 29 Playwright · 12 Vitest |

---

## Security

- **No card data is stored** — the brand and last four digits, nothing more.
- **Guardian ID numbers are hashed** with SHA-256 and a pepper: duplicates are detectable, the number itself is never kept.
- **A policy on every model**, so authorisation is answered in one place per resource rather than scattered through controllers.

---

## Design

A **clay and amber** identity on a warm sand ground — deliberately away from the medical green that charity sites default to.

| Role | Token | Value |
|---|---|---|
| Primary | `--color-brand` | `#c2410c` — terracotta |
| Secondary | `--color-accent` | `#d97706` — amber |
| Growth | `--color-growth` | `#4d7c0f` — olive |
| Canvas | `--color-canvas` | `#fbf7f0` — warm sand |

**The logo** is a custom SVG: two hands — one clay, one amber — cradling an olive-toned child. It draws the literal meaning of *kafala*, taking someone under your hand, and stays readable from 120px down to 16px.

**Typography** pairs *Readex Pro* for headings with *IBM Plex Sans Arabic* for body text and *Aref Ruqaa* for pull quotes — a distinctive Arabic pairing instead of the ubiquitous Cairo/Tajawal. Every size is a `clamp`.

**The chart palette was not chosen by eye.** The brand colours failed automated validation as a categorical palette: olive against teal measured ΔE 11.7 for normal vision against a threshold of 15, and the sand tone had chroma 0.027, so it read as grey. The palette was re-derived from the same colour ramps until it passed the lightness-range, chroma-floor and colour-blind separation checks. The working is in [`admin/src/utils/palette.js`](admin/src/utils/palette.js).

---

## Tech stack

**Back end** — Laravel 12 · PHP 8.2 · MySQL · Sanctum for token auth
**Admin console** — Vue 3 · Vite · Pinia · Chart.js
**Public site** — HTML, CSS and JavaScript with no framework and no build step
**Testing** — Pest for the API · Vitest for the console units · Playwright end to end

---

## Structure

```
kafala-orphan-sponsorship/
│
├── api/                    Laravel 12 · PHP 8.2 · MySQL · Sanctum
│   ├── app/  database/     ~12,400 lines
│   └── public/             ← the public site: HTML, CSS and JS, no framework
│       ├── index.html …    17 pages · ~10,900 lines
│       ├── assets/
│       └── admin-app/      ← Vue build output (not committed)
│
└── admin/                  Console source: Vue 3 · Vite · Pinia · Chart.js
```

---

## Running it

**Requirements:** PHP 8.2+ · Composer · MySQL or MariaDB · Node 20+

```bash
# 1) The API — it serves the public site too
cd api
composer install
cp .env.example .env && php artisan key:generate
mysql -u root -e "CREATE DATABASE kafala CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed     # ~60s: 148 orphans, 180 sponsors, two years of payments
php artisan metrics:rollup
php artisan serve

# 2) The admin console
cd ../admin
npm install
npm run dev
```

---

## Licence

MIT — see [LICENSE](LICENSE).

Built by [Ahmed Al-Hawajiri](https://github.com/ahmedalhawajri89), Gaza.

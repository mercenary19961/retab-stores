# Retab Stores — Project Context

> Quick reference for AI assistants and developers

---

## Project Overview

**Store:** Retab Stores
**Industry:** E-commerce — premium dates (تمور) retail in Saudi Arabia
**Type:** Online store (storefront + admin/back-office), currently **migrating off [Zid](https://zid.sa)** to a custom Laravel build
**Stack:** Laravel 12 + Inertia.js v3 + React 19 + TypeScript + TailwindCSS v4 (official `laravel/react-starter-kit` v1.0.1; Inertia upgraded v2→v3 on 2026-06-28)
**DB:** Local dev runs on **XAMPP MariaDB 10.4.32** (`127.0.0.1:3307`, db `retab-stores`) — note: MariaDB, _not_ MySQL (XAMPP bundles MariaDB under the "mysql" folder/service name, hence the confusion). **MySQL 8 planned for production** (switch via `DB_CONNECTION`); dev↔prod engine parity is a pending decision (see Foundation).
**Mailer:** _TBD_ — likely Resend (mirroring Sky Amman), decision pending
**Architecture:** Single-service monolith (Laravel serves everything via Inertia). SSR build-time scaffolding present; runtime SSR not yet wired (see Build Progress).
**Hosting:** _TBD_ — likely Railway (FrankenPHP) behind Cloudflare, mirroring Sky Amman
**Branch:** `main`
**Repos (dual-push):** `origin` fetches from `https://github.com/mercenary19961/retab-stores.git`; a **second push URL** for the client's repo will be added so one `git push` updates both (see Git & Deploy).
**Languages:** Arabic (primary — Saudi market, **RTL-first**) + English (secondary). ⚠️ This flips Sky Amman's default (which is English-default).
**Theme:** _Decision pending._ The starter kit ships a light/dark **appearance toggle** (`useAppearance` hook + `settings/appearance` page) — keep, or lock to a single branded theme like Sky Amman did. Decide before heavy UI work.

---

## Reference Project — Sky Amman

We previously shipped **Sky Amman** (real-estate site) on essentially this same stack. It lives at `c:\Users\sabba\Desktop\projects\sky-amman\`. **When in doubt about a pattern** (locale middleware, `HandleInertiaRequests` shared props, Turnstile wiring, admin layout, Site Content CRUD, security headers, Cloudflare `trustProxies` CIDRs, SSR sidecar, CI), read the corresponding file in Sky Amman first — those patterns are proven.

Sky Amman's `CLAUDE.md` is large — read targeted sections via Grep / `offset`+`limit`, never the whole file.

### ⚠️ Differences from Sky Amman — don't blindly copy

This project is the **official Laravel React starter kit**, scaffolded fresh; Sky Amman was a hand-rolled setup. Concrete divergences to respect:

- **Inertia is now v3 here too** (`inertiajs/inertia-laravel` v3.1.0, `@inertiajs/react` ^3.0) — _formerly a divergence (scaffold shipped v2), resolved by the v2→v3 upgrade on 2026-06-28._ It now **matches Sky Amman (v3)**, so Sky Amman's Inertia patterns **port cleanly**: the 419/CSRF auto-reload listener is `router.on('httpException', …)` (v2's `invalid` was renamed), and the `resolvePageComponent` `.default` unwrap is **already applied** in `app.tsx` + `ssr.jsx` (v3 dropped auto-unwrap of the page module's default export). Note retab uses lowercase `./pages/` paths, not Sky Amman's `./Pages/`.
- **Lowercase, kebab file layout:** `resources/js/pages/` (not `Pages/`), `resources/js/layouts/`, `resources/js/components/` (+ shadcn-style Radix UI primitives under `components/ui/`), and **modular route files** (`routes/auth.php`, `routes/settings.php`, `routes/web.php`). Path-adjust any Sky Amman snippet accordingly.
- **shadcn/Radix UI is the component base** here (`@radix-ui/*`, `class-variance-authority`, `tailwind-merge`, `tailwindcss-animate`) — Sky Amman hand-built its components.
- **No bilingual/i18n, Turnstile, CMS, or admin** exist yet — those are Sky Amman patterns to **port deliberately**, not assume.

---

## Background — migrating off Zid

The store is **already live and selling on Zid** (hosted SaaS). We are rebuilding it on Laravel to own the codebase and add custom touches. Implications to keep in mind:

- **Data migration:** product catalogue, categories, customers, and order history likely need exporting from Zid and importing here. Plan an import path (CSV/API export → seeders or a one-off importer) before go-live.
- **Payments are ours now.** Zid bundled checkout + payment. For KSA we'll need a local gateway — **Moyasar / Tap / HyperPay** (mada + cards) and optionally **Tabby / Tamara** (BNPL). This is a **key pending decision** and shapes the orders/payments schema.
- **Feature parity first, enhancements second** — match what the Zid store does before layering "our touch."

---

## Tech Stack (as scaffolded — real versions)

- **Backend:** Laravel `v12.62.0`, PHP `^8.2` (starter kit pinned to v1.0.1 because latest requires PHP 8.3), PHPUnit `11.5`, Ziggy `v2.6.3`
- **Inertia:** `inertiajs/inertia-laravel` `v3.1.0` + `@inertiajs/react` `^3.0` (installed `3.5.0`) — upgraded from v2 on 2026-06-28 (see Build Progress → Construction phase). Requires only PHP 8.2, so the starter-kit-v1.0.1 / PHP 8.2 pin was never a blocker for Inertia itself.
- **Frontend:** React `19`, TypeScript `^5.7`, Vite `^6`, Tailwind CSS `^4` (`@tailwindcss/vite`), `lucide-react`, Radix UI primitives, `clsx`/`tailwind-merge`/`cva`
- **What the starter kit already gives us:** auth flow (login / register / forgot+reset password / email verify / confirm password), `dashboard`, `settings` (profile / password / appearance), an `AppLayout`, and a `welcome` landing page. **SSR entry `resources/js/ssr.jsx` + `npm run build:ssr` exist** (runtime not enabled yet).
- **`.npmrc`** holds `production=false` — **do not delete it.** The user's shell has a global `NODE_ENV=production` that otherwise makes `npm install` silently drop devDependencies. (The starter kit dodges the worst of it by listing build deps under `dependencies`, but keep the guard.)

---

## Architecture Patterns (MUST FOLLOW)

These are the conventions we're adopting (most proven in Sky Amman). Where a pattern isn't built yet, it's marked **(to build / port)**.

### Inertia.js
- Controllers use `Inertia::render('page/name', [...props])` — no API routes for the storefront/admin UI.
- Mutations use `router.post/put/delete()` with `preserveScroll`, `onSuccess`, `onFinish`.
- Forms with files: native `FormData` + `forceFormData: true`.
- Routing is server-driven via `routes/*.php` — no client-side router.
- Auth state via `usePage().props.auth.user` (shared by middleware).
- Flash messages: server `->with('success', …)` → client reads `usePage().props.flash`.

### Bilingual (Arabic-first) + RTL — **(to build)**
- Saudi market: **Arabic is the primary/default locale**, English secondary. Plan locale as the single source of truth in the **server session** (no localStorage), mirroring Sky Amman's `SetLocale` middleware + `/locale/{lang}` POST.
- RTL: use CSS **logical properties** (`text-start`, `ms-*`/`me-*`, `ps-*`/`pe-*`), not `text-left`/`flex-end`.
- Arabic web font (e.g. IBM Plex Sans Arabic / Tajawal) + Latin font, swapped via `html[dir="rtl"]`.
- Pattern: pass both locale bundles from the controller, pick client-side; **stored content always wins over i18n fallbacks**.

### File Storage — **(to build)**
- Centralize uploads through a single `Media::storeFile()`-style helper (randomized filename, server-side type+MIME validation). Never write public files directly.
- For ephemeral hosting (Railway): seeded/structural images go in git under `public/`; runtime uploads need durable storage (S3/R2) — **product images especially** (don't rely on the container FS).

### Auth & Security — **(harden before launch)**
- Session-based auth (starter kit default). Use `Auth::id()`/`Auth::user()`, never the `auth()` helper in new code (Sky Amman convention).
- Rate-limit all public POST endpoints; add a bot gate (Cloudflare Turnstile) on public forms (port from Sky Amman).
- Server-side validation on ALL endpoints; validate file types by extension AND MIME; parameterized queries / Eloquent only.
- Add a `SecurityHeaders` middleware (CSP allowlist, Permissions-Policy) — port from Sky Amman, skip CSP in local dev.
- Production HTTPS behind a proxy: `URL::forceScheme('https')` when `APP_ENV=production` + correct `trustProxies` CIDRs (see Sky Amman's `bootstrap/app.php`).

### SEO — **(to build)**
- Storefront SEO matters (organic product discovery). Server-render public pages (enable SSR — see Build Progress), per-page/per-product `<Head>` meta + OG, dynamic `sitemap.xml`, `robots.txt` disallowing admin, JSON-LD (`Product`/`Offer`/`BreadcrumbList`), and `hreflang` AR↔EN.

### Deployment + SSR
- Likely Railway (Railpack, PHP 8.2 + Node) behind Cloudflare — port Sky Amman's startup (`migrate --force` → `storage:link` → `optimize`).
- Use `SESSION_DRIVER=database` in production (ephemeral FS).
- **SSR:** the starter kit ships `resources/js/ssr.jsx` + `npm run build:ssr`. To actually enable it we still need to: publish `config/inertia.php` (env-driven `INERTIA_SSR_ENABLED`), add a timeout/graceful-fallback HTTP gateway (so a hung SSR process falls back to CSR instead of 502-ing), and stand up a production SSR sidecar. **Port Sky Amman's `app/Ssr/TimeoutHttpGateway.php` + sidecar setup.** (Sky Amman docs two gotchas: Railpack pruning Node from the runtime → `RAILPACK_DEPLOY_APT_PACKAGES=nodejs`; and the SSR URL host must match the private domain exactly + is baked at `config:cache` time.)

---

## Database Schema (PLANNED — nothing e-commerce built yet)

Only the starter-kit defaults exist so far (`users`, `cache`, `jobs`, plus auth tables). The e-commerce schema is **not designed yet** — proposed core tables to flesh out during foundation:

- `products`, `categories` (+ pivot), `product_variants` (size/weight/grade — relevant for dates), `product_images`
- `inventory` / stock tracking, `prices` (incl. sale price), maybe `coupons`/`discounts`
- `carts` + `cart_items` (guest + logged-in), `orders` + `order_items`, `order_status` history
- `customers`/extended user profile, `addresses` (KSA address format), `payments` (gateway txn refs), `shipping`/`fulfillment`
- Bilingual product content (AR/EN) — decide column-per-locale vs. a translations table early.

> **Document every real schema decision here as it's made**, Sky-Amman-style (the "why", not just the "what"), so future agents don't re-litigate it.

---

## Key File Locations (as scaffolded)

```
routes/web.php, auth.php, settings.php, console.php   → route definitions (modular)
resources/js/app.tsx            → client entry
resources/js/ssr.jsx            → SSR entry (build-time only until runtime is wired)
resources/js/pages/             → page components (welcome, dashboard, auth/*, settings/*)
resources/js/layouts/           → AppLayout + auth/settings layouts
resources/js/components/         → shared components (+ ui/ shadcn-Radix primitives)
resources/js/hooks/             → e.g. useAppearance
resources/js/lib/               → utils (cn, etc.)
resources/js/types/             → shared TS types
resources/css/app.css           → Tailwind v4 entry + theme tokens
database/migrations/            → starter-kit defaults only so far
.npmrc                          → production=false (keep — NODE_ENV gotcha)
```

---

## Code Quality Rules (adopted from Sky Amman)

- If a model has a dedicated method (`Media::storeFile()`, `Setting::set()`), **always use it** — never bypass with raw `::update()`/`::create()`.
- The same write (create/update/restore) must go through one shared model method.
- **Never query inside a loop** — batch-fetch with `whereIn()`/`pluck()` first. Paginated views query related data only for the current page's IDs.
- Guard no-op operations (short-circuit when nothing changed).
- If a method returns a meaningful value (bool/count/status), use it — don't call-and-ignore.
- Match the surrounding code's style, naming, and comment density.

---

## Local Development

- **Start (all-in-one):** `composer run dev` — runs `php artisan serve` + queue listener + `npm run dev` concurrently.
- **Or separately:** `php artisan serve` + `npm run dev`.
- **URL:** `http://localhost:8000`
- **Database:** XAMPP **MariaDB 10.4.32** on `127.0.0.1:3307`, db `retab-stores` (user `root`, empty password) — set in `.env`. Reset: `php artisan migrate:fresh --seed`. ⚠️ `php artisan db:show` errors on MariaDB (queries `performance_schema.session_status`, which it doesn't expose) — cosmetic only; use `migrate:status` / `db:table <name>` to inspect.
- **Build:** `npm run build` (client only) — or `npm run build:ssr` once runtime SSR is wired.
- **Backend tests:** `php artisan test` (PHPUnit, in-memory SQLite).
- **Mail in dev:** default `MAIL_MAILER=log` writes to `storage/logs/laravel.log` — no provider key needed.
- **`.npmrc` guard:** if `npm install` ever drops vite/devDeps, `.npmrc` (`production=false`) should prevent it; fallback `npm install --include=dev`.

---

## Git & Deploy

- `origin` is wired to **your** repo: `https://github.com/mercenary19961/retab-stores.git`.
- **Dual-push (one `git push` → both repos)** once the client repo exists + you're a collaborator on it:
  ```bash
  git remote set-url --add --push origin https://github.com/mercenary19961/retab-stores.git
  git remote set-url --add --push origin https://github.com/CLIENT-ACCOUNT/retab-stores.git
  git remote -v   # expect 1 fetch URL + 2 push URLs
  ```
  ⚠️ The **first** `--add --push` line (re-adding your own repo) is required — adding any explicit push URL drops the implicit default, so without it pushes would go **only** to the client repo.
- Commit/push **only when the user asks** (don't auto-push). Branch off `main` for feature work.

---

## Commit Message Convention

Format: `type(scope): short description`

Types: `init` (scaffolding/setup) · `feat` · `fix` · `refactor` · `style` (visual only) · `doc` · `chore`

Rules: lowercase subject, no trailing period, present/imperative tense ("add" not "added"), under 72 chars, be specific ("fix: resolve 419 on locale toggle", not "fix: fix bug").

End commit messages with:
```
Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
```

## Collaboration — Commit Message Suggestions

After completing any task that touches code, end the reply with a **one-line suggested commit message** in the convention above (don't run the commit — just suggest it). Skip this for purely exploratory work (reading/answering) or when no files changed.

---

## Build Progress

### Scaffold (DONE — 2026-06-28)
- [x] `laravel new retab-stores --react --phpunit --database=sqlite` — Laravel 12 + Inertia v2 + React 19 + TS + Tailwind v4 (starter kit v1.0.1) — _Inertia later upgraded v2→v3, see Construction phase_
- [x] `.npmrc` (`production=false`), `npm install`, production build verified
- [x] Git on `main`, `origin` → `mercenary19961/retab-stores`
- [x] This `CLAUDE.md` (conventions + maintenance pattern established, Sky Amman set as reference project)

### Construction phase (in progress — branch `construction_phase`)
- [x] **Inertia v2 → v3 upgrade** (2026-06-28) — `inertia-laravel` v2.0.24→**v3.1.0**, `@inertiajs/react` ^2.0→**^3.0** (3.5.0). Done up front, before any feature work, so the major bump stays cheap and the project matches Sky Amman's v3. Changes: applied v3's `resolvePageComponent(…).then(m => m.default)` unwrap in `app.tsx` + `ssr.jsx` (v3 dropped default-export auto-unwrap — the one real breaking change that hit the scaffold). Blade `@inertia`/`@inertiaHead` directives unchanged (still valid in v3); no axios/qs/lodash in the scaffold so those v3 dep-removals were transparent (`npm` pruned 12 transitive packages). The PHP-8.3 concern in the docs was about the starter-kit _template_, not Inertia (v3 needs only PHP 8.2). **Verified:** `npm run build` ✓ + `php artisan test` ✓ (26 passed, 63 assertions). ⚠️ Browser-side hydration not yet smoke-tested in a real browser.

### Foundation (TODO)
- [ ] **Decisions:** theme (keep dark-mode toggle vs. single branded theme) · payment gateway (Moyasar/Tap/HyperPay + Tabby/Tamara) · **dev↔prod DB parity** (dev is XAMPP MariaDB 10.4 — old/EOL; prod planned MySQL 8 — match engines via Docker `mysql:8`, or knowingly accept the gap?) · hosting (Railway?) · mail (Resend?)
- [ ] **Arabic-first bilingual + RTL** (locale middleware, fonts, i18n bundles) — port from Sky Amman, flip default to AR
- [ ] **E-commerce schema** (products/categories/variants/inventory/cart/orders/payments/addresses) — design + migrations + models
- [ ] **Zid data migration** path (export → import catalogue + customers + order history)
- [ ] Security hardening (Turnstile, SecurityHeaders/CSP, rate limits, `URL::forceScheme` + trustProxies) — port from Sky Amman
- [ ] Push to GitHub (`git push -u origin main`) + add the client repo as the 2nd push URL

### Storefront + Admin (TODO)
- [ ] Storefront: catalogue, product detail, cart, checkout, order confirmation, customer account
- [ ] Admin/back-office: products CRUD, orders, customers, settings, content
- [ ] SEO (SSR on, sitemap/robots, Product/Offer JSON-LD, hreflang)

### Infrastructure (TODO)
- [ ] **Runtime SSR** — starter kit already ships `resources/js/ssr.jsx` + `build:ssr`; still need `config/inertia.php` (env toggle) + graceful-fallback gateway + production SSR sidecar (**port Sky Amman's `TimeoutHttpGateway` + sidecar**). _Flagged by the user as the immediate follow-up after this file._
- [ ] Automated tests + CI (PHPUnit / Vitest / Playwright), branch protection on `main`
- [ ] Production deploy (MySQL, env vars, data-seeding migrations), Cloudflare DNS + Turnstile keys, mail domain verification

> **Last updated:** 2026-06-28 — **Project scaffolded, CLAUDE.md established, Inertia upgraded v2 → v3, local DB pointed at XAMPP MariaDB.**
> - **Local DB configured + migrated:** `.env` points at XAMPP **MariaDB 10.4.32** (`127.0.0.1:3307`, db `retab-stores`, root / empty password); ran `php artisan migrate` (users/cache/jobs + sessions tables created). Confirmed the engine is **MariaDB**, not MySQL — XAMPP ships MariaDB under the "mysql" name (binary `C:\xampp\mysql\bin\mysqld.exe`, service `mysql`); TablePlus was just the client used to create the DB. `APP_KEY` already present from scaffold. Flagged dev↔prod DB parity (prod planned MySQL 8) as a pending decision.
> - **Inertia v2 → v3 upgrade** (on branch `construction_phase`, before any feature work): `inertia-laravel` v2.0.24→v3.1.0, `@inertiajs/react` ^2.0→^3.0 (3.5.0). Verified the doc's "v2 vs v3" framing against both lockfiles (Sky Amman really is v3.0.4) and confirmed v3 needs only PHP 8.2 — the PHP-8.3 pin was about the scaffolding template, not Inertia. Only the scaffold's `resolvePageComponent` resolve needed changing (v3 dropped default-export auto-unwrap → added `.then(m => m.default)` in `app.tsx`/`ssr.jsx`, mirroring Sky Amman). Build + 26 tests green. This resolves the former "Inertia is v2 here" divergence from Sky Amman.
> - Scaffolded `retab-stores` with the official Laravel 12 React starter kit (Inertia v2 + React 19 + TS + Tailwind v4, PHP 8.2 / starter kit v1.0.1). Added `.npmrc` before install to dodge the `NODE_ENV=production` devDep trap; production build verified clean. Git on `main`, `origin` → `mercenary19961/retab-stores` (dual-push to the client repo to be added once it exists).
> - Wrote this `CLAUDE.md` adopting Sky Amman's conventions (Build Progress checklist, `> Last updated:` log, commit convention + commit-suggestion rule, code-quality rules) and set **Sky Amman as the reference project**, with an explicit "don't blindly copy" list (Inertia **v2** not v3, lowercase `pages/` + modular routes, shadcn/Radix base, no i18n/Turnstile/admin yet).
> - **SSR noted as the immediate next task:** build-time scaffolding (`ssr.jsx` + `build:ssr`) is present, but runtime SSR (config/inertia.php toggle + graceful-fallback gateway + prod sidecar) still needs wiring — to be ported from Sky Amman.

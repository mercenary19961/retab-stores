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

## E-commerce Requirements & Decisions (client brief — 2026-06-29)

> Captured from the client brief. **Reference implementation:** `c:\Users\sabba\Desktop\projects\hardrock-ecom-demo\` — a mature Laravel 12 + Inertia **v2** e-commerce backend we built that ALREADY implements OTO shipping, Tamara, coupons, order activities, admin activity-log/undo, optimistic locking, roles (admin/editor), bilingual AR/EN, and notifications. Retab **adapts** these into a **tighter** build (gateway abstractions, thin controllers + service layer, explicit order state machine). Read hardrock-ecom-demo's files for proven patterns before building (e.g. `app/Services/Shipping/*`, `app/Services/Payments/*`).

### Payments — DECIDED
- **Cards via Moyasar** (mada + Visa/MC + Apple Pay + STC Pay) **+ Tamara (BNPL)** **+ bank transfer** (manual transfer to the store's **Al Rajhi** IBAN, **admin-verified** — added 2026-06-30 to match the client's current site). **No COD, no cash** — every order is prepaid (online gateway or verified bank transfer).
- **Store entity + bank** (from client): legal name **شركة مصنع رطاب الوطن للتمور**; Al Rajhi IBAN `SA9780000145608010008130` (acct `145608010008130`). Stored in `settings` (`bank_*`, `legal_name`); the IBAN is a public receiving account (fine to display).
- Both sit behind a `PaymentGateway` interface (mirror hardrock's `ShippingGateway` pattern); Tamara reuses hardrock's `TamaraClient`.
- **Capture model — DECIDED (hybrid per method):**
  - **Cards (Moyasar) = immediate capture** at checkout; **refund** on customer-cancel or admin-reject. Deliberately avoids any Moyasar delayed-capture dependency.
  - **Tamara = authorize at checkout, capture on admin confirmation**; **void** the authorization if cancelled/out-of-stock (Tamara supports auth→capture natively).
  - Both expose `authorize / capture / void / refund` via the `PaymentGateway` interface; the order flow calls the right one per method + state.
  - SLA: admin confirms within ~24–48h (Tamara auth expires); dashboard flags orders nearing auth-expiry.
  - ⚠️ Trade-off: rejected **card** orders incur a real refund (fee + "money in/out" UX) — acceptable while out-of-stock rejections stay rare (depends on inventory accuracy / أسماك sync); revisit (cards→delayed capture, if Moyasar supports it) if they become common.

### Shipping — DECIDED
- **OTO (Tryoto) aggregator** for fulfillment (reuse hardrock `ShippingGateway`→`OtoGateway`/`OtoClient` + `OtoWebhookController`). **GCC countries only. No Aramex, no DHL** (filter out).
- **Customer pays a SINGLE FIXED flat shipping price** across all GCC, regardless of OTO's actual carrier cost (DECIDED — client accepts absorbing the cross-GCC cost difference). One configurable number in admin.
- OTO **does** return live per-carrier rates via `checkOTODeliveryFee` (POST origin/destination/weight; auth = refresh-token→access-token, cached). Used **internally** for cost/carrier selection — NOT shown as the customer price.

### Products & Inventory — DECIDED
- **Inventory tracked by quantity (units in stock).** Weight is **descriptive only** — shown in the product title/description per product, NOT a structured field (shipping is flat, so weight never drives cost).
- **No variants (DECIDED)** — each sellable item = one product = one SMACC SKU (keeps the Excel import 1:1). All stock is unit-quantity on the product.

### Coupons — DECIDED (+ one POSTPONED)
- Admin-controlled coupon system (reuse + extend hardrock's `Coupon`).
- ⏸ **POSTPONED — QR-code coupons redeemable at the PHYSICAL store** (client used to issue a QR after 5 purchases for an in-store discount). Design later: signed **single-use** token, atomic redemption (optimistic locking), validated either by أسماك or a standalone cashier "redeem" web page (decoupled from أسماك). Revisit after core build.

### Order flow (client's required flow)
1. Customer orders → pays (prepaid) → payment confirmed.
2. Admin notified via **email + WhatsApp + admin-panel notification**.
3. Admin/editor checks inventory & issues, then **confirms**:
   - **If unavailable:** button to WhatsApp the customer (apologize + suggest similar products + "back soon"); the event is logged/notified + stored for **analytics** + shown on the dashboard.
   - **If confirmed:** deduct stock → alert OTO to pick up → WhatsApp customer "confirmed, courier coming."
4. **Cancellation:** customer may cancel **only before** admin confirms → notify customer **indirectly** (don't incentivize cancelling).

### Returns & exchanges — policy from current site (to replicate)
Source: retabstore.com policy. **Defect/damage-only** (matches food/perishable norms):
- **Eligibility:** ONLY for product **defects/damage** (عيوب / تمور تالفة). Excludes late receipt, shipping-company faults, and used/tampered products.
- **Window:** request within **3 days** of receiving the product → requires tracking **`delivered_at`** on orders.
- **Channel (current):** **WhatsApp** (returns dept +966 50 384 5356) with **photos** + order number + contact details.
- **Condition:** returned items in good condition with **original labels + packaging**.
- **Shipping fees:** **non-refundable** EXCEPT when the dates arrived **damaged**.
- **Resolution:** after inspection, exchange or **refund within 14 days**.
- **Build implication — returns module:** model as a **separate entity** (not just an order status): `return_requests` (order_id, user_id, status [requested/approved/rejected/exchanged/refunded], reason, resolved_at, admin_notes) + `return_items` (partial returns) + attached **photos** (media). Enforce 3-day window via `delivered_at`; refund via the payment gateway (card→Moyasar, Tamara→Tamara); shipping fee refunded only if damaged. Customer files in-account (with photos) → admin reviews → resolves; mirror WhatsApp touchpoints.
- **Also implies:** a **content/legal pages** capability (bilingual) for the policy page itself + about/contact (port Sky Amman's Site Content CRUD pattern).

### WhatsApp + Marketing — DECIDED: direct Meta Cloud API
- Use the **direct Meta Cloud API** (no BSP) — lowest cost, full control, under the same Meta Business Manager as the client's ads. We build the integration in Laravel (send + webhooks for delivery/read status).
- **Message types:** order confirmation / apology+suggest / "courier coming" / loyalty = **Utility** templates; monthly offers = **Marketing** templates (**opt-in required**). All business-initiated messages outside a customer's 24h reply window MUST be **Meta-approved templates** (free text only inside the 24h window).
- **Admin "marketing" section = two parts:** (a) **template manager** — compose + submit to Meta + track approval status (or manage templates in Meta Business Manager initially); (b) **campaign sender** — pick an approved template → fill variables → send to the opt-in segment → track delivery via webhook.
- **Guardrails:** marketing only to opted-in contacts; plan for Meta's template-approval lag; respect Meta's daily conversation limits + quality rating.
- Synergy: **click-to-WhatsApp ads** from the client's Meta campaigns start chats + open the free 24h window.

### Loyalty — DECIDED
- **5 confirmed purchases → 15% discount coupon**; customer notified + incentivized (WhatsApp).
- Confirmed buyers → added to the **monthly WhatsApp marketing list** (must capture opt-in consent at/after purchase).

### Customers, accounts & engagement — DECIDED
- **Account creation at checkout** (if not signed in) via any of: **WhatsApp** (phone OTP via WhatsApp), **Google** (OAuth — reuse hardrock's Socialite), **email** (email+password). Minimal at signup; user **completes profile later** (name, city, phone, email…).
- **Identity model:** `users` has **nullable** name/email/phone/password (a user may have only a phone, or only Google); a `social_accounts` table links providers to one account; `otp_verifications` for phone/WhatsApp sign-in.
- **OTP delivery = WhatsApp only (DECIDED)** — no SMS provider. Phone verification is via WhatsApp OTP; sign-in methods = **WhatsApp / Google / email**.
- **Reviews** (+ helpful votes; bilingual) and **wishlist** (per user) — reuse hardrock patterns.
- **Loyalty/points tracker:** count of confirmed purchases on the user; shown on the **customer's account** (progress to reward) AND the **admin** customer view; drives the 5→15% coupon. Count-based for v1, extensible to points-per-spend.

### POS integration (SMACC / سماك) — NO API; daily Excel export→import (DECIDED)
- Physical store's POS is **SMACC** ("سماك" = how S-M-A-C-C is pronounced), by **Arab Sea Information Systems** (`arabsea.com`) — major KSA accounting/POS/inventory/e-invoicing platform.
- ⚠️ **Arab Sea confirmed: NO API integration.** Only path = **export SMACC inventory to Excel**, then **import into our admin** to re-baseline website stock.
- **Inventory model (DECIDED):** **SMACC = ledger of record**; website = **daily-synced mirror** + soft reservations for in-flight online orders. Website stock is **advisory** between imports; the **per-order admin confirm-step is the real backstop** (stale stock → at worst an apology, never a bad fulfillment).
- 🔑 **Critical rule:** online sales MUST be reflected in SMACC before each export, or every import "refills" already-sold units → upward drift + overselling. Website generates a **daily "online sales" report** so the admin batch-adjusts SMACC, then exports.
- **Daily routine:** (1) website → today's online sales; (2) admin reflects them in SMACC; (3) export SMACC → Excel; (4) import to admin (diff/preview → apply).
- **Import design:** match rows by stable key → products store **`smacc_sku`/barcode**; **diff/preview** before apply (flag unmatched rows); transactional; logged + undoable (reuse `ActivityLogService`); idempotent.
- **Mitigate the daily-commitment risk:** "stock last synced: Xh ago" indicator + banner/alert if no import in >24h; low-stock buffer; one-drag import. **Ask Arab Sea:** can SMACC schedule/email the export? → enables auto-ingest of the file (still not live sync).
- Knock-on: staler mirror → more out-of-stock rejections → more captured-card refunds (see Payments). Daily import keeps that low.

### Admin panel & i18n — DECIDED
- **Storefront AR-first** then EN; **admin panel EN-first** then AR. Admin panel supports **dark + light themes**.

### Open decisions (blocking schema design)
1. ~~Payment capture model~~ — ✅ DECIDED (hybrid: cards immediate-capture, Tamara auth→capture; see Payments).
2. ~~POS / inventory~~ — ✅ SMACC by Arab Sea, **no API** → daily Excel export→import; SMACC = ledger of record, website = daily mirror + admin confirm-step backstop (see POS section).
3. ~~WhatsApp route~~ — ✅ direct Meta Cloud API (see WhatsApp section).
4. ~~Shipping flat rate~~ — ✅ single flat GCC rate.

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

### File Storage — **(BUILT — media layer + product images)**
- **Single upload path = `App\Support\Media`** (`storeImage()` / `url()` / `delete()`): validates extension AND MIME, randomizes the filename (UUID), stores public on the **`media` disk**. **Never write public files directly** — always go through `Media` (same rule as `Setting::set()`).
- **Storage provider — DECIDED: Cloudflare R2** (S3-compatible, **zero egress fees**, fits the planned Cloudflare stack). Wired as the `r2` disk in `config/filesystems.php` via Laravel's **s3 driver** (`region: auto`, path-style endpoint) — no R2-specific code, so swapping to real S3 is an env change. The active disk is chosen by **`MEDIA_DISK`** env: defaults to the local **`public`** disk in dev (no creds; run `php artisan storage:link` once), set **`MEDIA_DISK=r2`** in production (Railway's FS is ephemeral — uploads MUST NOT live on the container disk).
  - Prod env (Railway only, never commit): `MEDIA_DISK=r2`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT` (`https://<account-id>.r2.cloudflarestorage.com`), `R2_URL` (public bucket / custom domain).
  - **Cost:** R2 free tier = **10 GB storage + 1M writes + 10M reads per month**, then **$0.015/GB-month**, **$4.50/M writes**, **$0.36/M reads**, **$0 egress**. A dates catalogue (hundreds of images, ~sub-GB) sits in the free tier indefinitely; egress being free is the reason it beats S3 for an image-heavy storefront.
- **Product images BUILT** on this: `product_images` (path/alt/sort/is_primary) managed via `Admin\ProductImageController` (upload/delete/set-primary on dedicated multipart-POST endpoints — separate from the product text form to avoid PUT-multipart issues); first image auto-primary; deleting the primary promotes the next. Storefront catalogue/product + admin list/edit render via `Media::url()` with a 🌴 placeholder fallback.
- **Still pending (same media layer):** return-request photos (returns module) and review photos. Seeded/structural images (logo, etc.) still go in git under `public/`.

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

## Database Schema (BUILT — schema v1 complete on `construction_phase`)

> **Source of truth = the migrations** in `database/migrations/` (`2026_06_29_1000xx_*`) + Eloquent models in `app/Models/`. Migrated clean on **MariaDB (dev)** and **SQLite (tests)**; 26 tests green. Conventions: bilingual `_ar`/`_en` (AR required, EN nullable, app falls back to AR); quantity `stock` (no variants); `smacc_sku` import key; soft-deletes on `products` + `users`; money `decimal(10,2)` SAR; **order/payment/coupon/return state are backed enums** in `app/Enums/`.
>
> **Tables by domain:**
> - **Catalog:** `categories`, `products`, `product_images`
> - **Accounts:** `users` (extended: phone, role, locale, admin_theme, whatsapp_opt_in, confirmed_purchases_count; name/email/password now nullable), `social_accounts` (Google/OAuth), `otp_verifications` (WhatsApp OTP), `addresses` (GCC)
> - **Cart:** `carts`, `cart_items`
> - **Orders:** `orders` (state machine via `OrderStatus`; `delivered_at`; OTO + payment fields), `order_items` (snapshots), `order_activities` (append-only audit)
> - **Payments:** `payments` (auth/capture/void/refund ledger)
> - **Coupons/Loyalty:** `coupons` (admin-controlled; `channel`/`source`), `coupon_redemptions`, `loyalty_rewards` (5 buys → 15%)
> - **Returns:** `order_returns` (defect-only; photos; `ReturnStatus`), `return_items`
> - **Engagement:** `reviews`, `review_helpful_votes`, `wishlists`
> - **Platform:** `content_pages` (bilingual CMS), `whatsapp_messages` (Cloud API log), `notifications` (Laravel bell), `demand_events` (unavailable analytics), `settings` (`Setting::get/set`), `activity_logs` (admin audit + undo)
> - **Enums (`app/Enums/`):** `OrderStatus` (+enforced transitions), `PaymentStatus`, `PaymentMethod`, `CouponType`, `PaymentTransactionType`, `ReturnStatus`
>
> **Deliberately NOT built (schema-ready, deferred):** product variants (1 product = 1 SMACC SKU); QR in-store coupons; the payment/shipping/WhatsApp **service** layer (gateways/OTO/Cloud-API clients) — that's the next phase.

_Original roadmap (superseded by the BUILT inventory above — kept for historical context):_

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
- **Dual-push is ACTIVE** (configured 2026-06-28) — one `git push` → **both** repos. The client repo **`https://github.com/retab-dates-dev/retab-website.git`** is the **Railway production source** (currently empty — first push to populate it is pending). Configured via:
  ```bash
  git remote set-url --add --push origin https://github.com/mercenary19961/retab-stores.git    # re-add own repo FIRST
  git remote set-url --add --push origin https://github.com/retab-dates-dev/retab-website.git   # client / prod repo
  git remote -v   # → 1 fetch URL + 2 push URLs
  ```
  ⚠️ The **first** `--add --push` line (re-adding your own repo) is required — adding any explicit push URL drops the implicit default, so without it pushes would go **only** to the client repo. Read access to the client repo is verified (`git ls-remote` → exit 0). Because **every** push hits both URLs, once Railway watches the client repo's `main`, any push of `main` will deploy to production.
- Commit/push **only when the user asks** (don't auto-push). Branch off `main` for feature work.

---

## Commit Message Convention

Format: `type(scope): short description`

Types: `init` (scaffolding/setup) · `feat` · `fix` · `refactor` · `style` (visual only) · `doc` · `chore`

Rules: lowercase subject, no trailing period, present/imperative tense ("add" not "added"), under 72 chars, be specific ("fix: resolve 419 on locale toggle", not "fix: fix bug").

**Do NOT add any attribution/co-author trailer to commit messages** — no `Co-Authored-By:` line, no "Generated with" line. _(User preference set 2026-06-30; overrides the harness default.)_

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
- [x] **E-commerce schema** — built on `construction_phase`: 28 tables + 6 enums + Eloquent models, migrated on MariaDB + SQLite, 26 tests green (see Database Schema). _Service/gateway layer next._
- [ ] **Zid data migration** path (export → import catalogue + customers + order history)
- [ ] Security hardening (Turnstile, SecurityHeaders/CSP, rate limits, `URL::forceScheme` + trustProxies) — port from Sky Amman
- [x] Pushed to GitHub (`origin`) + **dual-push to client repo `retab-dates-dev/retab-website` configured** (Railway prod source). ⏳ Client repo still empty — first push to populate it is pending.

### Storefront + Admin (TODO)
- [ ] Storefront: catalogue, product detail, cart, checkout, order confirmation, customer account
- [ ] Admin/back-office: products CRUD, orders, customers, settings, content
- [ ] SEO (SSR on, sitemap/robots, Product/Offer JSON-LD, hreflang)

### Infrastructure (TODO)
- [ ] **Runtime SSR** — starter kit already ships `resources/js/ssr.jsx` + `build:ssr`; still need `config/inertia.php` (env toggle) + graceful-fallback gateway + production SSR sidecar (**port Sky Amman's `TimeoutHttpGateway` + sidecar**). _Flagged by the user as the immediate follow-up after this file._
- [ ] Automated tests + CI (PHPUnit / Vitest / Playwright), branch protection on `main`
- [ ] Production deploy (MySQL, env vars, data-seeding migrations), Cloudflare DNS + Turnstile keys, mail domain verification

> **Last updated:** 2026-06-28 — **Scaffold + CLAUDE.md, Inertia v2 → v3, local DB → XAMPP MariaDB, dual-push to client repo wired.**
> - **Dual-push to client repo configured:** added `https://github.com/retab-dates-dev/retab-website.git` as a 2nd push URL on `origin` (now 1 fetch + 2 push URLs) — this is the **Railway production source**. Read access verified (`ls-remote` exit 0); repo is empty so first push will populate it. Chose dual-push (one `git push` → both repos) over a separate `client` remote.
> - **Local DB configured + migrated:** `.env` points at XAMPP **MariaDB 10.4.32** (`127.0.0.1:3307`, db `retab-stores`, root / empty password); ran `php artisan migrate` (users/cache/jobs + sessions tables created). Confirmed the engine is **MariaDB**, not MySQL — XAMPP ships MariaDB under the "mysql" name (binary `C:\xampp\mysql\bin\mysqld.exe`, service `mysql`); TablePlus was just the client used to create the DB. `APP_KEY` already present from scaffold. Flagged dev↔prod DB parity (prod planned MySQL 8) as a pending decision.
> - **Inertia v2 → v3 upgrade** (on branch `construction_phase`, before any feature work): `inertia-laravel` v2.0.24→v3.1.0, `@inertiajs/react` ^2.0→^3.0 (3.5.0). Verified the doc's "v2 vs v3" framing against both lockfiles (Sky Amman really is v3.0.4) and confirmed v3 needs only PHP 8.2 — the PHP-8.3 pin was about the scaffolding template, not Inertia. Only the scaffold's `resolvePageComponent` resolve needed changing (v3 dropped default-export auto-unwrap → added `.then(m => m.default)` in `app.tsx`/`ssr.jsx`, mirroring Sky Amman). Build + 26 tests green. This resolves the former "Inertia is v2 here" divergence from Sky Amman.
> - Scaffolded `retab-stores` with the official Laravel 12 React starter kit (Inertia v2 + React 19 + TS + Tailwind v4, PHP 8.2 / starter kit v1.0.1). Added `.npmrc` before install to dodge the `NODE_ENV=production` devDep trap; production build verified clean. Git on `main`, `origin` → `mercenary19961/retab-stores` (dual-push to the client repo to be added once it exists).
> - Wrote this `CLAUDE.md` adopting Sky Amman's conventions (Build Progress checklist, `> Last updated:` log, commit convention + commit-suggestion rule, code-quality rules) and set **Sky Amman as the reference project**, with an explicit "don't blindly copy" list (Inertia **v2** not v3, lowercase `pages/` + modular routes, shadcn/Radix base, no i18n/Turnstile/admin yet).
> - **SSR noted as the immediate next task:** build-time scaffolding (`ssr.jsx` + `build:ssr`) is present, but runtime SSR (config/inertia.php toggle + graceful-fallback gateway + prod sidecar) still needs wiring — to be ported from Sky Amman.

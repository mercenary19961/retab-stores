# Retab Stores — Project Context

> Quick reference for AI assistants and developers

> **📍 Doc sync:** CLAUDE.md last synced to commit `0cfd70e` — 2026-07-02 13:54 (Thu) [`construction_phase`].
> _Convention: whenever you edit this file, refresh this line to the current commit — run_ `git log -1 --format="%h %cd" --date=format:"%Y-%m-%d %H:%M (%a)"` _and paste the hash + date + time. Anchors the doc to a known code state; pairs with the prose `> Last updated:` log at the bottom of Build Progress._

> **📌 Log the tricky stuff.** Whenever you hit an **issue, blocker, non-obvious behavior, or anything that cost real debugging time**, write it down with its **symptom → root cause → fix** — inline near the relevant section (retab's style, e.g. the MariaDB `db:show` and dual-push `--add --push` notes) and/or a one-liner in the `> Last updated:` log. The same stack is reused across projects (Sky Amman, HardRock, hardrock-ecom-demo), so a gotcha captured once saves the next project too. Traps → document as a gotcha; reusable patterns → note under Architecture/Decisions. When in doubt, over-document.

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

### Bilingual (Arabic-first) + RTL — **(BUILT — foundation + storefront; deferred items below)**
- **Locale = server session, default AR** (single source of truth, no localStorage): `SetLocale` middleware → `app()->setLocale()`, `LocaleController` + `POST /locale/{locale}` (whitelisted `ar|en`), `locale` shared via `HandleInertiaRequests`. `<html dir>` is set from the locale in `app.blade.php` for correct RTL on **first paint** (no flash).
- **Client i18n = `react-i18next` + `i18next`** (`resources/js/i18n/{index,ar,en}.ts`, `lng`/`fallbackLng` = `ar`) + `LanguageContext` (`useLanguage()` → toggle does `fetch POST /locale/{lang}`, updates `document.dir`/`lang`; seeded from the shared session locale). Wired into **both** `app.tsx` + `ssr.jsx`; provider wraps the whole app.
- **DB content (`_ar`/`_en` columns):** controllers ship **BOTH** locales; the client picks via **`useLocalized()`** (`resources/js/lib/localize.ts`, AR fallback) so the toggle is **instant** — no server round-trip (mirrors Sky Amman). Stored content always wins over i18n fallbacks.
- RTL: use CSS **logical properties** (`text-start`, `ms-*`/`me-*`, `ps-*`/`pe-*`), not `text-left`/`flex-end`.
- **Server-side strings (BUILT):** flash + error messages go through `__('messages.*')` (`lang/{ar,en}/messages.php`, storefront **and** admin); Laravel validation/auth/passwords localized (`lang/ar/*` mirroring published `lang/en/*`, incl. AR field-name `attributes`). Resolved in the request's session locale. ⚠️ Server-rendered strings (these + product names) reflect the locale **at request time** — they follow on the next request, not the instant client toggle.
- **Deferred:** **admin panel i18n** (admin UI is EN/LTR-pinned by design for now — server flash already localizes); **Arabic web font** (IBM Plex Sans Arabic / Tajawal, swap via `html[dir="rtl"]`); **SSR locale** (call `i18n.changeLanguage(locale)` before render once runtime SSR is enabled — noted in `i18n/index.ts`).

### File Storage — **(BUILT — media layer + product images)**
- **Single upload path = `App\Support\Media`** (`storeImage()` / `url()` / `delete()`): validates extension AND MIME, randomizes the filename (UUID), stores public on the **`media` disk**. **Never write public files directly** — always go through `Media` (same rule as `Setting::set()`).
- **Storage provider — DECIDED: Cloudflare R2** (S3-compatible, **zero egress fees**, fits the planned Cloudflare stack). Wired as the `r2` disk in `config/filesystems.php` via Laravel's **s3 driver** (`region: auto`, path-style endpoint) — no R2-specific code, so swapping to real S3 is an env change. The active disk is chosen by **`MEDIA_DISK`** env: defaults to the local **`public`** disk in dev (no creds; run `php artisan storage:link` once), set **`MEDIA_DISK=r2`** in production (Railway's FS is ephemeral — uploads MUST NOT live on the container disk).
  - Prod env (Railway only, never commit): `MEDIA_DISK=r2`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT` (`https://<account-id>.r2.cloudflarestorage.com`), `R2_URL` (public bucket / custom domain).
  - **Cost:** R2 free tier = **10 GB storage + 1M writes + 10M reads per month**, then **$0.015/GB-month**, **$4.50/M writes**, **$0.36/M reads**, **$0 egress**. A dates catalogue (hundreds of images, ~sub-GB) sits in the free tier indefinitely; egress being free is the reason it beats S3 for an image-heavy storefront.
- **Product images BUILT** on this: `product_images` (path/alt/sort/is_primary) managed via `Admin\ProductImageController` (upload/delete/set-primary on dedicated multipart-POST endpoints — separate from the product text form to avoid PUT-multipart issues); first image auto-primary; deleting the primary promotes the next. Storefront catalogue/product + admin list/edit render via `Media::url()` with a 🌴 placeholder fallback.
- **Return-request photos BUILT** on this layer (`returns/{order_id}/…`, shown in the admin review). **Still pending:** review photos. Seeded/structural images (logo, etc.) still go in git under `public/`.

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
- **⚠️ Intelephense gotcha — P1005 "Expected 4. Found 2." on `Model::where(...)` (solved for good 2026-07-02, machine-level).** _Symptom:_ every static Eloquent call (`Product::where(...)`) red-squiggled with P1005, in **every** Laravel project, surviving even `intelephense.diagnostics.argumentCount: false`. _Root cause (three parts):_ (1) the **official Laravel VS Code extension** (`laravel.vscode-laravel`) generates `vendor/_laravel_ide/_model_helpers.php` in every project, redeclaring each model with **defaults-less** `@method static mixed where($column, $operator, $value, $boolean)` — class-level tags shadow the real optional-param signatures, so Intelephense counts all 4 as required; (2) a **second embedded Intelephense** was running — the `porifa.laravel-intelephense` fork bundles its own server, labels diagnostics `intelephense`, but only reads `laraphense.*` settings with **no diagnostics toggles** — that's why the global `argumentCount: false` "didn't work"; (3) the separate P1013 (`session()->push` "undefined") is a framework docblock gap — `Request::session()` advertises the Session **contract**, which lacks `push()` (only the concrete `Store` has it). _Fix (machine-wide, covers all sibling projects):_ uninstalled `porifa.laravel-intelephense`; added `**/vendor/_laravel_ide/**` to **`intelephense.files.exclude`** in global VS Code settings (kept the default excludes — the setting replaces, not merges); removed the now-unneeded `argumentCount: false` so real arg-count checking is back. _Project-level companion:_ `barryvdh/laravel-ide-helper` (dev dep) supplies the **correct** signatures + model `@property` autocomplete — `write_eloquent_model_mixins => true` puts `@mixin \Eloquent` on vendor's base `Model` (wiped by `composer update` → re-applied by the `post-update-cmd` hook); `ide-helper:models --write-mixin` needs the DB up, re-run after schema changes; `_ide_helper*.php` gitignored — regenerate after fresh clone. Contract-vs-implementation gaps (session `push`) still need a one-line inline `/** @var \Illuminate\Session\Store $session */`. After changes: fully restart VS Code.

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
- [x] **Arabic-first bilingual + RTL** — foundation (`SetLocale` default AR, `LocaleController`, `react-i18next`, `LanguageContext`, locale-driven `<html dir>`) + **all storefront/account/auth pages localized** with an instant AR⇄EN toggle (DB content via `useLocalized`, both locales shipped). See Architecture → Bilingual. _Deferred: server-side flash/validation, admin i18n, Arabic web font._
- [x] **E-commerce schema** — built on `construction_phase`: 28 tables + 6 enums + Eloquent models, migrated on MariaDB + SQLite, 26 tests green (see Database Schema). _Service/gateway layer next._
- [ ] **Zid data migration** path (export → import catalogue + customers + order history)
- [x] **Security hardening** (2026-07-02, ported from Sky Amman) — `SecurityHeaders` middleware (nosniff/frame/referrer/permissions always; **CSP + HSTS skipped in local only** — Vite HMR's bracketed-IPv6 origin breaks CSP; allowlist = challenges.cloudflare.com, cloudflareinsights, fonts.bunny.net; no gateway origins needed since checkout redirects OUT to hosted pages); **trustProxies locked to Cloudflare CIDRs + RFC 1918** (never `*` — X-Forwarded-For spoofing would poison rate limits/audit IPs; refresh from cloudflare.com/ips) + `URL::forceScheme('https')` in production; **Turnstile** (`TurnstileVerifier` service — no-ops without `TURNSTILE_SECRET_KEY`, fails CLOSED on CF outage; `turnstile.tsx` widget renders nothing without the site key, shared via `turnstileSiteKey` prop) gating **WhatsApp OTP send** (each send costs a real message — the highest-abuse surface); **rate limits** on every public POST (locale 30/m, cart 60/m, checkout 10/m, reviews 10/m, helpful+wishlist 30/m, returns 5/m, register 10/m; OTP already 6/m). Prod env needed: `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY`.
- [x] Pushed to GitHub (`origin`) + **dual-push to client repo `retab-dates-dev/retab-website` configured** (Railway prod source). ⏳ Client repo still empty — first push to populate it is pending.

### Storefront + Admin (in progress — most of the vertical is built; checklist was stale, corrected 2026-07-02)
- [x] Storefront: catalogue, product detail, cart, checkout (Moyasar/Tamara/bank transfer), order confirmation, customer account — **built + bilingual (AR⇄EN)**
- [x] **Returns module** (2026-07-02) — defect/damage-only per policy: customer files in-account from the order page (photos via `Media`, 3-day window off `delivered_at`, one open return per order) → admin reviews at `/admin/returns` (photos + refund preview) → approve/reject, then resolve as **exchange** or **refund**. Refunds are REAL now: `PaymentGateway::refundPayment` + `PaymentService::refund` (Moyasar `POST /payments/{id}/refund`, halalas) and `TamaraService::refund` (`/payments/simplified-refund`, major units); ledger `Refund` rows carry the actual (possibly partial) amount; `payment_status` → `refunded`/`partially_refunded`; **shipping fee refunded only when goods arrived damaged** (admin toggle); bank transfer = manual, recorded only. Also retired the `OrderConfirmationService::releaseFunds` TODO — unavailable-with-captured-card now really refunds (best-effort + logged). WhatsApp: `admin_return_requested` + `return_update` templates. Flow in `ReturnService`; guard rails throw localized `messages.returns.*`.
- [ ] Admin/back-office — **partly built:** products CRUD ✓, orders (lifecycle) ✓, SMACC stock-import ✓, returns review ✓, **settings ✓** (flat shipping fee + bank details + legal name; allowlisted keys via `Setting::set`), **content/CMS ✓** (bilingual `content_pages` CRUD at `/admin/content-pages` → public `/pages/{slug}`, AR required/EN optional, `useLocalized` instant toggle; footer links + `ContentPageSeeder` seeds returns-policy/about/contact — policy text from the real store, non-destructive re-seed); **customers view ✓** (read-only directory at `/admin/customers`: search + opt-in filter, staff excluded; detail = profile + loyalty progress/rewards + last 20 orders); **pending:** WhatsApp marketing. _(Admin still EN/LTR — its i18n is later.)_ ⚠️ Testing gotcha: a test that renders a NEW page component fails with a Vite-manifest error until `npm run build` includes it — build before running page-render tests.
- [x] **SEO (2026-07-02)** — dynamic **`/sitemap.xml`** (home + active products + published pages, lastmod) + **`/robots.txt`** (disallows admin/account/cart/checkout/orders; absolute sitemap URL) — both are ROUTES; the static `public/robots.txt` was deleted (a public file shadows the route). Product page: meta description + OG tags + **`Product`/`Offer` JSON-LD** (absolute `url` shipped from the controller); catalogue page: meta/OG. ⚠️ **hreflang deliberately skipped:** locale is session-based so AR/EN share one URL — hreflang requires distinct URLs per language (e.g. `/en` prefix); revisit only if that URL strategy is ever adopted.

### Infrastructure (TODO)
- [x] **Runtime SSR — code side (2026-07-02); production sidecar pending deploy.** `config/inertia.php` published (`INERTIA_SSR_ENABLED` env toggle, **default OFF** — dev stays CSR; + `timeout`/`connect_timeout` knobs) + **`App\Ssr\TimeoutHttpGateway`** ported from Sky Amman (hung sidecar → CSR fallback, not a 502; bound in `AppServiceProvider::boot` so it beats Inertia's register()-time binding) + `ssr.jsx` calls `i18n.changeLanguage(page.props.locale)` before render (SSR honors the session locale — verified: AR page renders AR server-side). **Verified end-to-end locally** (Sky Amman-style, not just a bundle poke): `node bootstrap/ssr/ssr.js` + `INERTIA_SSR_ENABLED=true php artisan serve` → curl shows real `<h1>` HTML in the body. **Two SSR-safety gotchas fixed** (each crashed the sidecar): (1) starter kit's `use-appearance.tsx` ran `window.matchMedia` at MODULE scope — ssr.jsx eagerly imports all pages, so the server died at import; now lazy + `typeof window` guarded. (2) `LanguageProvider` called `usePage()` while sitting ABOVE `<App>` → "usePage must be used within the Inertia component"; now takes an `initialLocale` prop passed from both entries. _At deploy: create the sidecar service (`node bootstrap/ssr/ssr.js`, private port 13714) + set `INERTIA_SSR_ENABLED=true`, `INERTIA_SSR_URL` (see Sky Amman's Railpack notes: `RAILPACK_DEPLOY_APT_PACKAGES=nodejs`, URL host baked at config:cache)._
- [ ] Automated tests + CI (PHPUnit / Vitest / Playwright), branch protection on `main`
- [ ] Production deploy (MySQL, env vars, data-seeding migrations), Cloudflare DNS + Turnstile keys, mail domain verification

> **Last updated:** 2026-07-02 — **Returns module built end-to-end (+ real gateway refunds).** Customer flow: `GET/POST /orders/{order}/return` (owner-gated; eligibility = delivered + 3-day window + no open return, enforced in `ReturnService`), photos through `Media` to `returns/{order_id}`, bilingual `shop/return-request` page + status/entry-button on the order page. Admin flow: `/admin/returns` list/detail with photos + a refund preview for both shipping toggles → approve/reject → exchange or refund. **New refund plumbing:** `refundPayment()` added to the `PaymentGateway` contract (Moyasar impl) + `PaymentService::refund` and `TamaraClient/TamaraService::refund`; refund ledger rows record the ACTUAL amount (a direct `Payment::create` — `recordTransaction` assumes order-total, wrong for partials); `payment_status` moves to `refunded`/`partially_refunded` off the summed refund ledger. `OrderConfirmationService::releaseFunds` card-refund TODO retired (real refund, best-effort). WhatsApp `return_update` (customer) + `admin_return_requested` (admins) templates added — remember to create + get them approved in Meta before launch. Verified: **123 tests / 428 assertions** (5 new: filing+photos, window, duplicate-block, ownership, admin refund partial-math) + client & SSR builds.

> **Last updated:** 2026-07-02 — **Killed the recurring Intelephense P1005/P1013 false positives — for the whole machine, not just this repo.** True root cause was **not** Eloquent magic itself: the official Laravel VS Code extension generates `vendor/_laravel_ide/_model_helpers.php` with defaults-less `@method static mixed where($column, $operator, $value, $boolean)` tags that shadow the real signatures, **and** a second embedded Intelephense (the `porifa.laravel-intelephense` fork, no diagnostics settings) kept re-reporting it — which is why the old global `argumentCount: false` never worked. Fixed by uninstalling the fork + excluding `**/vendor/_laravel_ide/**` via global `intelephense.files.exclude` (restored `argumentCount` checking). Kept `barryvdh/laravel-ide-helper` in-repo for correct signatures + model autocomplete (self-heals via `post-update-cmd`). Full gotcha in Local Development. 118 tests still green.

> **Last updated:** 2026-07-02 — **Arabic-first i18n + RTL foundation, then storefront localization (AR⇄EN).**
> - **i18n foundation** (`b94cd6d`): `SetLocale` middleware (default **AR**) + `LocaleController` + `POST /locale/{locale}`; `HandleInertiaRequests` shares `locale`; `<html dir>` set from locale in `app.blade.php` (RTL on first paint, no flash); installed `react-i18next` + `i18next` (runtime deps → survive SSR prune) with `resources/js/i18n/{ar,en}.ts` bundles; `LanguageContext` (session-cookie source of truth, no localStorage) wired into `app.tsx` + `ssr.jsx`; AR⇄EN toggle in the store header.
> - **Storefront localization** (`da3f36f`): all 10 storefront/account/auth pages + `store-layout` converted to `t()` (incl. the order-status map, GCC country names, payment-method labels that were inline Arabic dicts). DB content (product/category names) localized via **`useLocalized()`** — controllers (`ShopController::card`, `CartService::summary`, `WishlistController`) now ship both `_ar`/`_en` and the client picks by locale so the toggle is **instant** (no reload), mirroring Sky Amman. Verified: client build ✓, SSR build ✓, 118 tests / 408 assertions ✓, zero Arabic literals left in converted pages.
> - **Server-side localization** (same-day follow-up): flash/error strings → `__('messages.*')` (`lang/{ar,en}/messages.php`) across storefront + admin controllers/services; Laravel validation/auth/passwords localized (`lang/ar/*` mirroring published `lang/en/*`, with AR `attributes`). 118 tests still green (AR values equal the old literals, so flash assertions hold). ⚠️ Resolves at request time — follows on the next request, not the instant client toggle.
> - **Deferred (distinct mechanisms):** admin-panel UI i18n (still EN/LTR by design), Arabic web font (Tajawal / IBM Plex Sans Arabic), and honoring the session locale in SSR once runtime SSR lands.
> - **Also:** corrected the stale Build Progress checklist — the storefront vertical + much of admin were already built but still showed as TODO.

> **Last updated:** 2026-07-02 — **Adopted two cross-project doc-maintenance conventions from Sky Amman.**
> - **Doc-sync stamp** added at the top of this file: anchors CLAUDE.md to a git commit (hash + date + time via `git log -1`), refreshed on every doc edit — a machine-anchored freshness marker that pairs with this prose log. Seeded to `a50e0c8` (2026-07-01).
> - **"Log the tricky stuff" convention** added: document any issue/blocker/non-obvious behavior with symptom → root cause → fix (inline in retab's style and/or a line here) so it isn't re-hit — including in sibling projects on the same stack.

> **Last updated:** 2026-06-28 — **Scaffold + CLAUDE.md, Inertia v2 → v3, local DB → XAMPP MariaDB, dual-push to client repo wired.**
> - **Dual-push to client repo configured:** added `https://github.com/retab-dates-dev/retab-website.git` as a 2nd push URL on `origin` (now 1 fetch + 2 push URLs) — this is the **Railway production source**. Read access verified (`ls-remote` exit 0); repo is empty so first push will populate it. Chose dual-push (one `git push` → both repos) over a separate `client` remote.
> - **Local DB configured + migrated:** `.env` points at XAMPP **MariaDB 10.4.32** (`127.0.0.1:3307`, db `retab-stores`, root / empty password); ran `php artisan migrate` (users/cache/jobs + sessions tables created). Confirmed the engine is **MariaDB**, not MySQL — XAMPP ships MariaDB under the "mysql" name (binary `C:\xampp\mysql\bin\mysqld.exe`, service `mysql`); TablePlus was just the client used to create the DB. `APP_KEY` already present from scaffold. Flagged dev↔prod DB parity (prod planned MySQL 8) as a pending decision.
> - **Inertia v2 → v3 upgrade** (on branch `construction_phase`, before any feature work): `inertia-laravel` v2.0.24→v3.1.0, `@inertiajs/react` ^2.0→^3.0 (3.5.0). Verified the doc's "v2 vs v3" framing against both lockfiles (Sky Amman really is v3.0.4) and confirmed v3 needs only PHP 8.2 — the PHP-8.3 pin was about the scaffolding template, not Inertia. Only the scaffold's `resolvePageComponent` resolve needed changing (v3 dropped default-export auto-unwrap → added `.then(m => m.default)` in `app.tsx`/`ssr.jsx`, mirroring Sky Amman). Build + 26 tests green. This resolves the former "Inertia is v2 here" divergence from Sky Amman.
> - Scaffolded `retab-stores` with the official Laravel 12 React starter kit (Inertia v2 + React 19 + TS + Tailwind v4, PHP 8.2 / starter kit v1.0.1). Added `.npmrc` before install to dodge the `NODE_ENV=production` devDep trap; production build verified clean. Git on `main`, `origin` → `mercenary19961/retab-stores` (dual-push to the client repo to be added once it exists).
> - Wrote this `CLAUDE.md` adopting Sky Amman's conventions (Build Progress checklist, `> Last updated:` log, commit convention + commit-suggestion rule, code-quality rules) and set **Sky Amman as the reference project**, with an explicit "don't blindly copy" list (Inertia **v2** not v3, lowercase `pages/` + modular routes, shadcn/Radix base, no i18n/Turnstile/admin yet).
> - **SSR noted as the immediate next task:** build-time scaffolding (`ssr.jsx` + `build:ssr`) is present, but runtime SSR (config/inertia.php toggle + graceful-fallback gateway + prod sidecar) still needs wiring — to be ported from Sky Amman.

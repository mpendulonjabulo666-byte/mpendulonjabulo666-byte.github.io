# NutriTale — Tasks (updated 2026-11-10)

Phased plan for a shared backend serving the website and future Android/iOS
apps. Rule for every phase: the live website and its data keep working, and
each step can be undone. See `CURRENT_ARCHITECTURE.md` and `AI_ARCHITECTURE.md`.

## Decisions needed from the owner (blocking)

- [ ] Domain name and VPS budget/provider (costs money; nothing in Phase 3+ can go live without it)
- [ ] How Premium is sold in the store apps: read-only status at first, or Apple/Google in-app billing
- [ ] Who owns the VPS, domain and store accounts (client or developer) — put it in the contract

## Phase 0 — Safety net (no code moves; do first)

- [ ] Take a full database export from the InfinityFree control panel; store it off the host; repeat weekly and before every phase
- [ ] Download a copy of `config/config.local.php`, `assets/img/avatars/` and `data/` from the host (these are not in git)
- [ ] Add an admin-only diagnostics check: one small outbound request each to Gemini, Unsplash, TheMealDB; show status and time. Run on the live host and record results in `CURRENT_ARCHITECTURE.md` section 5
- [ ] Test whether a server-to-server POST to `payfast_notify.php` passes InfinityFree's security check (PayFast sandbox ITN). Record the result
- [ ] Rotate the keys that were exposed earlier (Gemini, database password, Unsplash)
- [ ] Tag the current commit as `pre-api-baseline` for rollback

## Phase 1 — Separate business logic from pages (safe on InfinityFree)

One feature per commit; add or extend a test each time; no URL or visual change.

- [ ] Create `services/`; move nothing yet, agree naming
- [ ] Recipes: listing/search/filter and detail + paywall rules out of `index.php` / `recipe.php`
- [ ] Pantry: add/remove/update/expiry out of `pantry.php`
- [ ] Favourites, ratings
- [ ] Profile, diet preferences, allergens
- [ ] Planner and shopping list
- [ ] Auth: login/registration/lockout checks as functions (pages still own the session)
- [ ] Replace hard-coded `nutritale.freedev.app` (print footer, demo-account host check) with a configurable `APP_BASE_URL`

## Phase 2 — API v1, local and staging only

- [ ] `api_tokens` table (migration) + bearer-token auth, hashed tokens, per-device revoke
- [ ] `/api/v1/` router, JSON error format, input validation, rate limits
- [ ] Endpoints in the order listed in `AI_ARCHITECTURE.md` section 3 (recipes first, AI ideas last)
- [ ] API tests for every endpoint (auth required, other users' data refused, Premium rules enforced)
- [ ] Short API reference (`API.md`)

## Phase 3 — VPS and staging

- [ ] Provision VPS; PHP-FPM + nginx or Apache, MariaDB, HTTPS, firewall, automatic security updates
- [ ] Port `.htaccess` rules (blocked folders, headers, sitemap rewrite) to the VPS web server
- [ ] Staging site + staging database on a subdomain; own `config.local.php`
- [ ] Nightly database backup with `scripts/backup_db.sh` (cron), copied off the server; test one restore
- [ ] Run the outbound diagnostics on the VPS
- [ ] Deploy by SFTP/rsync from GitHub Actions to staging first, production on approval

## Phase 4 — Migration and cut-over

- [ ] Rehearsal: export InfinityFree database → import to staging → run tests → click through the site
- [ ] Lower DNS TTL a few days ahead
- [ ] Cut-over window: maintenance page on old site → final export → import → copy avatars and `data/` → switch DNS
- [ ] Update at the providers: OAuth redirect URLs (Google, Facebook, Apple), PayFast notify/return URLs, Turnstile hostname
- [ ] Redirect the old `freedev.app` address to the new domain
- [ ] **Rollback:** keep the InfinityFree site and database untouched for 14 days; to roll back, point DNS back and remove the maintenance page. Any data written on the new server after cut-over must be exported first

## Phase 5 — Mobile apps

- [ ] Miss Mvula opens Google Play and Apple Developer accounts (business, D-U-N-S)
- [ ] Expo app with configurable API base URL; Android first, then iPhone
- [ ] Sign in, recipes, pantry, AI ideas, favourites, planner, shopping list, account deletion
- [ ] Retailer links open in the system browser
- [ ] Store listings, privacy details, review account

## Not to be built yet

- [ ] Wellness / health logging (mood, weight, symptoms, intake) — blocked until consent, access controls, retention and safety rules are written and approved
- [ ] Self-hosted or self-trained AI model
- [ ] In-app ordering from retailers

## Open items carried over

- [ ] Client details for legal pages: business name, address, support email
- [ ] PayFast real merchant details (still sandbox)
- [ ] Written contract (price or laptop, ownership, maintenance, who pays hosting/AI/store fees)
- [ ] Landing-page redesign / bowl intro — on hold pending owner decision
- [ ] Delete `t.php` from the host if still present
